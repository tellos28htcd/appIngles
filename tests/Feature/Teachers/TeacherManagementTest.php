<?php

namespace Tests\Feature\Teachers;

use App\Enums\ContractType;
use App\Enums\TeacherStatus;
use App\Enums\UserStatus;
use App\Livewire\Auth\Login;
use App\Livewire\Teachers\TeacherEditor;
use App\Livewire\Teachers\TeacherIndex;
use App\Livewire\Users\UserEditor;
use App\Models\Role;
use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\UserInvitation;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class TeacherManagementTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;

    private School $schoolB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, MenuSeeder::class]);
        $this->schoolA = School::factory()->create(['name' => 'ESCUELA A']);
        $this->schoolB = School::factory()->create(['name' => 'ESCUELA B']);
    }

    private function user(string $roleSlug, ?School $school): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $roleSlug)->value('id'), 'school_id' => $school?->id]);
    }

    private function fillTeacher(Testable $component): Testable
    {
        return $component
            ->set('form.first_name', 'María')
            ->set('form.last_name', 'Pérez')
            ->set('form.second_last_name', 'Gómez')
            ->set('form.email', 'maria.perez@appingles.com')
            ->set('form.contract_type', 'full_time');
    }

    public function test_registering_a_teacher_creates_its_user_and_sends_invitation(): void
    {
        Notification::fake();
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolA));

        $this->fillTeacher(Livewire::test(TeacherEditor::class))
            ->set('form.curp', 'pegm850101mqtrmr09')
            ->set('form.rfc', 'pegm850101ab1')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('teachers.index'));

        $teacher = Teacher::with('user.role')->sole();
        $this->assertSame($this->schoolA->id, $teacher->school_id);
        $this->assertSame('PEGM850101MQTRMR09', $teacher->curp);
        $this->assertSame(ContractType::FullTime, $teacher->contract_type);
        $this->assertSame(48, $teacher->weekly_hours);

        $user = $teacher->user;
        $this->assertSame(Role::TEACHER, $user->role->slug);
        $this->assertSame($this->schoolA->id, $user->school_id);
        $this->assertSame('María Pérez Gómez', $user->name);
        $this->assertFalse($user->hasPassword());
        Notification::assertSentTo($user, UserInvitation::class);
    }

    public function test_weekly_hours_follow_the_contract_type(): void
    {
        Notification::fake();
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolA));

        $this->fillTeacher(Livewire::test(TeacherEditor::class))
            ->set('form.contract_type', 'part_time')
            ->assertSet('form.weekly_hours', 24)
            ->set('form.weekly_hours', 40)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(24, Teacher::sole()->weekly_hours);
    }

    public function test_hourly_teacher_can_never_exceed_48_hours(): void
    {
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolA));

        $this->fillTeacher(Livewire::test(TeacherEditor::class))
            ->set('form.contract_type', 'hourly')
            ->set('form.weekly_hours', 49)
            ->call('save')
            ->assertHasErrors(['form.weekly_hours' => 'max']);

        Notification::fake();
        $this->fillTeacher(Livewire::test(TeacherEditor::class))
            ->set('form.contract_type', 'hourly')
            ->set('form.weekly_hours', 12)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(12, Teacher::sole()->weekly_hours);
    }

    public function test_curp_and_rfc_are_optional_but_validated(): void
    {
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolA));

        $this->fillTeacher(Livewire::test(TeacherEditor::class))
            ->set('form.curp', 'ABC')
            ->set('form.rfc', '123')
            ->call('save')
            ->assertHasErrors(['form.curp', 'form.rfc']);
    }

    public function test_only_an_active_teacher_can_log_in(): void
    {
        $teacher = Teacher::factory()->create(['school_id' => $this->schoolA->id]);
        $teacher->user->update(['email' => 'docente@appingles.com']);
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolA));

        Livewire::test(TeacherEditor::class, ['teacher' => $teacher])
            ->set('form.status', TeacherStatus::UnpaidLeave->value)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(UserStatus::Inactive, $teacher->user->fresh()->status);

        auth()->logout();
        Livewire::test(Login::class)
            ->set('email', 'docente@appingles.com')
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors('email');
    }

    public function test_photo_is_private_and_only_visible_to_authorized_users(): void
    {
        Storage::fake('local');
        Notification::fake();
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolA));

        $this->fillTeacher(Livewire::test(TeacherEditor::class))
            ->set('form.photo', UploadedFile::fake()->image('Foto Maria.JPG', 300, 300))
            ->call('save')
            ->assertHasNoErrors();

        $teacher = Teacher::sole();
        Storage::disk('local')->assertExists($teacher->photo_path);
        $this->assertSame(mb_strtolower($teacher->photo_path), $teacher->photo_path);

        $this->get(route('teacher-photos.show', $teacher))->assertOk();

        // Otra escuela no la ve; el propio teacher sí.
        $this->flushSession();
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolB))->get(route('teacher-photos.show', $teacher))->assertNotFound();
        $teacher->user->forceFill(['password' => 'Academia2026'])->save();
        $this->flushSession();
        $this->actingAs($teacher->user->fresh())->get(route('teacher-photos.show', $teacher))->assertOk();
    }

    public function test_teachers_are_isolated_per_school(): void
    {
        $mine = Teacher::factory()->create(['school_id' => $this->schoolA->id, 'first_name' => 'Ana']);
        $foreign = Teacher::factory()->create(['school_id' => $this->schoolB->id, 'first_name' => 'Beto']);
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolA));

        $this->get(route('teachers.index'))->assertOk()->assertSee('Ana')->assertDontSee('Beto');
        $this->get(route('teachers.edit', $foreign))->assertNotFound();
        Livewire::test(TeacherIndex::class)->call('confirmDeletion', $foreign->id)->assertNotFound();
        $this->assertModelExists($mine);
    }

    public function test_academic_coordinator_manages_teachers_but_teacher_role_cannot(): void
    {
        $this->actingAs($this->user(Role::ACADEMIC_COORDINATOR, $this->schoolA))->get(route('teachers.index'))->assertOk();
        $this->actingAs($this->user(Role::TEACHER, $this->schoolA))->get(route('teachers.index'))->assertForbidden();
    }

    public function test_teacher_created_from_users_gets_an_incomplete_profile(): void
    {
        Notification::fake();
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolA));

        Livewire::test(UserEditor::class)
            ->set('form.first_name', 'Luis')
            ->set('form.last_name', 'Ramos')
            ->set('form.email', 'luis.ramos@appingles.com')
            ->set('form.role_id', Role::where('slug', Role::TEACHER)->value('id'))
            ->call('save')
            ->assertHasNoErrors();

        $teacher = Teacher::sole();
        $this->assertSame('Luis', $teacher->first_name);
        $this->assertTrue($teacher->isIncomplete());

        $this->get(route('teachers.index'))->assertSee('Datos incompletos');
    }

    public function test_unused_teacher_can_be_deleted_together_with_its_user(): void
    {
        $teacher = Teacher::factory()->create(['school_id' => $this->schoolA->id]);
        $teacherUser = $teacher->user;
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolA));

        Livewire::test(TeacherIndex::class)->call('confirmDeletion', $teacher->id)->call('delete');

        $this->assertModelMissing($teacher);
        $this->assertModelMissing($teacherUser);
    }
}
