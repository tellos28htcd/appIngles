<?php

namespace Tests\Feature\Students;

use App\Livewire\Catalogs\SalesAdvisorsTab;
use App\Livewire\Catalogs\StudentCatalogs;
use App\Livewire\Catalogs\StudentCatalogTab;
use App\Livewire\Catalogs\SurveyQuestionsTab;
use App\Models\Role;
use App\Models\SalesAdvisor;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentPlan;
use App\Models\StudentSurveyAnswer;
use App\Models\StudentType;
use App\Models\SurveyQuestion;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StudentCatalogBaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StudentCatalogsTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;

    private School $schoolB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, MenuSeeder::class]);
        $this->schoolA = School::factory()->create();
        $this->schoolB = School::factory()->create();
        $this->seed(StudentCatalogBaseSeeder::class);
    }

    private function user(string $roleSlug, ?School $school): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $roleSlug)->value('id'), 'school_id' => $school?->id]);
    }

    public function test_school_admin_manages_its_own_student_catalogs(): void
    {
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolA));
        $this->get(route('student-catalogs.index'))->assertOk()->assertSee('Asesores de ventas');

        Livewire::test(SalesAdvisorsTab::class, ['schoolId' => $this->schoolA->id])
            ->call('edit')
            ->set('name', 'Intercambio')
            ->call('save')
            ->assertHasNoErrors();

        Livewire::test(StudentCatalogTab::class, ['schoolId' => $this->schoolA->id, 'catalog' => 'planes'])
            ->call('edit')
            ->set('values.code', 'z')
            ->set('values.name', 'Plan Z')
            ->set('values.installments', 4)
            ->call('save')
            ->assertHasNoErrors();

        Livewire::test(SurveyQuestionsTab::class, ['schoolId' => $this->schoolA->id])
            ->call('edit')
            ->set('section', 'Otros')
            ->set('question', '¿Cómo nos conociste?')
            ->set('type', 'choice')
            ->set('options', "Amigos\nRedes sociales\n\nAmigos")
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('INTERCAMBIO', SalesAdvisor::where('school_id', $this->schoolA->id)->value('name'));
        $this->assertSame(4, StudentPlan::ofCatalog($this->schoolA->id)->where('code', 'Z')->value('installments'));
        $this->assertSame(['Amigos', 'Redes sociales'], SurveyQuestion::ofCatalog($this->schoolA->id)->where('question', '¿Cómo nos conociste?')->sole()->options);

        // La base y las otras escuelas no cambian.
        $this->assertFalse(StudentPlan::withoutGlobalScope('school')->whereNull('school_id')->where('code', 'Z')->exists());
        $this->assertFalse(StudentPlan::withoutGlobalScope('school')->where('school_id', $this->schoolB->id)->where('code', 'Z')->exists());
    }

    public function test_school_admin_cannot_touch_another_school_or_the_base(): void
    {
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolA));

        Livewire::test(SalesAdvisorsTab::class, ['schoolId' => $this->schoolB->id])->assertForbidden();
        Livewire::test(StudentCatalogTab::class, ['schoolId' => null, 'catalog' => 'tipos'])->assertForbidden();
        Livewire::test(SurveyQuestionsTab::class, ['schoolId' => null])->assertForbidden();
    }

    public function test_only_school_admin_opens_the_student_catalogs(): void
    {
        $this->actingAs($this->user(Role::STUDENT_SERVICES, $this->schoolA))->get(route('student-catalogs.index'))->assertForbidden();
    }

    public function test_platform_admin_manages_the_base_and_schools_receive_it_as_an_update(): void
    {
        $this->actingAs($this->user(Role::PLATFORM_ADMIN, null));
        $this->get(route('base-catalogs.index', ['pestana' => 'encuesta']))->assertOk()->assertSee('Encuesta del alumno');

        Livewire::test(StudentCatalogTab::class, ['schoolId' => null, 'catalog' => 'tipos'])
            ->call('edit')
            ->set('values.code', 'BECA')
            ->set('values.name', 'Becado')
            ->call('save')
            ->assertHasNoErrors();

        $this->flushSession();
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolA));

        Livewire::test(StudentCatalogs::class)
            ->assertSet('tab', 'asesores')
            ->call('reviewUpdates')
            ->call('incorporateUpdates');

        $this->assertTrue(StudentType::ofCatalog($this->schoolA->id)->where('code', 'BECA')->exists());
    }

    public function test_a_question_with_answers_is_deactivated_instead_of_deleted(): void
    {
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolA));
        $question = SurveyQuestion::ofCatalog($this->schoolA->id)->first();
        $student = Student::factory()->create(['school_id' => $this->schoolA->id]);
        StudentSurveyAnswer::create(['school_id' => $this->schoolA->id, 'student_id' => $student->id, 'survey_question_id' => $question->id, 'answer' => 'No']);

        Livewire::test(SurveyQuestionsTab::class, ['schoolId' => $this->schoolA->id])
            ->call('confirmDeletion', $question->id)
            ->assertSet('confirmingDeletion', null);

        $this->assertModelExists($question);
    }
}
