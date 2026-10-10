<?php

namespace Tests\Feature\Settings;

use App\Enums\HolidayType;
use App\Livewire\Settings\ChargeConceptIndex;
use App\Livewire\Settings\ClassroomIndex;
use App\Livewire\Settings\HolidayIndex;
use App\Livewire\Settings\MySchool;
use App\Livewire\Settings\PaymentMethodIndex;
use App\Models\ChargeConcept;
use App\Models\Classroom;
use App\Models\Holiday;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\School;
use App\Models\State;
use App\Models\User;
use App\Support\OfficialHolidays;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SchoolSettingsTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;

    private School $schoolB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, MenuSeeder::class]);
        $state = State::create(['code' => '22', 'name' => 'Querétaro']);
        $municipality = $state->municipalities()->create(['code' => '014', 'name' => 'Querétaro']);
        $this->schoolA = School::factory()->create(['code' => '001', 'name' => 'ESCUELA A', 'state_id' => $state->id, 'municipality_id' => $municipality->id]);
        $this->schoolB = School::factory()->create(['code' => '002', 'name' => 'ESCUELA B']);
    }

    private function user(string $roleSlug, ?School $school): User
    {
        return User::factory()->create([
            'role_id' => Role::where('slug', $roleSlug)->value('id'),
            'school_id' => $school?->id,
        ]);
    }

    // ---- Mi escuela ---------------------------------------------------------

    public function test_school_admin_edits_his_school_but_not_its_code(): void
    {
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolA));

        $this->get(route('my-school.edit'))->assertOk()->assertSee('Mi escuela')->assertSee('ESCUELA A');

        Livewire::test(MySchool::class)
            ->set('form.name', 'ESCUELA A CENTRO')
            ->set('form.brand_primary', '#16A34A')
            ->set('form.code', 'HACK')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('my-school.edit'));

        $school = $this->schoolA->fresh();
        $this->assertSame('ESCUELA A CENTRO', $school->name);
        $this->assertSame('#16A34A', $school->brand_primary);
        $this->assertSame('001', $school->code);
        $this->assertSame('ESCUELA B', $this->schoolB->fresh()->name);
    }

    public function test_other_roles_cannot_edit_my_school(): void
    {
        $this->actingAs($this->user(Role::TEACHER, $this->schoolA))
            ->get(route('my-school.edit'))
            ->assertForbidden();
    }

    public function test_super_admin_is_sent_to_schools_list(): void
    {
        $this->actingAs($this->user(Role::PLATFORM_ADMIN, null));

        Livewire::test(MySchool::class)->assertRedirect(route('schools.index'));
        Livewire::test(ClassroomIndex::class)->assertRedirect(route('schools.index'));
    }

    // ---- Salones, métodos y conceptos ---------------------------------------

    public function test_classrooms_are_per_school(): void
    {
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolA));

        Livewire::test(ClassroomIndex::class)
            ->call('edit')
            ->set('name', 'Aula 1')
            ->set('capacity', 8)
            ->call('save')
            ->assertHasNoErrors();

        Livewire::test(ClassroomIndex::class)
            ->call('edit')
            ->set('name', 'aula 1')
            ->set('capacity', 6)
            ->call('save')
            ->assertHasErrors(['name' => 'unique']);

        // Otra escuela puede usar el mismo nombre y no ve los salones de la primera.
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolB));
        $this->assertSame(0, Classroom::count());

        Livewire::test(ClassroomIndex::class)
            ->call('edit')
            ->set('name', 'Aula 1')
            ->set('capacity', 4)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, Classroom::withoutGlobalScope('school')->where('school_id', $this->schoolA->id)->count());
    }

    public function test_school_admin_cannot_touch_another_schools_records(): void
    {
        $foreign = Classroom::withoutGlobalScope('school')->create(['school_id' => $this->schoolB->id, 'name' => 'Aula B', 'capacity' => 6]);
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolA));

        Livewire::test(ClassroomIndex::class)->call('edit', $foreign->id)->assertNotFound();
        Livewire::test(ClassroomIndex::class)->call('toggle', $foreign->id)->assertNotFound();
        Livewire::test(ClassroomIndex::class)->call('confirmDeletion', $foreign->id)->assertNotFound();

        $this->assertTrue($foreign->fresh()->is_active);
    }

    public function test_payment_methods_start_empty_and_are_captured_by_each_school(): void
    {
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolA));

        $this->get(route('payment-methods.index'))->assertOk()->assertSee('Aún no hay métodos de pago');

        Livewire::test(PaymentMethodIndex::class)
            ->call('edit')
            ->set('name', 'Transferencia')
            ->set('requiresReference', true)
            ->call('save')
            ->assertHasNoErrors();

        $method = PaymentMethod::sole();
        $this->assertTrue($method->requires_reference);
        $this->assertSame($this->schoolA->id, $method->school_id);
    }

    public function test_charge_concept_keeps_type_and_amount(): void
    {
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolA));

        Livewire::test(ChargeConceptIndex::class)
            ->call('edit')
            ->set('name', 'Colegiatura mensual')
            ->set('type', 'tuition')
            ->set('suggestedAmount', '$1,450.50')
            ->call('save')
            ->assertHasNoErrors();

        $concept = ChargeConcept::sole();
        $this->assertSame('tuition', $concept->type->value);
        $this->assertSame('1450.50', $concept->suggested_amount);

        Livewire::test(ChargeConceptIndex::class)
            ->call('edit')
            ->set('name', 'Libro')
            ->set('suggestedAmount', '-5')
            ->call('save')
            ->assertHasErrors(['suggestedAmount']);
    }

    // ---- Días festivos --------------------------------------------------------

    public function test_official_holidays_are_calculated_for_mexico(): void
    {
        $dates = collect(OfficialHolidays::forYear(2026))->mapWithKeys(fn ($h) => [$h['key'] => $h['date']->toDateString()]);

        $this->assertSame('2026-01-01', $dates['new_year']);
        $this->assertSame('2026-02-02', $dates['constitution']);
        $this->assertSame('2026-03-16', $dates['benito_juarez']);
        $this->assertSame('2026-11-16', $dates['revolution']);
        $this->assertArrayNotHasKey('executive_transition', $dates->all());
        $this->assertArrayHasKey('executive_transition', collect(OfficialHolidays::forYear(2030))->keyBy('key')->all());
    }

    public function test_holidays_page_generates_official_days_once_and_respects_deactivation(): void
    {
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolA));

        $component = Livewire::test(HolidayIndex::class, ['year' => 2026]);
        $this->assertSame(7, Holiday::inYear(2026)->where('type', HolidayType::Official)->count());

        $christmas = Holiday::where('official_key', 'christmas')->first();
        $component->call('toggle', $christmas->id);
        $this->assertFalse($christmas->fresh()->is_active);

        // Volver a abrir no duplica ni reactiva.
        Livewire::test(HolidayIndex::class, ['year' => 2026]);
        $this->assertSame(7, Holiday::inYear(2026)->where('type', HolidayType::Official)->count());
        $this->assertFalse($christmas->fresh()->is_active);

        // Los oficiales no se editan ni se eliminan.
        Livewire::test(HolidayIndex::class, ['year' => 2026])->call('edit', $christmas->id)->assertNotFound();
        Livewire::test(HolidayIndex::class, ['year' => 2026])->call('confirmDeletion', $christmas->id)->assertSet('confirmingDeletion', null);

        // La otra escuela tiene los suyos, independientes.
        $this->assertSame(0, Holiday::withoutGlobalScope('school')->where('school_id', $this->schoolB->id)->count());
    }

    public function test_school_adds_a_holiday_period(): void
    {
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolA));

        Livewire::test(HolidayIndex::class, ['year' => 2026])
            ->call('edit')
            ->set('name', 'Vacaciones de verano')
            ->set('startsOn', '2026-07-20')
            ->set('endsOn', '2026-07-31')
            ->call('save')
            ->assertHasNoErrors();

        $holiday = Holiday::where('type', HolidayType::School)->sole();
        $this->assertSame(12, $holiday->days());
        $this->assertTrue(Holiday::covering('2026-07-25')->exists());
        $this->assertFalse(Holiday::covering('2026-08-01')->exists());

        Livewire::test(HolidayIndex::class, ['year' => 2026])
            ->call('edit')
            ->set('name', 'Al revés')
            ->set('startsOn', '2026-07-31')
            ->set('endsOn', '2026-07-20')
            ->call('save')
            ->assertHasErrors(['endsOn']);
    }

    public function test_menu_shows_settings_options_to_school_admin(): void
    {
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolA))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('my-school.edit'))
            ->assertSee(route('classrooms.index'))
            ->assertSee(route('payment-methods.index'))
            ->assertSee(route('charge-concepts.index'))
            ->assertSee(route('holidays.index'));
    }
}
