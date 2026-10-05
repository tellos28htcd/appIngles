<?php

namespace Tests\Feature\Schools;

use App\Enums\SchoolStatus;
use App\Livewire\Auth\Login;
use App\Livewire\Schools\SchoolEditor;
use App\Livewire\Schools\SchoolIndex;
use App\Models\Municipality;
use App\Models\Role;
use App\Models\School;
use App\Models\State;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class SchoolManagementTest extends TestCase
{
    use RefreshDatabase;

    private State $queretaro;

    private Municipality $queretaroCity;

    private Municipality $otherStateMunicipality;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, MenuSeeder::class]);

        $this->queretaro = State::create(['code' => '22', 'name' => 'Querétaro']);
        $this->queretaroCity = $this->queretaro->municipalities()->create(['code' => '014', 'name' => 'Querétaro']);
        $this->otherStateMunicipality = State::create(['code' => '01', 'name' => 'Aguascalientes'])
            ->municipalities()->create(['code' => '001', 'name' => 'Aguascalientes']);
    }

    private function userWithRole(string $slug, ?School $school = null): User
    {
        return User::factory()->create([
            'role_id' => Role::where('slug', $slug)->value('id'),
            'school_id' => $school?->id,
        ]);
    }

    private function fillValidSchool(Testable $component): Testable
    {
        return $component
            ->set('form.code', '026')
            ->set('form.name', 'QUERÉTARO NORTE')
            ->set('form.state_id', $this->queretaro->id)
            ->set('form.municipality_id', $this->queretaroCity->id)
            ->set('form.rfc', 'iqe200101ab1')
            ->set('form.postal_code', '76100')
            ->set('form.last_folio_series_a', 1520)
            ->set('form.session_capacity', 5)
            ->set('form.max_sessions', 3)
            ->set('form.self_booking', 'any_time');
    }

    public function test_platform_admin_creates_a_school_with_excel_fields(): void
    {
        $this->actingAs($this->userWithRole(Role::PLATFORM_ADMIN));

        $this->fillValidSchool(Livewire::test(SchoolEditor::class))
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('schools.index'));

        $school = School::sole();
        $this->assertSame('026', $school->code);
        $this->assertSame('IQE200101AB1', $school->rfc);
        $this->assertSame($this->queretaroCity->id, $school->municipality_id);
        $this->assertSame(1520, $school->last_folio_series_a);
        $this->assertSame(5, $school->session_capacity);
        $this->assertSame(SchoolStatus::Active, $school->status);
    }

    public function test_editor_renders_every_excel_section_and_setting(): void
    {
        $this->actingAs($this->userWithRole(Role::PLATFORM_ADMIN))
            ->get(route('schools.create'))
            ->assertOk()
            ->assertDontSee('<x-ui.', false)
            ->assertSee('Datos del plantel')
            ->assertSee('Clave del instituto')
            ->assertSee('Trabaja los días domingo')
            ->assertSee('Programar horario para las aulas')
            ->assertSee('Agendar sin asignar un aula')
            ->assertSee('Agendar clubes híbridos')
            ->assertSee('El alumno requiere progreso para acceder a los contenidos')
            ->assertSee('Recorrer las actividades agendadas')
            ->assertSee('Último folio serie A (fiscalizado)');
    }

    public function test_municipality_must_belong_to_the_selected_state(): void
    {
        $this->actingAs($this->userWithRole(Role::PLATFORM_ADMIN));

        $this->fillValidSchool(Livewire::test(SchoolEditor::class))
            ->set('form.municipality_id', $this->otherStateMunicipality->id)
            ->call('save')
            ->assertHasErrors(['form.municipality_id']);
    }

    public function test_changing_state_clears_municipality(): void
    {
        $this->actingAs($this->userWithRole(Role::PLATFORM_ADMIN));

        $this->fillValidSchool(Livewire::test(SchoolEditor::class))
            ->set('form.state_id', $this->otherStateMunicipality->state_id)
            ->assertSet('form.municipality_id', null);
    }

    public function test_session_capacity_cannot_exceed_six(): void
    {
        $this->actingAs($this->userWithRole(Role::PLATFORM_ADMIN));

        $this->fillValidSchool(Livewire::test(SchoolEditor::class))
            ->set('form.session_capacity', 7)
            ->call('save')
            ->assertHasErrors(['form.session_capacity' => 'max']);
    }

    public function test_school_code_is_unique(): void
    {
        School::factory()->create(['code' => '026']);
        $this->actingAs($this->userWithRole(Role::PLATFORM_ADMIN));

        $this->fillValidSchool(Livewire::test(SchoolEditor::class))
            ->call('save')
            ->assertHasErrors(['form.code' => 'unique']);
    }

    public function test_logo_is_stored_with_a_lowercase_generated_name(): void
    {
        Storage::fake('public');
        $this->actingAs($this->userWithRole(Role::PLATFORM_ADMIN));

        $this->fillValidSchool(Livewire::test(SchoolEditor::class))
            ->set('form.logo', UploadedFile::fake()->image('Logo Escuela.PNG', 200, 80))
            ->call('save')
            ->assertHasNoErrors();

        $path = School::sole()->logo_path;
        $this->assertSame(mb_strtolower($path), $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_school_admin_cannot_manage_schools(): void
    {
        $school = School::factory()->create();
        $this->actingAs($this->userWithRole(Role::SCHOOL_ADMIN, $school));

        $this->get(route('schools.index'))->assertForbidden();
        $this->get(route('schools.create'))->assertForbidden();
        $this->get(route('schools.edit', $school))->assertForbidden();
        Livewire::test(SchoolEditor::class)->assertForbidden();
    }

    public function test_suspending_a_school_blocks_its_users_from_logging_in(): void
    {
        $school = School::factory()->create();
        $teacher = $this->userWithRole(Role::TEACHER, $school);

        $this->actingAs($this->userWithRole(Role::PLATFORM_ADMIN));
        Livewire::test(SchoolIndex::class)
            ->call('confirmSuspend', $school->id)
            ->call('suspend');

        $this->assertSame(SchoolStatus::Suspended, $school->fresh()->status);

        auth()->logout();
        Livewire::test(Login::class)
            ->set('email', $teacher->email)
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors(['email' => __('access.school_suspended')]);
        $this->assertGuest();
    }

    public function test_school_users_use_their_school_brand(): void
    {
        $school = School::factory()->create(['name' => 'QUERÉTARO NORTE', 'brand_primary' => '#3B2BB8']);

        $this->actingAs($this->userWithRole(Role::SCHOOL_ADMIN, $school))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('--brand-primary: #3B2BB8', false)
            ->assertSee('QUERÉTARO NORTE');
    }
}
