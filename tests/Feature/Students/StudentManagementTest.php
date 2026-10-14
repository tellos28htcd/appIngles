<?php

namespace Tests\Feature\Students;

use App\Enums\StudentStatus;
use App\Enums\UserStatus;
use App\Livewire\Guardians\GuardianIndex;
use App\Livewire\Students\StudentEditor;
use App\Livewire\Students\StudentIndex;
use App\Livewire\Students\StudentShow;
use App\Livewire\Users\UserEditor;
use App\Models\Guardian;
use App\Models\Role;
use App\Models\SalesAdvisor;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentPlan;
use App\Models\StudentType;
use App\Models\User;
use App\Notifications\UserInvitation;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StudentCatalogBaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;

    private School $schoolB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, MenuSeeder::class]);
        $this->schoolA = School::factory()->create(['name' => 'QUERÉTARO NORTE', 'enrollment_prefix' => 'QRON']);
        $this->schoolB = School::factory()->create(['name' => 'ESCUELA B']);
        $this->seed(StudentCatalogBaseSeeder::class);
        $this->travelTo(now()->setDate(2026, 8, 15));
    }

    private function user(string $roleSlug, ?School $school): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $roleSlug)->value('id'), 'school_id' => $school?->id]);
    }

    private function newStudent(): Testable
    {
        return Livewire::test(StudentEditor::class)
            ->set('form.first_name', 'María')
            ->set('form.last_name', 'Pérez');
    }

    public function test_registering_a_student_creates_its_user_without_sending_any_mail(): void
    {
        Notification::fake();
        $this->schoolA->forceFill(['last_enrollment_sequence' => 505])->save();
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolA));
        $plan = StudentPlan::ofCatalog($this->schoolA->id)->where('code', 'F')->sole();
        $advisor = SalesAdvisor::create(['school_id' => $this->schoolA->id, 'name' => 'ASESORA UNO']);

        $this->newStudent()
            ->set('form.email', 'maria.perez@appingles.com')
            ->set('enrollment.contract_number', 'c-100')
            ->set('enrollment.sales_advisor_id', $advisor->id)
            ->set('enrollment.student_plan_id', $plan->id)
            ->assertSet('enrollment.installments', 10)
            ->call('save')
            ->assertHasNoErrors();

        $student = Student::with(['user.role', 'enrollments', 'statusChanges'])->sole();
        $this->assertSame('QRON26080506', $student->enrollment_number);
        $this->assertSame(506, $this->schoolA->fresh()->last_enrollment_sequence);
        $this->assertSame('MARÍA', $student->first_name);
        $this->assertSame(Role::STUDENT, $student->user->role->slug);
        $this->assertSame($this->schoolA->id, $student->user->school_id);
        $this->assertFalse($student->user->hasPassword());
        $this->assertSame('C-100', $student->enrollments->sole()->contract_number);
        $this->assertSame(StudentStatus::Active, $student->statusChanges->sole()->to_status);
        $this->assertSame('ALUMN', $student->type()->value('code'));

        Notification::assertNothingSent();
    }

    public function test_student_without_email_has_no_user_until_one_is_captured(): void
    {
        Notification::fake();
        $this->actingAs($this->user(Role::STUDENT_SERVICES, $this->schoolA));

        $this->newStudent()->call('save')->assertHasNoErrors();
        $student = Student::sole();
        $this->assertNull($student->user_id);

        Livewire::test(StudentEditor::class, ['student' => $student])
            ->set('form.email', 'maria@appingles.com')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('maria@appingles.com', $student->fresh()->user->email);
        Notification::assertNothingSent();
    }

    public function test_transfer_number_is_kept_and_does_not_consume_the_sequence(): void
    {
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolA));

        $this->newStudent()->set('form.enrollment_number', 'tula2401 0003')->call('save')->assertHasNoErrors();
        $this->assertSame('TULA24010003', Student::sole()->enrollment_number);
        $this->assertSame(0, $this->schoolA->fresh()->last_enrollment_sequence);

        // Una matrícula propia mayor hace que el consecutivo continúe desde ahí.
        $this->newStudent()->set('form.first_name', 'Luis')->set('form.enrollment_number', 'QRON25110379')->call('save')->assertHasNoErrors();
        $this->newStudent()->set('form.first_name', 'Ana')->call('save')->assertHasNoErrors();

        $this->assertSame('QRON26080380', Student::where('first_name', 'ANA')->value('enrollment_number'));
    }

    public function test_access_is_only_sent_when_requested(): void
    {
        Notification::fake();
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolA));
        $this->newStudent()->set('form.email', 'maria@appingles.com')->call('save');
        $student = Student::sole();

        Notification::assertNothingSent();

        Livewire::test(StudentIndex::class)->call('sendAccess', $student->id);

        Notification::assertSentTo($student->user, UserInvitation::class);
    }

    public function test_students_are_isolated_per_school(): void
    {
        $mine = Student::factory()->create(['school_id' => $this->schoolA->id, 'first_name' => 'PROPIO']);
        $foreign = Student::factory()->create(['school_id' => $this->schoolB->id, 'first_name' => 'AJENO']);
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolA));

        $this->get(route('students.index'))->assertOk()->assertSee('PROPIO')->assertDontSee('AJENO');
        $this->get(route('students.show', $mine))->assertOk();
        $this->get(route('students.show', $foreign))->assertNotFound();
        $this->get(route('students.edit', $foreign))->assertNotFound();
        Livewire::test(StudentIndex::class)->call('sendAccess', $foreign->id)->assertNotFound();
        Livewire::test(StudentIndex::class)->call('confirmDeletion', $foreign->id)->assertNotFound();
    }

    public function test_reception_only_consults_and_cannot_register(): void
    {
        $student = Student::factory()->create(['school_id' => $this->schoolA->id]);
        $this->actingAs($this->user(Role::RECEPTION, $this->schoolA));

        $this->get(route('students.index'))->assertOk()->assertDontSee(route('students.create'));
        $this->get(route('students.show', $student))->assertOk();
        $this->get(route('students.create'))->assertForbidden();
        $this->get(route('students.edit', $student))->assertForbidden();
        Livewire::test(StudentShow::class, ['student' => $student])->call('askStatusChange')->assertForbidden();

        $this->flushSession();
        $this->actingAs($this->user(Role::TEACHER, $this->schoolA))->get(route('students.index'))->assertForbidden();
    }

    public function test_withdrawal_requires_a_reason_keeps_history_and_blocks_access(): void
    {
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolA));
        $this->newStudent()->set('form.email', 'maria@appingles.com')->call('save');
        $student = Student::sole();

        $show = Livewire::test(StudentShow::class, ['student' => $student])
            ->call('askStatusChange')
            ->set('newStatus', StudentStatus::Withdrawn->value)
            ->call('changeStatus')
            ->assertHasErrors('statusReason');

        $show->set('statusReason', 'Devolvió el material')->call('changeStatus')->assertHasNoErrors();

        $student->refresh();
        $this->assertSame(StudentStatus::Withdrawn, $student->status);
        $this->assertSame(UserStatus::Inactive, $student->user->status);
        $change = $student->statusChanges()->latest('id')->first();
        $this->assertSame('Devolvió el material', $change->reason);
        $this->assertSame(StudentStatus::Active, $change->from_status);
    }

    public function test_one_guardian_can_have_several_students_and_receives_access_only_on_request(): void
    {
        Notification::fake();
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolA));
        [$first, $second] = Student::factory()->count(2)->create(['school_id' => $this->schoolA->id]);

        Livewire::test(StudentShow::class, ['student' => $first])
            ->call('linkGuardian')
            ->set('guardianName', 'Rosa Gómez')
            ->set('guardianEmail', 'rosa@appingles.com')
            ->set('guardianReceives', ['payments'])
            ->call('saveGuardian')
            ->assertHasNoErrors();

        $guardian = Guardian::sole();
        Livewire::test(StudentShow::class, ['student' => $second])
            ->call('linkGuardian')
            ->set('guardianId', (string) $guardian->id)
            ->set('guardianRelationship', 'Mamá')
            ->call('saveGuardian')
            ->assertHasNoErrors();

        $this->assertSame(2, $guardian->students()->count());
        $this->assertNull($guardian->fresh()->user_id);
        Notification::assertNothingSent();

        Livewire::test(GuardianIndex::class)->call('grantAccess', $guardian->id);

        $user = $guardian->fresh()->user;
        $this->assertSame(Role::GUARDIAN, $user->role->slug);
        $this->assertSame('ROSA GÓMEZ', $user->name);
        Notification::assertSentTo($user, UserInvitation::class);
    }

    public function test_guardian_of_another_school_cannot_be_linked(): void
    {
        $foreignGuardian = Guardian::create(['school_id' => $this->schoolB->id, 'name' => 'AJENO']);
        $student = Student::factory()->create(['school_id' => $this->schoolA->id]);
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolA));

        Livewire::test(StudentShow::class, ['student' => $student])
            ->call('linkGuardian')
            ->set('guardianId', (string) $foreignGuardian->id)
            ->call('saveGuardian')
            ->assertHasErrors('guardianId');

        $this->assertSame(0, $student->guardians()->count());
    }

    public function test_enrollment_cannot_point_to_another_schools_catalogs(): void
    {
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolA));
        $foreignPlan = StudentPlan::withoutGlobalScope('school')->where('school_id', $this->schoolB->id)->first();

        $this->newStudent()->set('enrollment.student_plan_id', $foreignPlan->id)->call('save')->assertHasErrors('enrollment.student_plan_id');
        $this->assertSame(0, Student::count());
    }

    public function test_student_and_guardian_roles_are_not_assigned_from_users(): void
    {
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolA));

        Livewire::test(UserEditor::class)
            ->set('form.first_name', 'Ana')
            ->set('form.last_name', 'López')
            ->set('form.email', 'ana@appingles.com')
            ->set('form.role_id', Role::where('slug', Role::STUDENT)->value('id'))
            ->call('save')
            ->assertHasErrors('form.role_id');
    }

    public function test_new_schools_receive_the_student_catalogs(): void
    {
        $this->assertSame(
            StudentType::ofCatalog(null)->count(),
            StudentType::ofCatalog($this->schoolA->id)->whereNotNull('base_id')->count(),
        );
        $this->assertSame('QUER', School::suggestEnrollmentPrefix('Querétaro Norte'));
        $this->assertSame('ELXX', School::suggestEnrollmentPrefix('El Marqués'));
    }

    public function test_every_screen_renders_with_data(): void
    {
        Notification::fake();
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolA));
        $this->newStudent()->set('form.email', 'maria@appingles.com')->set('enrollment.contract_number', 'C-1')->call('save');
        $student = Student::sole();
        $guardian = Guardian::create(['school_id' => $this->schoolA->id, 'name' => 'ROSA', 'email' => 'rosa@appingles.com']);
        $student->guardians()->attach($guardian);
        $student->absences()->create(['school_id' => $this->schoolA->id, 'starts_on' => '2026-08-01', 'reason' => 'Viaje']);

        $this->get(route('students.index'))->assertOk()->assertSee($student->enrollment_number);
        $this->assertSame('QRON26080001', $student->enrollment_number);
        $this->get(route('students.create'))->assertOk()->assertSee('QRON26080002');
        $this->get(route('students.show', $student))->assertOk()->assertSee('Contrato C-1')->assertSee('ROSA')->assertSee('Viaje');
        $this->get(route('students.edit', $student))->assertOk()->assertSee('¿Quién cubre el pago de tu curso?');
        $this->get(route('students.import'))->assertOk();
        $this->get(route('guardians.index'))->assertOk()->assertSee('ROSA')->assertSee($student->enrollment_number);

        foreach (['asesores', 'planes', 'origenes', 'tipos', 'puestos', 'encuesta'] as $tab) {
            $this->get(route('student-catalogs.index', ['pestana' => $tab]))->assertOk();
        }

        Livewire::test(StudentShow::class, ['student' => $student])
            ->call('editEnrollment', $student->enrollments()->value('id'))->assertSet('editingEnrollment', true)
            ->call('editAbsence')->assertSet('addingAbsence', true)
            ->call('linkGuardian')->assertSet('linkingGuardian', true);
    }

    public function test_photo_is_private_to_the_school_and_the_student(): void
    {
        $student = Student::factory()->create(['school_id' => $this->schoolA->id, 'photo_path' => null]);
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolB))
            ->get(route('student-photos.show', $student))->assertNotFound();
    }
}
