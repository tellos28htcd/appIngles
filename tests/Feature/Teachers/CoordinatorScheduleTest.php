<?php

namespace Tests\Feature\Teachers;

use App\Actions\Teachers\AvailabilityPlanner;
use App\Livewire\Catalogs\CoordinatorActivitiesTab;
use App\Livewire\Teachers\AvailabilityPanel;
use App\Livewire\Teachers\ClubSchedule;
use App\Livewire\Teachers\CoordinatorSchedule;
use App\Models\Classroom;
use App\Models\Club;
use App\Models\CoordinatorActivity;
use App\Models\CoordinatorSession;
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

class CoordinatorScheduleTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    /** @var array<int, ScheduleSlot> */
    private array $slots = [];

    private CoordinatorActivity $review;

    private CoordinatorActivity $meeting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, MenuSeeder::class]);
        $this->school = School::factory()->create();
        $this->travelTo(CarbonImmutable::parse('2026-07-01 09:00'));

        $shift = Shift::create(['school_id' => $this->school->id, 'name' => 'Matutino', 'sort_order' => 1, 'is_active' => true]);
        foreach (range(9, 12) as $number => $hour) {
            $this->slots[$hour] = ScheduleSlot::create([
                'school_id' => $this->school->id, 'shift_id' => $shift->id, 'number' => $number + 1,
                'starts_at' => sprintf('%02d:00:00', $hour), 'ends_at' => sprintf('%02d:00:00', $hour + 1), 'is_active' => true,
            ]);
        }
        $this->review = CoordinatorActivity::create(['school_id' => $this->school->id, 'name' => 'Revisión de Planeaciones', 'authorized_hours' => 2,
            'uses_teacher_hour' => true, 'panel_mark' => 'R', 'sort_order' => 10, 'is_active' => true]);
        $this->meeting = CoordinatorActivity::create(['school_id' => $this->school->id, 'name' => 'Junta mensual', 'authorized_hours' => 1,
            'sort_order' => 20, 'is_active' => true]);
    }

    private function user(string $roleSlug, ?School $school = null): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $roleSlug)->value('id'), 'school_id' => ($school ?? $this->school)->id]);
    }

    /** @param array<int, list<int>> $hours */
    private function teacher(string $short, int $number, array $hours): Teacher
    {
        $teacher = Teacher::factory()->create(['school_id' => $this->school->id, 'user_id' => null, 'first_name' => ucfirst(strtolower($short)), 'short_name' => $short, 'panel_number' => $number]);

        foreach ($hours as $weekday => $list) {
            foreach ($list as $hour) {
                TeacherScheduleBlock::create(['school_id' => $this->school->id, 'teacher_id' => $teacher->id, 'weekday' => $weekday, 'schedule_slot_id' => $this->slots[$hour]->id]);
            }
        }

        return $teacher;
    }

    private function program(string $date, int $hour, CoordinatorActivity $activity, ?Teacher $teacher = null, bool $repeat = false): Testable
    {
        return Livewire::withQueryParams(['mes' => substr($date, 0, 7)])->test(CoordinatorSchedule::class)
            ->call('add', $date, $this->slots[$hour]->id)->set('activityId', $activity->id)
            ->set('teacherKey', $teacher ? (string) $teacher->id : CoordinatorSchedule::COORDINATION)
            ->set('repeat', $repeat)->call('save');
    }

    public function test_plan_review_takes_the_teacher_hour_marks_r_and_blocks_clubs(): void
    {
        $melissa = $this->teacher('MELISSA', 1, [1 => [9, 10]]);
        $dulce = $this->teacher('DULCE', 2, [2 => [9]]);
        app(AvailabilityPlanner::class)->generateWeek($this->school, CarbonImmutable::parse('2026-07-27'));
        $this->actingAs($this->user(Role::ACADEMIC_COORDINATOR));

        $options = Livewire::withQueryParams(['mes' => '2026-07'])->test(CoordinatorSchedule::class)
            ->call('add', '2026-07-27', $this->slots[9]->id)->set('activityId', $this->review->id)->get('teacherOptions');
        $this->assertSame([$melissa->id], array_keys($options));

        $this->program('2026-07-27', 9, $this->review, $dulce)->assertHasErrors('teacherKey');
        $this->program('2026-07-27', 9, $this->review, $melissa)->assertHasNoErrors();

        // En el panel: "R" blanca sobre negro, sin aula.
        $cell = TeacherAvailability::where('teacher_id', $melissa->id)->whereDate('date', '2026-07-27')->where('schedule_slot_id', $this->slots[9]->id)->sole();
        $this->assertSame([CoordinatorSession::sole()->id, null], [$cell->coordinator_session_id, $cell->classroom_id]);
        $this->actingAs($this->user(Role::SCHOOL_ADMIN));
        Livewire::withQueryParams(['mes' => '2026-07', 'semana' => '2026-07-27'])->test(AvailabilityPanel::class)
            ->assertSeeHtml('bg-ink-900 text-sm font-extrabold text-white');

        // Esa hora ya no sirve para clubs.
        Classroom::create(['school_id' => $this->school->id, 'name' => '7', 'capacity' => 20, 'is_club_room' => true, 'is_active' => true]);
        Club::create(['school_id' => $this->school->id, 'name' => 'Conversation Club', 'sort_order' => 1, 'is_active' => true,
            'codes' => [['code' => 'C12', 'levels' => [1, 2], 'color' => 1, 'is_active' => true]]]);
        $clubTeachers = Livewire::withQueryParams(['mes' => '2026-07'])->test(ClubSchedule::class)->call('add', '2026-07-27', $this->slots[9]->id)->get('teacherOptions');
        $this->assertArrayNotHasKey($melissa->id, $clubTeachers);
    }

    public function test_other_activities_use_coordination_without_teacher_and_do_not_touch_the_panel(): void
    {
        $this->actingAs($this->user(Role::ACADEMIC_COORDINATOR));

        $component = Livewire::withQueryParams(['mes' => '2026-07'])->test(CoordinatorSchedule::class)
            ->call('add', '2026-07-27', $this->slots[10]->id)->set('activityId', $this->meeting->id);
        $this->assertSame([CoordinatorSchedule::COORDINATION], array_keys($component->get('teacherOptions')));
        $component->assertSet('teacherKey', CoordinatorSchedule::COORDINATION)->call('save')->assertHasNoErrors();

        $session = CoordinatorSession::sole();
        $this->assertNull($session->teacher_id);
        $this->assertSame(0, TeacherAvailability::whereNotNull('coordinator_session_id')->count());

        // Una actividad por hora.
        $this->program('2026-07-27', 10, $this->review, $this->teacher('MELISSA', 1, [1 => [10]]))->assertHasErrors('slot');
    }

    public function test_session_evaluation_picks_a_working_teacher_without_taking_the_hour(): void
    {
        $evaluation = CoordinatorActivity::create(['school_id' => $this->school->id, 'name' => 'Evaluación de Sesión', 'authorized_hours' => 2,
            'requires_teacher' => true, 'sort_order' => 15, 'is_active' => true]);
        $melissa = $this->teacher('MELISSA', 1, [1 => [9]]);
        $dulce = $this->teacher('DULCE', 2, [2 => [9]]);
        app(AvailabilityPlanner::class)->generateWeek($this->school, CarbonImmutable::parse('2026-07-27'));
        $this->actingAs($this->user(Role::ACADEMIC_COORDINATOR));

        $component = Livewire::withQueryParams(['mes' => '2026-07'])->test(CoordinatorSchedule::class)
            ->call('add', '2026-07-27', $this->slots[9]->id)->set('activityId', $evaluation->id);
        $this->assertSame([$melissa->id], array_keys($component->get('teacherOptions')));
        $component->assertSet('teacherKey', '');

        $this->program('2026-07-27', 9, $evaluation, $dulce)->assertHasErrors('teacherKey');
        $this->program('2026-07-27', 9, $evaluation, $melissa)->assertHasNoErrors();

        // Se guarda con el teacher, pero su hora sigue siendo de clase (sin "R" ni bloqueo).
        $this->assertSame($melissa->id, CoordinatorSession::sole()->teacher_id);
        $cell = TeacherAvailability::where('teacher_id', $melissa->id)->whereDate('date', '2026-07-27')->where('schedule_slot_id', $this->slots[9]->id)->sole();
        $this->assertNull($cell->coordinator_session_id);
        $this->assertContains($melissa->id, app(AvailabilityPlanner::class)->availableTeacherIds($this->school, CarbonImmutable::parse('2026-07-27'), $this->slots[9]));
    }

    public function test_club_evaluation_has_two_hours_per_teacher_and_a_row_per_teacher(): void
    {
        $evaluation = CoordinatorActivity::create(['school_id' => $this->school->id, 'name' => 'Evaluación de Club', 'authorized_hours' => 2,
            'per_teacher' => true, 'requires_teacher' => true, 'table_column' => 1, 'sort_order' => 3, 'is_active' => true]);
        $melissa = $this->teacher('MELISSA', 1, [1 => [9, 10, 11], 2 => [9]]);
        $dulce = $this->teacher('DULCE', 2, [1 => [11], 2 => [10]]);
        $this->actingAs($this->user(Role::ACADEMIC_COORDINATOR));

        $this->program('2026-07-27', 9, $evaluation, $melissa)->assertHasNoErrors();
        $this->program('2026-07-27', 10, $evaluation, $melissa)->assertHasNoErrors();
        // Melissa ya tiene sus 2 h; Dulce tiene las suyas aparte.
        $this->program('2026-07-28', 9, $evaluation, $melissa)->assertHasErrors('activityId');
        $this->program('2026-07-28', 10, $evaluation, $dulce)->assertHasNoErrors();

        $columns = Livewire::withQueryParams(['mes' => '2026-07'])->test(CoordinatorSchedule::class)->viewData('columns');
        $left = collect($columns[1])->filter()->mapWithKeys(fn ($row) => [$row['label'] => [$row['authorized'], $row['programmed']]])->all();
        $this->assertSame(['Evaluación de Club T. Melissa' => [2.0, 2.0], 'Evaluación de Club T. Dulce' => [2.0, 1.0]], $left);
    }

    public function test_authorized_hours_cannot_be_exceeded_and_totals_are_shown(): void
    {
        $this->actingAs($this->user(Role::SCHOOL_ADMIN));

        $this->program('2026-07-06', 9, $this->meeting)->assertHasNoErrors();
        $this->program('2026-07-07', 9, $this->meeting)->assertHasErrors('activityId');

        $page = Livewire::withQueryParams(['mes' => '2026-07'])->test(CoordinatorSchedule::class);
        $this->assertEquals([3.0, 1.0], [$page->viewData('totalAuthorized'), $page->viewData('totalProgrammed')]);
        $this->assertFalse($page->viewData('allMatch'));
        $page->assertSee('Junta mensual')->assertSee('Aún no coincide');
    }

    public function test_repeat_is_remembered_and_summaries_start_collapsed(): void
    {
        $melissa = $this->teacher('MELISSA', 1, [1 => [9]]);
        $this->actingAs($this->user(Role::SCHOOL_ADMIN));

        // HA = 2: de las 4 semanas solo caben 2; las demás se avisan.
        $this->program('2026-07-06', 9, $this->review, $melissa, repeat: true)->assertDispatched('toast', type: 'warning');
        $this->assertSame(['2026-07-06', '2026-07-13'], CoordinatorSession::orderBy('date')->get()->map(fn ($session) => $session->date->toDateString())->all());

        $page = Livewire::withQueryParams(['mes' => '2026-07'])->test(CoordinatorSchedule::class);
        $page->call('edit', CoordinatorSession::orderBy('date')->first()->id)->assertSet('repeat', true);
        $this->assertSame([], $page->viewData('summaries'));
        [$july] = $page->call('toggleSummary', 'activity')->viewData('summaries');
        $this->assertEquals([['name' => 'Revisión de Planeaciones', 'order' => 10, 'hours' => 2.0]], $july['activities']);
    }

    public function test_only_coordinator_and_admin_use_it_and_schools_are_isolated(): void
    {
        $this->actingAs($this->user(Role::SCHOOL_ADMIN));
        $this->program('2026-07-06', 9, $this->meeting)->assertHasNoErrors();

        $this->actingAs($this->user(Role::RECEPTION));
        $this->get(route('coordinator.schedule'))->assertForbidden();

        $this->actingAs($this->user(Role::SCHOOL_ADMIN, School::factory()->create()));
        Livewire::test(CoordinatorSchedule::class)->call('edit', CoordinatorSession::withoutGlobalScope('school')->sole()->id)->assertNotFound();
    }

    public function test_activities_catalog_keeps_authorized_hours_and_the_panel_mark(): void
    {
        $this->actingAs($this->user(Role::SCHOOL_ADMIN));

        Livewire::test(CoordinatorActivitiesTab::class, ['schoolId' => $this->school->id])->call('edit')
            ->set('name', 'Revisión de WP')->set('authorizedHours', 4)->set('usesTeacherHour', true)->call('save')->assertHasErrors('panelMark')
            ->set('panelMark', 'w')->call('save')->assertHasNoErrors();

        $activity = CoordinatorActivity::where('name', 'Revisión de WP')->sole();
        $this->assertSame([4.0, true, 'W'], [$activity->authorized_hours, $activity->uses_teacher_hour, $activity->panel_mark]);
    }
}
