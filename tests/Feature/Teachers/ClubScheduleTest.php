<?php

namespace Tests\Feature\Teachers;

use App\Actions\Teachers\AvailabilityPlanner;
use App\Enums\HolidayType;
use App\Livewire\Catalogs\ClubsTab;
use App\Livewire\Settings\ClassroomIndex;
use App\Livewire\Teachers\AvailabilityPanel;
use App\Livewire\Teachers\ClubSchedule;
use App\Livewire\Teachers\TeacherIncidentIndex;
use App\Models\Classroom;
use App\Models\Club;
use App\Models\ClubSession;
use App\Models\Holiday;
use App\Models\Role;
use App\Models\ScheduleSlot;
use App\Models\School;
use App\Models\Shift;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\TeacherScheduleBlock;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class ClubScheduleTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    /** @var array<int, ScheduleSlot> */
    private array $slots = [];

    private Classroom $audiovisual;

    private Club $conversation;

    private CarbonImmutable $monday;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, MenuSeeder::class]);
        $this->school = School::factory()->create();
        $this->monday = CarbonImmutable::parse('2026-07-27');
        $this->travelTo(CarbonImmutable::parse('2026-07-01 09:00'));

        $shift = Shift::create(['school_id' => $this->school->id, 'name' => 'Matutino', 'sort_order' => 1, 'is_active' => true]);
        foreach (range(9, 12) as $number => $hour) {
            $this->slots[$hour] = ScheduleSlot::create([
                'school_id' => $this->school->id, 'shift_id' => $shift->id, 'number' => $number + 1,
                'starts_at' => sprintf('%02d:00:00', $hour), 'ends_at' => sprintf('%02d:00:00', $hour + 1), 'is_active' => true,
            ]);
        }
        Classroom::create(['school_id' => $this->school->id, 'name' => '1', 'capacity' => 5, 'is_active' => true]);
        $this->audiovisual = Classroom::create(['school_id' => $this->school->id, 'name' => '9', 'capacity' => 20, 'is_club_room' => true, 'is_active' => true]);
        $this->conversation = Club::create(['school_id' => $this->school->id, 'name' => 'Conversation Club', 'sort_order' => 10, 'is_active' => true,
            'codes' => [['code' => 'C12', 'levels' => [1, 2], 'color' => 1, 'is_active' => true], ['code' => 'C23', 'levels' => [2, 3], 'color' => 2, 'is_active' => true]]]);
    }

    private function user(string $roleSlug, ?School $school = null): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $roleSlug)->value('id'), 'school_id' => ($school ?? $this->school)->id]);
    }

    /** @param array<int, list<int>> $hours */
    private function teacher(string $short, int $number, array $hours): Teacher
    {
        $teacher = Teacher::factory()->create(['school_id' => $this->school->id, 'user_id' => null, 'short_name' => $short, 'panel_number' => $number]);

        foreach ($hours as $weekday => $list) {
            foreach ($list as $hour) {
                TeacherScheduleBlock::create(['school_id' => $this->school->id, 'teacher_id' => $teacher->id, 'weekday' => $weekday, 'schedule_slot_id' => $this->slots[$hour]->id]);
            }
        }

        return $teacher;
    }

    private function schedule(string $date, int $hour, ?Teacher $teacher, string $code = 'C12', string $modality = 'in_person', bool $repeat = false): Testable
    {
        return Livewire::withQueryParams(['mes' => substr($date, 0, 7)])->test(ClubSchedule::class)
            ->call('add', $date, $this->slots[$hour]->id)
            ->set('codeKey', $this->conversation->id.'|'.$code)->set('teacherId', $teacher?->id)->set('modality', $modality)->set('repeat', $repeat)
            ->call('save');
    }

    public function test_a_club_needs_a_teacher_with_that_hour_and_takes_it_from_regular_classes(): void
    {
        $melissa = $this->teacher('MELISSA', 1, [1 => [9, 10]]);
        $dulce = $this->teacher('DULCE', 2, [2 => [9]]);
        app(AvailabilityPlanner::class)->generateWeek($this->school, $this->monday);
        $this->actingAs($this->user(Role::ACADEMIC_COORDINATOR));

        $options = Livewire::withQueryParams(['mes' => '2026-07'])->test(ClubSchedule::class)->call('add', '2026-07-27', $this->slots[9]->id)->get('teacherOptions');
        $this->assertSame([$melissa->id], array_keys($options));

        $this->schedule('2026-07-27', 9, $dulce)->assertHasErrors('teacherId');
        // Sin aula virtual no hay club en línea.
        $this->schedule('2026-07-27', 9, $melissa, 'C12', 'online')->assertHasErrors('slot');
        $virtual = Classroom::create(['school_id' => $this->school->id, 'name' => 'Zoom 1', 'capacity' => 30, 'is_club_room' => true, 'is_virtual' => true, 'is_active' => true]);
        $this->schedule('2026-07-27', 9, $melissa, 'C12', 'online')->assertHasNoErrors();

        $session = ClubSession::sole();
        $this->assertSame([$virtual->id, 'C12', 'online'], [$session->classroom_id, $session->code, $session->modality->value]);

        // En el panel esa hora es de club, con su aula, y no se le cambia el aula ahí.
        $cell = TeacherAvailability::where('teacher_id', $melissa->id)->whereDate('date', '2026-07-27')->where('schedule_slot_id', $this->slots[9]->id)->sole();
        $this->assertSame([$session->id, $virtual->id], [$cell->club_session_id, $cell->classroom_id]);
        Livewire::withQueryParams(['mes' => '2026-07', 'semana' => '2026-07-27'])->test(AvailabilityPanel::class)
            ->call('setClassroom', $cell->id, null)->assertDispatched('toast', type: 'error', message: __('availability.validation.club_hour'));
        $this->assertSame($virtual->id, $cell->fresh()->classroom_id);

        // Al eliminar el club la hora vuelve a ser de clases.
        Livewire::withQueryParams(['mes' => '2026-07'])->test(ClubSchedule::class)->call('edit', $session->id)->call('delete');
        $this->assertNull($cell->fresh()->club_session_id);
    }

    public function test_one_in_person_and_one_or_two_online_clubs_fit_at_the_same_hour_with_different_teachers(): void
    {
        $melissa = $this->teacher('MELISSA', 1, [1 => [9]]);
        $dulce = $this->teacher('DULCE', 2, [1 => [9]]);
        $jesus = $this->teacher('JESUS', 3, [1 => [9]]);
        $ernesto = $this->teacher('ERNESTO', 4, [1 => [9]]);
        foreach (['Zoom 1', 'Zoom 2'] as $name) {
            Classroom::create(['school_id' => $this->school->id, 'name' => $name, 'capacity' => 30, 'is_club_room' => true, 'is_virtual' => true, 'is_active' => true]);
        }
        $this->actingAs($this->user(Role::SCHOOL_ADMIN));

        $this->schedule('2026-07-27', 9, $melissa)->assertHasNoErrors();
        // Solo hay un aula audiovisual física: un segundo presencial no cabe.
        $this->schedule('2026-07-27', 9, $dulce, 'C23')->assertHasErrors('slot');
        $this->schedule('2026-07-27', 9, $dulce, 'C23', 'online')->assertHasNoErrors();
        // El mismo teacher no da dos clubs a la misma hora.
        $this->schedule('2026-07-27', 9, $dulce, 'C12', 'online')->assertHasErrors('teacherId');
        $this->schedule('2026-07-27', 9, $jesus, 'C12', 'online')->assertHasNoErrors();
        // Dos aulas virtuales: un tercer club en línea ya no cabe.
        $this->schedule('2026-07-27', 9, $ernesto, 'C23', 'online')->assertHasErrors('slot');

        $this->assertSame(['in_person' => 1, 'online' => 2], ClubSession::get()->countBy(fn ($session) => $session->modality->value)->all());
    }

    public function test_room_closures_and_holidays_block_clubs(): void
    {
        $melissa = $this->teacher('MELISSA', 1, [1 => [9, 10], 2 => [9]]);
        $this->actingAs($this->user(Role::SCHOOL_ADMIN));

        Livewire::test(ClassroomIndex::class)->call('openClosures', $this->audiovisual->id)
            ->set('closureFrom', '2026-07-27')->set('closureTo', '2026-07-27')->set('closureAllDay', false)
            ->set('closureFromSlot', $this->slots[9]->id)->set('closureToSlot', $this->slots[9]->id)->set('closureReason', 'Mantenimiento')
            ->call('saveClosure')->assertHasNoErrors();

        $this->schedule('2026-07-27', 9, $melissa)->assertHasErrors('slot');
        $this->schedule('2026-07-27', 10, $melissa)->assertHasNoErrors();

        // La reserva aparece en la programación como "Reservada para otra actividad" y queda en el historial.
        $this->get(route('clubs.schedule', ['mes' => '2026-07']))->assertOk()->assertSee('Reservada para otra actividad');
        $this->assertSame(['Mantenimiento'], Livewire::test(ClassroomIndex::class)->call('openClosures', $this->audiovisual->id)->get('closures')->pluck('reason')->all());

        Holiday::create(['school_id' => $this->school->id, 'name' => 'Asueto', 'type' => HolidayType::School, 'starts_on' => '2026-07-28', 'ends_on' => '2026-07-28', 'is_active' => true]);
        $this->schedule('2026-07-28', 9, $melissa)->assertHasErrors('slot');
    }

    public function test_a_covering_teacher_can_give_the_club_and_takes_it_when_the_owner_is_absent(): void
    {
        $melissa = $this->teacher('MELISSA', 1, [1 => [9]]);
        $dulce = $this->teacher('DULCE', 2, [2 => [9]]);
        app(AvailabilityPlanner::class)->generateWeek($this->school, $this->monday);
        $this->actingAs($this->user(Role::ACADEMIC_COORDINATOR));
        $this->schedule('2026-07-27', 9, $melissa)->assertHasNoErrors();

        Livewire::test(TeacherIncidentIndex::class)
            ->call('edit')->set('teacherId', $melissa->id)->set('incidentType', 'vacation')
            ->set('startsOn', '2026-07-27')->set('endsOn', '2026-07-27')->set('allDay', true)->set('reason', 'Vacaciones')->call('save');
        Livewire::test(TeacherIncidentIndex::class)
            ->call('edit')->set('teacherId', $dulce->id)->set('incidentType', 'covers')->set('coveredTeacherId', $melissa->id)
            ->set('startsOn', '2026-07-27')->set('endsOn', '2026-07-27')->set('allDay', true)->set('reason', 'Cubre')->call('save')->assertHasNoErrors();

        $cell = TeacherAvailability::where('teacher_id', $dulce->id)->whereDate('date', '2026-07-27')->where('schedule_slot_id', $this->slots[9]->id)->sole();
        $this->assertSame(ClubSession::sole()->id, $cell->club_session_id);
    }

    public function test_repeat_fills_the_same_day_and_hour_in_the_following_weeks_of_the_month(): void
    {
        $melissa = $this->teacher('MELISSA', 1, [1 => [9]]);
        $this->actingAs($this->user(Role::SCHOOL_ADMIN));

        $this->schedule('2026-07-06', 9, $melissa, repeat: true)->assertHasNoErrors();

        $this->assertSame(['2026-07-06', '2026-07-13', '2026-07-20', '2026-07-27'],
            ClubSession::orderBy('date')->get()->map(fn ($session) => $session->date->toDateString())->all());
    }

    public function test_repeat_also_works_when_editing_a_club(): void
    {
        $melissa = $this->teacher('MELISSA', 1, [1 => [9]]);
        $this->actingAs($this->user(Role::SCHOOL_ADMIN));
        $this->schedule('2026-07-13', 9, $melissa)->assertHasNoErrors();

        Livewire::withQueryParams(['mes' => '2026-07'])->test(ClubSchedule::class)
            ->call('edit', ClubSession::sole()->id)->set('codeKey', $this->conversation->id.'|C23')->set('repeat', true)->call('save')->assertHasNoErrors();

        $this->assertSame([['2026-07-13', 'C23'], ['2026-07-20', 'C23'], ['2026-07-27', 'C23']],
            ClubSession::orderBy('date')->get()->map(fn ($session) => [$session->date->toDateString(), $session->code])->all());
    }

    public function test_summaries_by_teacher_and_by_type_show_this_and_last_month(): void
    {
        $melissa = $this->teacher('MELISSA', 1, [1 => [9, 10]]);
        $this->actingAs($this->user(Role::SCHOOL_ADMIN));
        $this->schedule('2026-06-29', 9, $melissa)->assertHasNoErrors();
        $this->schedule('2026-07-27', 9, $melissa)->assertHasNoErrors();
        $this->schedule('2026-07-27', 10, $melissa, 'C23')->assertHasNoErrors();

        $page = Livewire::withQueryParams(['mes' => '2026-07'])->test(ClubSchedule::class);
        [$july, $june] = $page->viewData('summaries');
        $this->assertSame([2.0, 1.0], [(float) $july['total'], (float) $june['total']]);
        $this->assertSame(['C12' => 1.0, 'C23' => 1.0], array_map('floatval', $july['teachers'][0]['codes']));
        $this->assertEquals(1.0, $page->viewData('codeHours')[$this->conversation->id.'|C12']);
        $page->set('summaryTab', 'type')->assertSee('Resumen por tipo')->assertSee('C23');
    }

    public function test_reception_only_consults_and_schools_are_isolated(): void
    {
        $melissa = $this->teacher('MELISSA', 1, [1 => [9]]);
        $this->actingAs($this->user(Role::SCHOOL_ADMIN));
        $this->schedule('2026-07-27', 9, $melissa)->assertHasNoErrors();

        $this->actingAs($this->user(Role::RECEPTION));
        $this->get(route('clubs.schedule', ['mes' => '2026-07']))->assertOk()->assertSee('C12')->assertSee('MELISSA')->assertSee('Presencial');
        Livewire::test(ClubSchedule::class)->call('add', '2026-07-27', $this->slots[10]->id)->assertForbidden();

        $other = School::factory()->create();
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $other));
        $sessionId = ClubSession::withoutGlobalScope('school')->sole()->id;
        Livewire::test(ClubSchedule::class)->call('edit', $sessionId)->assertNotFound();
    }

    public function test_club_abbreviations_are_captured_in_the_catalog_and_do_not_repeat(): void
    {
        $this->actingAs($this->user(Role::SCHOOL_ADMIN));
        $movie = Club::create(['school_id' => $this->school->id, 'name' => 'Movie Club', 'sort_order' => 20, 'is_active' => true]);

        Livewire::test(ClubsTab::class, ['schoolId' => $this->school->id])->call('edit', $movie->id)
            ->call('addCode')->set('codes.0.code', 'c12')->set('codes.0.levels', ['1'])
            ->call('save')->assertHasErrors('codes.0.code')
            ->set('codes.0.code', 'm12')->set('codes.0.levels', ['1', '2'])->call('save')->assertHasNoErrors();

        $this->assertSame([['code' => 'M12', 'levels' => [1, 2], 'color' => 1, 'is_active' => true]], $movie->fresh()->codeList());
    }
}
