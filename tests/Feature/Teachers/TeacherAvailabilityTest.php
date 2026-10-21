<?php

namespace Tests\Feature\Teachers;

use App\Actions\Teachers\AvailabilityPlanner;
use App\Enums\HolidayType;
use App\Enums\TeacherIncidentType;
use App\Enums\TeacherStatus;
use App\Livewire\Teachers\AvailabilityPanel;
use App\Livewire\Teachers\BaseScheduleEditor;
use App\Livewire\Teachers\TeacherIncidentIndex;
use App\Models\Classroom;
use App\Models\Holiday;
use App\Models\Role;
use App\Models\ScheduleSlot;
use App\Models\School;
use App\Models\Shift;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\TeacherIncident;
use App\Models\TeacherScheduleBlock;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TeacherAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private School $otherSchool;

    /** @var array<int, ScheduleSlot> hora => bloque */
    private array $slots = [];

    /** @var array<string, Classroom> nombre => aula */
    private array $rooms = [];

    private CarbonImmutable $monday;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, MenuSeeder::class]);
        $this->school = School::factory()->create();
        $this->otherSchool = School::factory()->create();
        $this->monday = CarbonImmutable::parse('2026-07-27');

        $shift = Shift::create(['school_id' => $this->school->id, 'name' => 'Matutino', 'sort_order' => 1, 'is_active' => true]);
        foreach (range(7, 20) as $number => $hour) {
            $this->slots[$hour] = ScheduleSlot::create([
                'school_id' => $this->school->id, 'shift_id' => $shift->id, 'number' => $number + 1,
                'starts_at' => sprintf('%02d:00:00', $hour), 'ends_at' => sprintf('%02d:00:00', $hour + 1), 'is_active' => true,
            ]);
        }
        foreach (range(1, 9) as $number) {
            $this->rooms[(string) $number] = Classroom::create(['school_id' => $this->school->id, 'name' => (string) $number, 'capacity' => 5, 'is_active' => true]);
        }
    }

    private function user(string $roleSlug, ?School $school = null): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $roleSlug)->value('id'), 'school_id' => ($school ?? $this->school)->id]);
    }

    /** @param array<int, list<int>> $hours día (1 = lunes) => horas */
    private function teacher(string $short, int $number, array $hours, array $attributes = []): Teacher
    {
        $teacher = Teacher::factory()->create([
            'school_id' => $this->school->id, 'user_id' => null, 'first_name' => ucfirst(strtolower($short)),
            'short_name' => $short, 'panel_number' => $number, 'weekly_hours' => 48, ...$attributes,
        ]);

        foreach ($hours as $weekday => $list) {
            foreach ($list as $hour) {
                TeacherScheduleBlock::create(['school_id' => $this->school->id, 'teacher_id' => $teacher->id, 'weekday' => $weekday, 'schedule_slot_id' => $this->slots[$hour]->id]);
            }
        }

        return $teacher;
    }

    private function cell(Teacher $teacher, CarbonImmutable $date, int $hour): ?TeacherAvailability
    {
        return TeacherAvailability::withoutGlobalScope('school')->where('teacher_id', $teacher->id)->where('is_absent', false)
            ->whereDate('date', $date->toDateString())->where('schedule_slot_id', $this->slots[$hour]->id)->first();
    }

    private function planner(): AvailabilityPlanner
    {
        return app(AvailabilityPlanner::class);
    }

    public function test_a_week_is_built_from_the_base_schedule_and_keeps_last_weeks_classrooms(): void
    {
        $melissa = $this->teacher('MELISSA', 1, [1 => [9, 10, 11]]);
        $this->planner()->generateWeek($this->school, $this->monday);

        $cell = $this->cell($melissa, $this->monday, 9);
        $this->assertNotNull($cell);
        $this->assertNull($cell->classroom_id);
        $this->assertNull($this->cell($melissa, $this->monday->addDay(), 9));

        $this->planner()->setClassroom($cell, $this->rooms['3']->id);
        $this->planner()->generateWeek($this->school, $this->monday->addWeek());

        $this->assertSame($this->rooms['3']->id, $this->cell($melissa, $this->monday->addWeek(), 9)->classroom_id);
        $this->assertNull($this->cell($melissa, $this->monday->addWeek(), 10)->classroom_id);
    }

    public function test_a_classroom_cannot_have_two_teachers_at_the_same_hour(): void
    {
        $melissa = $this->teacher('MELISSA', 1, [1 => [9]]);
        $alfonso = $this->teacher('ALFONSO', 2, [1 => [9]]);
        $this->planner()->generateWeek($this->school, $this->monday);
        $this->planner()->setClassroom($this->cell($melissa, $this->monday, 9), $this->rooms['4']->id);

        $this->actingAs($this->user(Role::SCHOOL_ADMIN));
        Livewire::test(AvailabilityPanel::class)
            ->call('setClassroom', $this->cell($alfonso, $this->monday, 9)->id, $this->rooms['4']->id)
            ->assertDispatched('toast', type: 'error');

        $this->assertNull($this->cell($alfonso, $this->monday, 9)->classroom_id);
    }

    public function test_vacation_empties_the_hours_and_covers_moves_them_with_the_same_classroom(): void
    {
        $melissa = $this->teacher('MELISSA', 1, [1 => [9, 10, 11, 12, 13]]);
        $dulce = $this->teacher('DULCE', 4, [1 => [15, 16]]);
        $this->planner()->generateWeek($this->school, $this->monday);
        foreach ([9, 10, 11, 12, 13] as $hour) {
            $this->planner()->setClassroom($this->cell($melissa, $this->monday, $hour), $this->rooms['3']->id);
        }

        $this->actingAs($this->user(Role::ACADEMIC_COORDINATOR));
        Livewire::test(TeacherIncidentIndex::class)
            ->call('edit')->set('teacherId', $melissa->id)->set('incidentType', 'vacation')
            ->set('startsOn', '2026-07-27')->set('endsOn', '2026-07-27')->set('allDay', false)
            ->set('fromSlotId', $this->slots[9]->id)->set('toSlotId', $this->slots[13]->id)->set('reason', 'Vacaciones')
            ->call('save')->assertHasNoErrors();

        $this->assertNull($this->cell($melissa, $this->monday, 9));

        Livewire::test(TeacherIncidentIndex::class)
            ->call('edit')->set('teacherId', $dulce->id)->set('incidentType', 'covers')->set('coveredTeacherId', $melissa->id)
            ->set('startsOn', '2026-07-27')->set('endsOn', '2026-07-27')->set('allDay', false)
            ->set('fromSlotId', $this->slots[9]->id)->set('toSlotId', $this->slots[13]->id)->set('reason', 'Cubre vacaciones')
            ->call('save')->assertHasNoErrors();

        $covered = $this->cell($dulce, $this->monday, 9);
        $this->assertSame($this->rooms['3']->id, $covered->classroom_id);
        $this->assertSame($melissa->id, $covered->covered_teacher_id);
        $this->assertSame(7, $this->planner()->weeklyHours($dulce, $this->monday));
    }

    public function test_deleting_an_incident_restores_the_hours_and_classroom(): void
    {
        $melissa = $this->teacher('MELISSA', 1, [1 => [9]]);
        $this->planner()->generateWeek($this->school, $this->monday);
        $this->planner()->setClassroom($this->cell($melissa, $this->monday, 9), $this->rooms['3']->id);
        $this->actingAs($this->user(Role::SCHOOL_ADMIN));

        $index = Livewire::test(TeacherIncidentIndex::class)
            ->call('edit')->set('teacherId', $melissa->id)->set('incidentType', 'absence')
            ->set('startsOn', '2026-07-27')->set('endsOn', '2026-07-27')->set('reason', 'No llegó')
            ->call('save')->assertHasNoErrors();
        $this->assertNull($this->cell($melissa, $this->monday, 9));

        $incident = TeacherIncident::sole();
        $index->call('confirmDeletion', $incident->id)->call('delete');

        // La hora vuelve con el aula que tenía.
        $this->assertSame($this->rooms['3']->id, $this->cell($melissa, $this->monday, 9)?->classroom_id);
    }

    public function test_covering_requires_a_free_teacher_and_respects_48_hours(): void
    {
        $melissa = $this->teacher('MELISSA', 1, [1 => [9, 10]]);
        $alfonso = $this->teacher('ALFONSO', 2, [1 => [9]]);
        $this->actingAs($this->user(Role::SCHOOL_ADMIN));

        Livewire::test(TeacherIncidentIndex::class)
            ->call('edit')->set('teacherId', $alfonso->id)->set('incidentType', 'covers')->set('coveredTeacherId', $melissa->id)
            ->set('startsOn', '2026-07-27')->set('endsOn', '2026-07-27')->set('reason', 'Cubre')
            ->call('save')->assertHasErrors('coveredTeacherId');

        $full = $this->teacher('LEONARDO', 6, collect(range(1, 6))->mapWithKeys(fn ($day) => [$day => range(11, 18)])->all());
        Livewire::test(TeacherIncidentIndex::class)
            ->call('edit')->set('teacherId', $full->id)->set('incidentType', 'covers')->set('coveredTeacherId', $melissa->id)
            ->set('startsOn', '2026-07-27')->set('endsOn', '2026-07-27')->set('reason', 'Cubre')
            ->call('save')->assertHasErrors('coveredTeacherId');
    }

    public function test_holidays_have_no_availability_and_inactive_teachers_are_not_in_the_panel(): void
    {
        $melissa = $this->teacher('MELISSA', 1, [1 => [9], 2 => [9]]);
        $renata = $this->teacher('RENATA', 8, [1 => [9]], ['status' => TeacherStatus::Inactive]);
        Holiday::create(['school_id' => $this->school->id, 'starts_on' => '2026-07-27', 'ends_on' => '2026-07-27', 'name' => 'Festivo', 'type' => HolidayType::School, 'is_active' => true]);

        $this->planner()->generateWeek($this->school, $this->monday);

        $this->assertNull($this->cell($melissa, $this->monday, 9));
        $this->assertNotNull($this->cell($melissa, $this->monday->addDay(), 9));
        $this->assertSame(0, $this->planner()->weeklyHours($renata, $this->monday));
    }

    public function test_classrooms_come_from_the_last_armed_week_even_if_one_was_skipped(): void
    {
        $melissa = $this->teacher('MELISSA', 1, [1 => [9]]);
        $this->planner()->generateWeek($this->school, $this->monday);
        $this->planner()->setClassroom($this->cell($melissa, $this->monday, 9), $this->rooms['3']->id);

        $this->planner()->generateWeek($this->school, $this->monday->addWeeks(2));

        $this->assertSame($this->rooms['3']->id, $this->cell($melissa, $this->monday->addWeeks(2), 9)->classroom_id);
    }

    public function test_arming_a_week_adds_the_official_holidays_and_labels_them(): void
    {
        $melissa = $this->teacher('MELISSA', 1, [1 => [9], 2 => [9]]);
        $revolution = CarbonImmutable::parse('2026-11-16');

        $this->planner()->generateWeek($this->school, $revolution);

        $this->assertTrue(Holiday::withoutGlobalScope('school')->where('school_id', $this->school->id)->whereDate('starts_on', '2026-11-16')->exists());
        $this->assertNull($this->cell($melissa, $revolution, 9));
        $this->assertNotNull($this->cell($melissa, $revolution->addDay(), 9));

        $this->actingAs($this->user(Role::SCHOOL_ADMIN));
        $this->get(route('availability.index', ['mes' => '2026-11', 'semana' => '2026-11-16']))->assertOk()->assertSee('Día festivo: Día de la Revolución');
    }

    public function test_adding_or_removing_a_holiday_updates_armed_weeks(): void
    {
        $melissa = $this->teacher('MELISSA', 1, [2 => [9]]);
        $this->planner()->generateWeek($this->school, $this->monday);
        $this->planner()->setClassroom($this->cell($melissa, $this->monday->addDay(), 9), $this->rooms['3']->id);

        $holiday = Holiday::create(['school_id' => $this->school->id, 'starts_on' => '2026-07-28', 'ends_on' => '2026-07-28', 'name' => 'Aniversario', 'type' => HolidayType::School, 'is_active' => true]);
        $this->assertNull($this->cell($melissa, $this->monday->addDay(), 9));

        $holiday->update(['is_active' => false]);
        $this->assertSame($this->rooms['3']->id, $this->cell($melissa, $this->monday->addDay(), 9)?->classroom_id);
    }

    public function test_incidents_still_apply_when_recalculating_from_the_school_timezone(): void
    {
        $melissa = $this->teacher('MELISSA', 1, [2 => [9]]);
        $this->planner()->generateWeek($this->school, $this->monday);
        TeacherIncident::create([
            'school_id' => $this->school->id, 'teacher_id' => $melissa->id, 'type' => TeacherIncidentType::Vacation,
            'starts_on' => '2026-07-28', 'ends_on' => '2026-07-28', 'reason' => 'Vacaciones',
        ]);

        // "Hoy" con la hora de México (como lo calcula el horario base al guardar).
        $this->planner()->rebuildRange($this->school, CarbonImmutable::parse('2026-07-27', 'America/Mexico_City'), CarbonImmutable::parse('2026-08-01', 'America/Mexico_City'));

        $this->assertNull($this->cell($melissa, $this->monday->addDay(), 9));
    }

    public function test_recalculating_keeps_each_hour_id_for_future_bookings(): void
    {
        $melissa = $this->teacher('MELISSA', 1, [1 => [9, 10]]);
        $dulce = $this->teacher('DULCE', 4, [1 => [11]]);
        $this->planner()->generateWeek($this->school, $this->monday);
        $this->planner()->setClassroom($this->cell($melissa, $this->monday, 9), $this->rooms['3']->id);
        $this->planner()->setClassroom($this->cell($dulce, $this->monday, 11), $this->rooms['3']->id);
        $nine = $this->cell($melissa, $this->monday, 9)->id;
        $eleven = $this->cell($dulce, $this->monday, 11)->id;

        TeacherIncident::create([
            'school_id' => $this->school->id, 'teacher_id' => $melissa->id, 'type' => TeacherIncidentType::Absence,
            'starts_on' => '2026-07-27', 'ends_on' => '2026-07-27', 'from_slot_id' => $this->slots[10]->id, 'to_slot_id' => $this->slots[10]->id, 'reason' => 'No llegó',
        ]);
        $this->planner()->rebuildRange($this->school, $this->monday, $this->monday);

        $this->assertSame($nine, $this->cell($melissa, $this->monday, 9)->id);
        $this->assertSame($this->rooms['3']->id, $this->cell($melissa, $this->monday, 9)->classroom_id);
        $this->assertSame($eleven, $this->cell($dulce, $this->monday, 11)->id);
        $this->assertNull($this->cell($melissa, $this->monday, 10));
    }

    public function test_base_schedule_cannot_pass_48_hours_except_waitlist(): void
    {
        $this->actingAs($this->user(Role::SCHOOL_ADMIN));
        $teacher = $this->teacher('MELISSA', 1, []);
        $all = collect(range(1, 6))->flatMap(fn ($day) => collect(range(7, 15))->map(fn ($hour) => $day.'-'.$this->slots[$hour]->id))
            ->mapWithKeys(fn ($key) => [$key => true])->all();

        Livewire::test(BaseScheduleEditor::class, ['teacherId' => $teacher->id])->set('selected', $all)->call('save')->assertHasErrors('selected');

        $waitlist = $this->teacher('LISTA_ESP', 7, [], ['is_waitlist' => true]);
        Livewire::test(BaseScheduleEditor::class, ['teacherId' => $waitlist->id])->set('selected', $all)->call('save')->assertHasNoErrors();
        $this->assertSame(54, $waitlist->scheduleBlocks()->count());
    }

    public function test_uncovered_incident_hours_show_absent_and_covered_ones_do_not(): void
    {
        $melissa = $this->teacher('MELISSA', 1, [1 => [9, 10, 11, 12]]);
        $dulce = $this->teacher('DULCE', 4, [1 => [15]]);
        $this->planner()->generateWeek($this->school, $this->monday);

        $this->actingAs($this->user(Role::ACADEMIC_COORDINATOR));
        Livewire::test(TeacherIncidentIndex::class)
            ->call('edit')->set('teacherId', $melissa->id)->set('incidentType', 'vacation')
            ->set('startsOn', '2026-07-27')->set('endsOn', '2026-07-27')->set('allDay', true)->set('reason', 'Vacaciones')
            ->call('save')->assertHasNoErrors();
        Livewire::test(TeacherIncidentIndex::class)
            ->call('edit')->set('teacherId', $dulce->id)->set('incidentType', 'covers')->set('coveredTeacherId', $melissa->id)
            ->set('startsOn', '2026-07-27')->set('endsOn', '2026-07-27')->set('allDay', false)
            ->set('fromSlotId', $this->slots[9]->id)->set('toSlotId', $this->slots[10]->id)->set('reason', 'Cubre')
            ->call('save')->assertHasNoErrors();

        $absences = Livewire::withQueryParams(['mes' => '2026-07', 'semana' => '2026-07-27'])->test(AvailabilityPanel::class)->viewData('absences');
        $key = fn (int $hour) => '2026-07-27|'.$this->slots[$hour]->id.'|'.$melissa->id;

        // 9 y 10 las cubre Dulce; 11 y 12 nadie: se muestran "A".
        $this->assertFalse($absences->has($key(9)));
        $this->assertFalse($absences->has($key(10)));
        $this->assertTrue($absences->has($key(11)));
        $this->assertTrue($absences->has($key(12)));
    }

    public function test_the_chart_does_not_count_the_waitlist(): void
    {
        // Lunes a viernes y sábado: el promedio cuenta todos los días del panel (6).
        $this->teacher('MELISSA', 1, [1 => [9], 2 => [9], 3 => [9], 4 => [9], 5 => [9], 6 => [9]]);
        $this->teacher('DULCE', 2, [6 => [9]]);
        $this->teacher('ESPERA', 9, [1 => [9], 2 => [9], 3 => [9], 4 => [9], 5 => [9]], ['is_waitlist' => true]);
        $this->planner()->generateWeek($this->school, $this->monday);

        $this->actingAs($this->user(Role::SCHOOL_ADMIN));
        $average = Livewire::withQueryParams(['mes' => '2026-07', 'semana' => '2026-07-27'])->test(AvailabilityPanel::class)->viewData('average');

        $this->assertEquals(1.2, $average[$this->slots[9]->id]);
    }

    public function test_a_sunday_shift_adds_sunday_and_its_hours_only_apply_that_day(): void
    {
        $this->assertSame(range(1, 6), $this->planner()->weekdays($this->school));

        $sunday = Shift::create(['school_id' => $this->school->id, 'name' => 'Domingo', 'weekdays' => [7], 'sort_order' => 2, 'is_active' => true]);
        $slot = ScheduleSlot::create(['school_id' => $this->school->id, 'shift_id' => $sunday->id, 'number' => 30, 'starts_at' => '21:00:00', 'ends_at' => '22:00:00', 'is_active' => true]);
        $this->assertSame(range(1, 7), $this->planner()->weekdays($this->school));
        $this->assertCount(7, $this->planner()->days($this->school, $this->monday));

        $teacher = $this->teacher('MELISSA', 1, []);
        $this->actingAs($this->user(Role::SCHOOL_ADMIN));
        Livewire::test(BaseScheduleEditor::class, ['teacherId' => $teacher->id])
            ->set('selected', ['7-'.$slot->id => true, '1-'.$slot->id => true, '1-'.$this->slots[9]->id => true, '7-'.$this->slots[9]->id => true])
            ->call('save');

        // El turno Domingo solo el domingo; el Matutino (lunes a sábado) no el domingo.
        $this->assertEqualsCanonicalizing(['7-'.$slot->id, '1-'.$this->slots[9]->id],
            $teacher->scheduleBlocks()->get()->map(fn ($block) => $block->weekday.'-'.$block->schedule_slot_id)->all());

        $this->planner()->generateWeek($this->school, $this->monday);
        $this->assertTrue(TeacherAvailability::where('teacher_id', $teacher->id)->whereDate('date', '2026-08-02')->where('schedule_slot_id', $slot->id)->exists());
    }

    public function test_the_base_schedule_is_edited_from_the_panel_and_refreshes_it(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-27 09:00'));
        $melissa = $this->teacher('MELISSA', 1, [2 => [9]]);
        $this->planner()->generateWeek($this->school, $this->monday);
        $this->actingAs($this->user(Role::ACADEMIC_COORDINATOR));

        $panel = Livewire::withQueryParams(['mes' => '2026-07', 'semana' => '2026-07-27'])->test(AvailabilityPanel::class)
            ->call('editSchedule', $melissa->id)->assertSet('editingScheduleTeacherId', $melissa->id)
            ->assertSeeLivewire(BaseScheduleEditor::class);

        Livewire::test(BaseScheduleEditor::class, ['teacherId' => $melissa->id])
            ->set('selected', ['2-'.$this->slots[9]->id => true, '3-'.$this->slots[10]->id => true])
            ->call('save')->assertHasNoErrors()->assertDispatched('base-schedule-saved');

        // La semana armada se recalcula con el nuevo horario base.
        $this->assertNotNull($this->cell($melissa, $this->monday->addDays(2), 10));
        $panel->dispatch('base-schedule-saved', teacherId: $melissa->id)->assertSet('editingScheduleTeacherId', null);

        // Un teacher de otra escuela no se puede editar.
        $this->flushSession();
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->otherSchool));
        Livewire::test(BaseScheduleEditor::class, ['teacherId' => $melissa->id])->assertNotFound();
    }

    public function test_termination_and_rehire_incidents_change_the_status_and_the_panel_from_their_date(): void
    {
        $melissa = $this->teacher('MELISSA', 1, [1 => [9], 2 => [9], 3 => [9], 4 => [9], 5 => [9], 6 => [9]]);
        $this->planner()->generateWeek($this->school, $this->monday);
        $this->actingAs($this->user(Role::ACADEMIC_COORDINATOR));
        $register = fn (string $type, string $date) => Livewire::test(TeacherIncidentIndex::class)
            ->call('edit')->set('teacherId', $melissa->id)->set('incidentType', $type)->set('startsOn', $date)->set('reason', 'Motivo')
            ->call('save');

        $register('termination', '2026-07-29')->assertHasNoErrors();
        $melissa->refresh();
        $this->assertSame(TeacherStatus::Terminated, $melissa->status);
        $this->assertSame('2026-07-29', $melissa->terminated_on->toDateString());
        // Antes de la baja conserva sus horas; desde ese día sale del panel.
        $this->assertNotNull($this->cell($melissa, $this->monday->addDay(), 9));
        $this->assertNull($this->cell($melissa, $this->monday->addDays(2), 9));

        $register('termination', '2026-07-30')->assertHasErrors('incidentType');
        $register('rehire', '2026-07-29')->assertHasErrors('startsOn');

        $register('rehire', '2026-07-31')->assertHasNoErrors();
        $melissa->refresh();
        $this->assertSame([TeacherStatus::Active, null], [$melissa->status, $melissa->terminated_on]);
        $this->assertNull($this->cell($melissa, $this->monday->addDays(3), 9));
        $this->assertNotNull($this->cell($melissa, $this->monday->addDays(4), 9));

        // Solo se borra la última; al borrar el reingreso vuelve a estar de baja.
        $termination = TeacherIncident::where('type', 'termination')->sole();
        Livewire::test(TeacherIncidentIndex::class)->call('confirmDeletion', $termination->id)->assertSet('confirmingDeletionId', null);
        $rehire = TeacherIncident::where('type', 'rehire')->sole();
        Livewire::test(TeacherIncidentIndex::class)->call('confirmDeletion', $rehire->id)->call('delete');
        $this->assertSame(TeacherStatus::Terminated, $melissa->fresh()->status);
        $this->assertNull($this->cell($melissa, $this->monday->addDays(4), 9));
    }

    public function test_a_teacher_appears_in_the_panel_from_the_hire_date(): void
    {
        $alfonso = $this->teacher('ALFONSO', 2, [1 => [9], 2 => [9], 3 => [9]], ['hired_on' => '2026-07-28']);
        $this->planner()->generateWeek($this->school, $this->monday);

        $this->assertNull($this->cell($alfonso, $this->monday, 9));
        $this->assertNotNull($this->cell($alfonso, $this->monday->addDay(), 9));
        $this->assertNotNull($this->cell($alfonso, $this->monday->addDays(2), 9));

        // En el panel no tiene columna antes de su fecha de ingreso (ni en semanas anteriores).
        $this->teacher('MELISSA', 1, [1 => [9]]);
        $this->actingAs($this->user(Role::SCHOOL_ADMIN));
        $panel = Livewire::withQueryParams(['mes' => '2026-07', 'semana' => '2026-07-27'])->test(AvailabilityPanel::class);
        $dayTeachers = $panel->viewData('dayTeachers');
        $this->assertSame(['MELISSA'], $dayTeachers['2026-07-27']->pluck('short_name')->all());
        $this->assertSame(['MELISSA', 'ALFONSO'], $dayTeachers['2026-07-28']->pluck('short_name')->all());

        $previous = Livewire::withQueryParams(['mes' => '2026-07', 'semana' => '2026-07-20'])->test(AvailabilityPanel::class);
        $this->assertNotContains('ALFONSO', $previous->viewData('teachers')->pluck('short_name')->all());
    }

    public function test_panel_permissions_and_isolation(): void
    {
        $melissa = $this->teacher('MELISSA', 1, [1 => [9]]);
        $this->planner()->generateWeek($this->school, $this->monday);

        $this->actingAs($this->user(Role::SCHOOL_ADMIN));
        $this->get(route('availability.index', ['mes' => '2026-07', 'semana' => '2026-07-27']))->assertOk()->assertSee('MELISSA');

        $this->flushSession();
        $this->actingAs($this->user(Role::RECEPTION));
        $this->get(route('availability.index'))->assertForbidden();

        $this->flushSession();
        $this->actingAs($this->user(Role::ACADEMIC_COORDINATOR));
        $this->get(route('availability.index', ['mes' => '2026-07', 'semana' => '2026-07-27']))->assertOk();

        // Otra escuela no ve ni toca las celdas de esta.
        $this->flushSession();
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->otherSchool));
        Livewire::test(AvailabilityPanel::class)->call('setClassroom', $this->cell($melissa, $this->monday, 9)->id, null)->assertNotFound();
    }
}
