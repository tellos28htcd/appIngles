<?php

namespace Tests\Feature\Agenda;

use App\Actions\Agenda\AgendaService;
use App\Actions\Teachers\AvailabilityPlanner;
use App\Enums\AttendanceEffect;
use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Enums\LessonType;
use App\Enums\SelfBooking;
use App\Livewire\Agenda\AgendaDay;
use App\Livewire\Agenda\MyAgenda;
use App\Models\Activity;
use App\Models\Book;
use App\Models\Booking;
use App\Models\CancellationReason;
use App\Models\Classroom;
use App\Models\Club;
use App\Models\ClubSession;
use App\Models\CoordinatorActivity;
use App\Models\CoordinatorSession;
use App\Models\Lesson;
use App\Models\Role;
use App\Models\ScheduleSlot;
use App\Models\School;
use App\Models\Shift;
use App\Models\Student;
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

class AgendaTest extends TestCase
{
    use RefreshDatabase;

    private const MONDAY = '2026-07-27';

    private School $school;

    /** @var array<int, ScheduleSlot> */
    private array $slots = [];

    /** @var list<Classroom> */
    private array $rooms = [];

    private Lesson $firstLesson;

    /** @var array<string, CancellationReason> */
    private array $reasons = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, MenuSeeder::class]);
        $this->travelTo(CarbonImmutable::parse('2026-07-26 09:00', 'America/Mexico_City'));
        $this->school = School::factory()->create([
            'timezone' => 'America/Mexico_City', 'session_capacity' => 2, 'self_booking' => SelfBooking::AnyTime,
            'books_without_classroom' => false, 'max_sessions' => null, 'max_clubs' => null, 'club_min_lesson_number' => null,
        ]);

        $shift = Shift::create(['school_id' => $this->school->id, 'name' => 'Matutino', 'sort_order' => 1, 'is_active' => true]);
        foreach ([9, 10] as $number => $hour) {
            $this->slots[$hour] = ScheduleSlot::create([
                'school_id' => $this->school->id, 'shift_id' => $shift->id, 'number' => $number + 1,
                'starts_at' => sprintf('%02d:00:00', $hour), 'ends_at' => sprintf('%02d:00:00', $hour + 1), 'is_active' => true,
            ]);
        }
        $this->rooms = [
            Classroom::create(['school_id' => $this->school->id, 'name' => '1', 'capacity' => 5, 'is_active' => true]),
            Classroom::create(['school_id' => $this->school->id, 'name' => '2', 'capacity' => 5, 'is_active' => true]),
        ];

        $book = Book::create(['school_id' => $this->school->id, 'level' => 1, 'name' => 'Book 1', 'is_active' => true]);
        $this->firstLesson = Lesson::create(['school_id' => $this->school->id, 'book_id' => $book->id, 'number' => 1, 'name' => 'Lesson 1', 'type' => LessonType::Lesson, 'sort_order' => 1, 'is_active' => true]);
        Lesson::create(['school_id' => $this->school->id, 'book_id' => $book->id, 'number' => 2, 'name' => 'Lesson 2', 'type' => LessonType::Lesson, 'sort_order' => 2, 'is_active' => true]);

        foreach (['no_show' => ['No se presentó', AttendanceEffect::Absent], 'notice' => ['Aviso para cancelar', AttendanceEffect::None]] as $key => [$name, $effect]) {
            $this->reasons[$key] = CancellationReason::create(['school_id' => $this->school->id, 'name' => $name, 'attendance' => $effect, 'sort_order' => count($this->reasons) + 1, 'is_active' => true]);
        }

        $melissa = $this->teacher('MELISSA', 1);
        $dulce = $this->teacher('DULCE', 2);
        app(AvailabilityPlanner::class)->generateWeek($this->school, CarbonImmutable::parse(self::MONDAY));
        TeacherAvailability::where('teacher_id', $melissa->id)->update(['classroom_id' => $this->rooms[0]->id]);
        TeacherAvailability::where('teacher_id', $dulce->id)->update(['classroom_id' => $this->rooms[1]->id]);
    }

    private function teacher(string $short, int $number): Teacher
    {
        $teacher = Teacher::factory()->create(['school_id' => $this->school->id, 'user_id' => null, 'short_name' => $short, 'panel_number' => $number]);
        foreach ($this->slots as $slot) {
            TeacherScheduleBlock::create(['school_id' => $this->school->id, 'teacher_id' => $teacher->id, 'weekday' => 1, 'schedule_slot_id' => $slot->id]);
        }

        return $teacher;
    }

    private function user(string $roleSlug, ?School $school = null): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $roleSlug)->value('id'), 'school_id' => ($school ?? $this->school)->id]);
    }

    private function student(?User $user = null): Student
    {
        return Student::factory()->create(['school_id' => $this->school->id, 'user_id' => $user?->id]);
    }

    private function receptionBooks(Student $student, int $hour, string $kind = 'session', string $modality = 'in_person', ?int $clubId = null): Testable
    {
        return Livewire::test(AgendaDay::class)->set('date', self::MONDAY)
            ->call('create')->call('pickStudent', $student->id)
            ->set('option', $this->slots[$hour]->id."|$kind|$modality|".$clubId)->call('book');
    }

    public function test_reception_books_in_a_random_room_with_space_and_then_the_waitlist(): void
    {
        $this->actingAs($this->user(Role::RECEPTION));
        $students = Student::factory()->count(5)->create(['school_id' => $this->school->id]);

        foreach ($students->take(4) as $student) {
            $this->receptionBooks($student, 9)->assertDispatched('toast', type: 'success');
        }
        // Cupo por sesión 2 en cada una de las 2 aulas: se llenan las dos.
        $this->assertEqualsCanonicalizing([$this->rooms[0]->id, $this->rooms[0]->id, $this->rooms[1]->id, $this->rooms[1]->id], Booking::pluck('classroom_id')->all());

        // Llenas y sin "Agendar en lista de espera": no hay lugar.
        $this->receptionBooks($students[4], 9)->assertDispatched('toast', type: 'error', message: __('agenda.validation.full'));

        $this->school->update(['books_without_classroom' => true]);
        $this->receptionBooks($students[4], 9)->assertDispatched('toast', type: 'warning');
        $waiting = Booking::with('classroom')->where('student_id', $students[4]->id)->sole();
        $this->assertTrue($waiting->is_waitlist);
        $this->assertSame(__('agenda.waitlist'), $waiting->placeLabel());

        // Se libera un lugar: se propone al de la lista de espera y pasa a esa aula.
        $freed = Booking::where('student_id', $students[0]->id)->sole();
        Livewire::test(AgendaDay::class)->set('date', self::MONDAY)
            ->call('confirmCancel', $freed->id)->assertSet('reasonId', $this->reasons['notice']->id)->set('reasonId', $this->reasons['no_show']->id)->call('cancel')
            ->assertSet('cancellingId', null);
        $this->assertSame([$waiting->id], app(AgendaService::class)->waitlistProposals($this->school, CarbonImmutable::parse(self::MONDAY))->modelKeys());

        Livewire::test(AgendaDay::class)->set('date', self::MONDAY)->call('promote', $waiting->id)->assertDispatched('toast', type: 'success');
        $waiting->refresh();
        $this->assertFalse($waiting->is_waitlist);
        $this->assertSame($freed->classroom_id, $waiting->classroom_id);
        $this->assertSame(['waitlisted', 'promoted'], $waiting->events()->pluck('event')->all());
        $this->assertSame(['booked', 'cancelled'], $freed->events()->pluck('event')->all());
    }

    public function test_the_week_calendar_shows_free_places_by_day_and_hour(): void
    {
        $this->school->update(['virtual_rooms_per_slot' => 2, 'virtual_room_capacity' => 3]);
        Classroom::create(['school_id' => $this->school->id, 'name' => '10', 'capacity' => 8, 'is_virtual' => true, 'is_active' => true]);
        Classroom::create(['school_id' => $this->school->id, 'name' => '11', 'capacity' => 8, 'is_virtual' => true, 'is_active' => true]);
        $this->actingAs($this->user(Role::RECEPTION));
        $this->receptionBooks($this->student(), 9)->assertDispatched('toast', type: 'success');

        $days = app(AvailabilityPlanner::class)->days($this->school, CarbonImmutable::parse(self::MONDAY));
        $week = app(AgendaService::class)->weekAvailability($this->school, $days);
        $nine = $week[self::MONDAY.'|'.$this->slots[9]->id];

        // 2 aulas × cupo 2 = 4 lugares (1 ocupado); en línea 2 aulas × 3 = 6.
        $this->assertSame(['free' => 3, 'capacity' => 4, 'in_person' => 1, 'online' => 0], $nine['session']);
        $this->assertSame(['free' => 6, 'capacity' => 6], $nine['online']);
        $this->assertSame(1, $nine['booked']);
        $this->assertSame(['free' => 4, 'capacity' => 4, 'in_person' => 0, 'online' => 0], $week[self::MONDAY.'|'.$this->slots[10]->id]['session']);
        $this->assertArrayNotHasKey('2026-07-28|'.$this->slots[9]->id, $week);

        // El calendario lo muestra y la celda abre la reservación de esa hora.
        Livewire::test(AgendaDay::class)->set('date', self::MONDAY)
            ->assertSee('3/4')->assertSee('6/6')
            ->call('toggleCalendar')->assertSet('calendarCollapsed', true)->assertDontSee('3/4')
            ->call('toggleCalendar')->assertSee('3/4')
            ->call('openSlot', self::MONDAY, $this->slots[10]->id)->assertSet('creating', true)->assertSet('slotId', $this->slots[10]->id);
    }

    public function test_each_hour_takes_the_next_activity_and_the_same_hour_cannot_be_booked_twice(): void
    {
        $this->actingAs($this->user(Role::RECEPTION));
        $student = $this->student();

        $this->receptionBooks($student, 9)->assertDispatched('toast', type: 'success');
        $this->receptionBooks($student, 9)->assertDispatched('toast', type: 'error', message: __('agenda.validation.duplicate'));
        $this->receptionBooks($student, 10)->assertDispatched('toast', type: 'success');

        $this->assertSame(['L1-L1-A1', 'L1-L1-A2'], Booking::orderBy('id')->with(['book', 'lesson'])->get()->map->activityLabel()->all());
        $this->assertSame($this->firstLesson->id, Booking::first()->lesson_id);
    }

    public function test_low_attendance_in_the_last_week_limits_the_student_to_one_hour_a_day(): void
    {
        $this->actingAs($this->user(Role::RECEPTION));
        $student = $this->student();
        $slot = $this->slots[9]->id;

        // 1 asistencia y 2 faltas en los últimos 7 días: índice 0.33 → 1 hora por día.
        foreach (['2026-07-20' => 'attended', '2026-07-21' => 'no_show', '2026-07-22' => 'no_show'] as $date => $result) {
            Booking::create(['school_id' => $this->school->id, 'student_id' => $student->id, 'date' => $date, 'schedule_slot_id' => $slot,
                'kind' => 'session', 'modality' => 'in_person',
                'status' => $result === 'attended' ? BookingStatus::Attended : BookingStatus::Cancelled,
                'cancellation_reason_id' => $result === 'attended' ? null : $this->reasons['no_show']->id]);
        }

        $this->receptionBooks($student, 9)->assertDispatched('toast', type: 'success');
        $this->receptionBooks($student, 10)->assertDispatched('toast', type: 'error', message: __('agenda.validation.daily_limit', ['hours' => 1]));
    }

    public function test_reception_marks_attendance(): void
    {
        $this->actingAs($this->user(Role::RECEPTION));
        $this->receptionBooks($this->student(), 9);
        $booking = Booking::with('student')->sole();

        Livewire::test(AgendaDay::class)->set('date', self::MONDAY)
            ->assertSee($booking->student->fullName())->assertSee('L1-L1-A1')
            ->call('markAttended', $booking->id)->assertDispatched('toast', type: 'success');

        $this->assertSame(BookingStatus::Attended, $booking->refresh()->status);

        // Con asistencia ya no se puede cambiar (no aparece "Cambiar" y la acción se rechaza).
        Livewire::test(AgendaDay::class)->set('date', self::MONDAY)->assertDontSeeHtml('editBooking('.$booking->id.')')
            ->call('editBooking', $booking->id)->assertStatus(422);
    }

    public function test_teachers_see_the_agenda_but_cannot_book(): void
    {
        $this->actingAs($this->user(Role::TEACHER));

        Livewire::test(AgendaDay::class)->assertOk()->call('create')->assertForbidden();
    }

    public function test_the_student_books_according_to_self_booking_and_cancels_only_their_own(): void
    {
        $user = $this->user(Role::STUDENT);
        $student = $this->student($user);
        $this->actingAs($user);
        $option = $this->slots[9]->id.'|session|in_person|';

        $this->school->update(['self_booking' => SelfBooking::Disabled]);
        Livewire::test(MyAgenda::class)->call('shiftWeek', 1)->call('openSlot', self::MONDAY, $this->slots[9]->id)->set('option', $option)->call('book')
            ->assertDispatched('toast', type: 'error', message: __('agenda.validation.self_disabled'));

        // "Día anterior": hoy (domingo) puede reservar el lunes.
        $this->school->update(['self_booking' => SelfBooking::DayBefore]);
        Livewire::test(MyAgenda::class)->call('shiftWeek', 1)->call('openSlot', self::MONDAY, $this->slots[9]->id)->set('option', $option)->call('book')
            ->assertDispatched('toast', type: 'success');
        $mine = Booking::with('classroom')->sole();
        $this->assertFalse($mine->booked_by_reception);

        // No cancela la de otro alumno; la suya sí (con "Aviso para cancelar").
        $other = Booking::create(['school_id' => $this->school->id, 'student_id' => $this->student()->id, 'date' => self::MONDAY,
            'schedule_slot_id' => $this->slots[10]->id, 'kind' => 'session', 'modality' => 'in_person', 'status' => BookingStatus::Booked]);
        Livewire::test(MyAgenda::class)->call('cancel', $other->id)->assertForbidden();
        Livewire::test(MyAgenda::class)->assertSee(__('agenda.room', ['name' => $mine->classroom->name]))->call('cancel', $mine->id)->assertDispatched('toast', type: 'success');
        $this->assertSame($this->reasons['notice']->id, $mine->refresh()->cancellation_reason_id);
    }

    public function test_the_student_cannot_book_the_same_day_with_day_before(): void
    {
        $this->travelTo(CarbonImmutable::parse(self::MONDAY.' 06:00', 'America/Mexico_City'));
        $this->school->update(['self_booking' => SelfBooking::DayBefore]);
        $user = $this->user(Role::STUDENT);
        $this->student($user);
        $this->actingAs($user);

        Livewire::test(MyAgenda::class)->call('openSlot', self::MONDAY, $this->slots[9]->id)->set('option', $this->slots[9]->id.'|session|in_person|')->call('book')
            ->assertDispatched('toast', type: 'error', message: __('agenda.validation.day_before'));
    }

    public function test_clubs_use_the_club_capacity_and_the_code_level(): void
    {
        $this->school->update(['club_capacity' => 1]);
        $club = Club::create(['school_id' => $this->school->id, 'name' => 'Conversation Club', 'sort_order' => 10, 'is_active' => true,
            'codes' => [['code' => 'C12', 'levels' => [1, 2], 'color' => 1, 'is_active' => true], ['code' => 'C34', 'levels' => [3, 4], 'color' => 2, 'is_active' => true]]]);
        $teacher = Teacher::where('short_name', 'MELISSA')->sole();
        $level1 = ClubSession::create(['school_id' => $this->school->id, 'club_id' => $club->id, 'code' => 'C12', 'teacher_id' => $teacher->id,
            'classroom_id' => $this->rooms[0]->id, 'date' => self::MONDAY, 'schedule_slot_id' => $this->slots[10]->id, 'modality' => 'in_person']);
        $level3 = ClubSession::create(['school_id' => $this->school->id, 'club_id' => $club->id, 'code' => 'C34', 'teacher_id' => Teacher::where('short_name', 'DULCE')->value('id'),
            'classroom_id' => $this->rooms[1]->id, 'date' => self::MONDAY, 'schedule_slot_id' => $this->slots[10]->id, 'modality' => 'in_person']);
        $this->actingAs($this->user(Role::RECEPTION));
        [$ana, $luis] = [$this->student(), $this->student()];

        $clubs = collect(Livewire::test(AgendaDay::class)->set('date', self::MONDAY)->call('create')->call('pickStudent', $ana->id)->get('options'))
            ->flatMap(fn (array $hour) => $hour['options'])->pluck('club_session_id')->filter()->values()->all();
        $this->assertSame([$level1->id], $clubs);

        $this->receptionBooks($ana, 10, 'club', 'in_person', $level1->id)->assertDispatched('toast', type: 'success');
        $this->receptionBooks($luis, 10, 'club', 'in_person', $level1->id)->assertDispatched('toast', type: 'error', message: __('agenda.validation.full'));
        $this->receptionBooks($luis, 10, 'club', 'in_person', $level3->id)->assertDispatched('toast', type: 'error', message: __('agenda.validation.club_not_allowed'));
        $this->assertSame('C12', Booking::with('clubSession')->sole()->activityLabel());
    }

    public function test_online_practice_fills_the_virtual_rooms_and_then_joins_a_class_as_hybrid(): void
    {
        $this->school->update(['virtual_rooms_per_slot' => 1, 'virtual_room_capacity' => 1, 'session_capacity' => 2]);
        Classroom::create(['school_id' => $this->school->id, 'name' => '10', 'capacity' => 8, 'is_virtual' => true, 'is_active' => true]);
        Classroom::create(['school_id' => $this->school->id, 'name' => '11', 'capacity' => 8, 'is_virtual' => true, 'is_active' => true]);
        $this->actingAs($this->user(Role::RECEPTION));

        // 1.º en línea: aula virtual. Llena: los siguientes en línea entran a una clase presencial (híbrida).
        $this->receptionBooks($this->student(), 9, 'session', 'online')->assertDispatched('toast', type: 'success');
        $this->receptionBooks($this->student(), 9, 'session', 'online')->assertDispatched('toast', type: 'success');
        [$virtual, $hybrid] = Booking::with('classroom')->orderBy('id')->get()->all();
        $this->assertSame(__('agenda.virtual_classroom', ['name' => '10']), $virtual->placeLabel());
        // El aula virtual no está en el panel: la atiende un teacher que da clase presencial a esa hora.
        $this->assertContains($virtual->teacher_id, Teacher::whereIn('short_name', ['MELISSA', 'DULCE'])->pluck('id')->all());
        $this->assertNotNull($hybrid->teacher_availability_id);
        $this->assertSame(__('agenda.hybrid_room', ['name' => $hybrid->classroom->name]), $hybrid->placeLabel());

        // El híbrido ocupa un lugar del aula: 2 aulas × 2 = 4, quedan 3; el calendario cuenta por modalidad.
        foreach (range(1, 3) as $i) {
            $this->receptionBooks($this->student(), 9)->assertDispatched('toast', type: 'success');
        }
        $this->receptionBooks($this->student(), 9)->assertDispatched('toast', type: 'error', message: __('agenda.validation.full'));
        $this->receptionBooks($this->student(), 9, 'session', 'online')->assertDispatched('toast', type: 'error', message: __('agenda.validation.full'));

        $days = app(AvailabilityPlanner::class)->days($this->school, CarbonImmutable::parse(self::MONDAY));
        $nine = app(AgendaService::class)->weekAvailability($this->school, $days)[self::MONDAY.'|'.$this->slots[9]->id];
        $this->assertSame(['free' => 0, 'capacity' => 4, 'in_person' => 3, 'online' => 2], $nine['session']);
        $this->assertSame(['free' => 0, 'capacity' => 1], $nine['online']);
    }

    public function test_without_virtual_classrooms_online_practice_joins_a_class_as_hybrid(): void
    {
        // Sin aulas virtuales en el catálogo de Aulas.
        $this->actingAs($this->user(Role::RECEPTION));
        $student = $this->student();

        $modalities = collect(Livewire::test(AgendaDay::class)->set('date', self::MONDAY)->call('openSlot', self::MONDAY, $this->slots[9]->id)
            ->call('pickStudent', $student->id)->get('options')[0]['options'])->where('kind', 'session')->pluck('modality')->all();
        $this->assertSame(['in_person', 'online'], $modalities);

        $this->receptionBooks($student, 9, 'session', 'online')->assertDispatched('toast', type: 'success');
        $booking = Booking::with('classroom')->sole();
        $this->assertNull($booking->virtual_room);
        $this->assertSame(__('agenda.hybrid_room', ['name' => $booking->classroom->name]), $booking->placeLabel());
    }

    public function test_reception_has_ten_minutes_of_tolerance_and_the_student_none(): void
    {
        $this->travelTo(CarbonImmutable::parse(self::MONDAY.' 09:05', 'America/Mexico_City'));
        $user = $this->user(Role::STUDENT);
        $student = $this->student($user);

        $this->actingAs($user);
        Livewire::test(MyAgenda::class)->call('openSlot', self::MONDAY, $this->slots[9]->id)->set('option', $this->slots[9]->id.'|session|in_person|')->call('book')
            ->assertDispatched('toast', type: 'error', message: __('agenda.validation.past'));

        $this->actingAs($this->user(Role::RECEPTION));
        $this->receptionBooks($student, 9)->assertDispatched('toast', type: 'success');

        $this->travelTo(CarbonImmutable::parse(self::MONDAY.' 09:11', 'America/Mexico_City'));
        $this->receptionBooks($this->student(), 9)->assertDispatched('toast', type: 'error', message: __('agenda.validation.past'));
    }

    public function test_another_school_cannot_see_or_change_the_bookings(): void
    {
        $this->actingAs($this->user(Role::RECEPTION));
        $this->receptionBooks($this->student(), 9);
        $booking = Booking::with('student')->sole();

        $other = School::factory()->create();
        $this->actingAs($this->user(Role::RECEPTION, $other));

        Livewire::test(AgendaDay::class)->set('date', self::MONDAY)->assertDontSee($booking->student->fullName());
        Livewire::test(AgendaDay::class)->set('date', self::MONDAY)->call('markAttended', $booking->id)->assertNotFound();
        $this->assertSame(BookingStatus::Booked, $booking->refresh()->status);
    }

    public function test_the_day_list_filters_by_hour_room_and_teacher(): void
    {
        $this->actingAs($this->user(Role::RECEPTION));
        [$ana, $luis] = [$this->student(), $this->student()];
        $this->receptionBooks($ana, 9);
        $this->receptionBooks($luis, 10);

        Livewire::test(AgendaDay::class)->set('date', self::MONDAY)
            ->assertSee($ana->fullName())->assertSee($luis->fullName())
            ->set('filterSlot', (string) $this->slots[10]->id)->assertDontSee($ana->fullName())->assertSee($luis->fullName())
            ->call('clearFilters')->set('filterTeacher', (string) Booking::where('student_id', $ana->id)->value('teacher_id'))->assertSee($ana->fullName());
    }

    public function test_reception_moves_a_booking_to_an_available_room_and_its_teacher_comes_with_it(): void
    {
        $this->actingAs($this->user(Role::RECEPTION));
        [$ana, $luis] = [$this->student(), $this->student()];
        $this->receptionBooks($ana, 9);
        $booking = Booking::sole();
        $here = collect($this->rooms)->firstWhere('id', $booking->classroom_id);
        $other = collect($this->rooms)->first(fn (Classroom $room) => $room->id !== $here->id);
        $otherTeacher = TeacherAvailability::where('classroom_id', $other->id)->where('schedule_slot_id', $this->slots[9]->id)->value('teacher_id');
        $page = fn () => Livewire::test(AgendaDay::class)->set('date', self::MONDAY);

        // Solo aulas disponibles a esa hora (la actual y la otra del panel; no el aula sin teacher ni la virtual inexistente).
        Classroom::create(['school_id' => $this->school->id, 'name' => '99', 'capacity' => 5, 'is_active' => true]);
        $rooms = $page()->call('editBooking', $booking->id)->viewData('edit')['choices'];
        $this->assertEqualsCanonicalizing([$here->id, $other->id], array_keys($rooms));

        // Al cambiar el aula, el teacher es el de esa aula en el panel.
        $page()->call('editBooking', $booking->id)->set('editRoomId', $other->id)->assertSee(Teacher::find($otherTeacher)->panelName())
            ->call('saveChange')->assertDispatched('toast', type: 'success')->assertSet('editingId', null);
        $booking->refresh();
        $this->assertSame([$other->id, $otherTeacher], [$booking->classroom_id, $booking->teacher_id]);

        // Un aula llena ya no aparece (cupo por sesión 2).
        foreach ([$luis, $this->student()] as $student) {
            Booking::create(['school_id' => $this->school->id, 'student_id' => $student->id, 'date' => self::MONDAY, 'schedule_slot_id' => $this->slots[9]->id,
                'kind' => 'session', 'modality' => 'in_person', 'status' => BookingStatus::Booked, 'classroom_id' => $here->id,
                'teacher_availability_id' => TeacherAvailability::where('classroom_id', $here->id)->where('schedule_slot_id', $this->slots[9]->id)->value('id')]);
        }
        $this->assertArrayNotHasKey($here->id, $page()->call('editBooking', $booking->id)->viewData('edit')['choices']);
        $page()->call('editBooking', $booking->id)->set('editRoomId', $here->id)->call('saveChange')
            ->assertDispatched('toast', type: 'error', message: __('agenda.validation.room_full', ['room' => $here->name]));

        $this->assertSame(['booked', 'changed'], $booking->events()->pluck('event')->all());
    }

    public function test_only_reception_staff_can_change_bookings(): void
    {
        $this->actingAs($this->user(Role::RECEPTION));
        $this->receptionBooks($this->student(), 9);
        $booking = Booking::sole();

        $this->actingAs($this->user(Role::TEACHER));
        Livewire::test(AgendaDay::class)->set('date', self::MONDAY)->call('editBooking', $booking->id)->assertForbidden();
    }

    public function test_full_hours_go_to_the_waitlist_room_and_the_seat_passes_only_to_the_waitlist(): void
    {
        // "Agendar en lista de espera" apagado, pero el panel tiene la columna "Lista de espera" con el aula 9.
        $this->school->update(['books_without_classroom' => false, 'session_capacity' => 1]);
        $waitRoom = Classroom::create(['school_id' => $this->school->id, 'name' => '9', 'capacity' => 20, 'is_active' => true]);
        $waitTeacher = Teacher::factory()->create(['school_id' => $this->school->id, 'user_id' => null, 'short_name' => 'LISTA_ESP', 'panel_number' => 99, 'is_waitlist' => true]);
        TeacherAvailability::create(['school_id' => $this->school->id, 'teacher_id' => $waitTeacher->id, 'date' => self::MONDAY,
            'schedule_slot_id' => $this->slots[9]->id, 'classroom_id' => $waitRoom->id, 'is_absent' => false]);
        $this->actingAs($this->user(Role::RECEPTION));
        [$ana, $luis, $sofia, $pedro] = [$this->student(), $this->student(), $this->student(), $this->student()];

        $this->receptionBooks($ana, 9)->assertDispatched('toast', type: 'success');
        $this->receptionBooks($luis, 9)->assertDispatched('toast', type: 'success');
        $options = Livewire::test(AgendaDay::class)->set('date', self::MONDAY)->call('openSlot', self::MONDAY, $this->slots[9]->id)
            ->call('pickStudent', $sofia->id)->get('options');
        $this->assertTrue(collect($options[0]['options'])->firstWhere('modality', 'in_person')['waitlist']);

        $this->receptionBooks($sofia, 9)->assertDispatched('toast', type: 'warning');
        $waiting = Booking::where('student_id', $sofia->id)->sole();
        $this->assertTrue($waiting->is_waitlist);
        $this->assertSame([$waitRoom->id, $waitTeacher->id], [$waiting->classroom_id, $waiting->teacher_id]);

        // Ana no llegó: su lugar pasa a Sofía (de la lista de espera); Pedro, sin reservación, no aparece.
        $seat = Booking::where('student_id', $ana->id)->sole();
        $page = Livewire::test(AgendaDay::class)->set('date', self::MONDAY)->call('editBooking', $seat->id);
        $this->assertSame([$waiting->id], $page->viewData('edit')['waiting']->modelKeys());
        $page->assertDontSee($pedro->fullName())
            ->set('editWaitingId', $waiting->id)->assertSet('editReasonId', $this->reasons['no_show']->id)
            ->call('saveChange')->assertDispatched('toast', type: 'success');

        $seat->refresh();
        $waiting->refresh();
        $this->assertSame([BookingStatus::Cancelled, $this->reasons['no_show']->id], [$seat->status, $seat->cancellation_reason_id]);
        $this->assertFalse($waiting->is_waitlist);
        $this->assertSame([$seat->classroom_id, $seat->teacher_id], [$waiting->classroom_id, $waiting->teacher_id]);
    }

    public function test_reception_changes_the_activity_of_a_booking_when_there_is_room(): void
    {
        $this->school->update(['club_capacity' => 1]);
        $club = Club::create(['school_id' => $this->school->id, 'name' => 'Conversation Club', 'sort_order' => 10, 'is_active' => true,
            'codes' => [['code' => 'C12', 'levels' => [1, 2], 'color' => 1, 'is_active' => true]]]);
        $session = ClubSession::create(['school_id' => $this->school->id, 'club_id' => $club->id, 'code' => 'C12', 'teacher_id' => Teacher::where('short_name', 'MELISSA')->value('id'),
            'classroom_id' => $this->rooms[0]->id, 'date' => self::MONDAY, 'schedule_slot_id' => $this->slots[10]->id, 'modality' => 'in_person']);
        $this->actingAs($this->user(Role::RECEPTION));
        [$ana, $luis] = [$this->student(), $this->student()];

        // Ana reservó club pero viene a práctica: cambia y su lugar del club queda libre para Luis.
        $this->receptionBooks($ana, 10, 'club', 'in_person', $session->id)->assertDispatched('toast', type: 'success');
        $booking = Booking::sole();
        $page = Livewire::test(AgendaDay::class)->set('date', self::MONDAY)->call('editBooking', $booking->id);
        $kinds = collect($page->viewData('edit')['activities'][0]['options'])->map(fn (array $item) => $item['kind'].'|'.$item['modality'])->all();
        $this->assertSame(['session|in_person', 'session|online'], $kinds);

        $page->set('editActivity', $this->slots[10]->id.'|session|in_person|')->call('saveChange')->assertDispatched('toast', type: 'success');
        $booking->refresh();
        $this->assertSame([BookingKind::Session, null, BookingStatus::Booked], [$booking->kind, $booking->club_session_id, $booking->status]);
        $this->assertNotNull($booking->teacher_availability_id);
        $this->assertSame('activity_changed', $booking->events()->pluck('event')->last());
        $this->receptionBooks($luis, 10, 'club', 'in_person', $session->id)->assertDispatched('toast', type: 'success');

        // De regreso al club: ya está lleno (Luis), así que no cambia.
        Livewire::test(AgendaDay::class)->set('date', self::MONDAY)->call('editBooking', $booking->id)
            ->set('editActivity', $this->slots[10]->id.'|club|in_person|'.$session->id)->call('saveChange')
            ->assertDispatched('toast', type: 'error', message: __('agenda.validation.full'));
        $this->assertSame(BookingKind::Session, $booking->refresh()->kind);
    }

    public function test_reception_fixes_a_booking_made_for_the_wrong_student(): void
    {
        $this->actingAs($this->user(Role::RECEPTION));
        [$martin, $otroMartin] = [$this->student(), $this->student()];
        $this->receptionBooks($martin, 9);
        $booking = Booking::sole();

        Livewire::test(AgendaDay::class)->set('date', self::MONDAY)->call('editBooking', $booking->id)
            ->set('editSearch', $otroMartin->first_name)->call('pickEditStudent', $otroMartin->id)->call('saveChange')
            ->assertDispatched('toast', type: 'success');

        $this->assertSame($otroMartin->id, $booking->refresh()->student_id);
        $this->assertSame(BookingStatus::Booked, $booking->status);
    }

    public function test_double_click_on_attended_returns_the_booking_to_booked(): void
    {
        $this->actingAs($this->user(Role::RECEPTION));
        $this->receptionBooks($this->student(), 9);
        $booking = Booking::sole();

        Livewire::test(AgendaDay::class)->set('date', self::MONDAY)->call('markAttended', $booking->id)
            ->assertSeeHtml('wire:dblclick="undoAttendance('.$booking->id.')"')
            ->call('undoAttendance', $booking->id)->assertDispatched('toast', type: 'success')
            ->assertSeeHtml('editBooking('.$booking->id.')');

        $this->assertSame(BookingStatus::Booked, $booking->refresh()->status);
        $this->assertSame(['booked', 'attended', 'attendance_undone'], $booking->events()->pluck('event')->all());
    }

    public function test_the_activity_label_includes_its_code_from_the_catalog(): void
    {
        foreach ([1 => 'BA', 2 => '1P'] as $number => $code) {
            Activity::create(['school_id' => $this->school->id, 'number' => $number, 'code' => $code, 'description' => $code, 'is_active' => true]);
        }
        $this->actingAs($this->user(Role::RECEPTION));
        $student = $this->student();

        $this->receptionBooks($student, 9);
        $this->receptionBooks($student, 10);

        $this->assertSame(['L1-L1-A1-BA', 'L1-L1-A2-1P'], Booking::orderBy('id')->with(['book', 'lesson'])->get()->map->activityLabel()->all());
    }

    public function test_a_coordinator_teacher_gets_no_students_during_a_club_evaluation(): void
    {
        $melissa = Teacher::where('short_name', 'MELISSA')->sole();
        $dulce = Teacher::where('short_name', 'DULCE')->sole();
        $melissa->update(['user_id' => $this->user(Role::ACADEMIC_COORDINATOR)->id]);
        $this->actingAs($this->user(Role::RECEPTION));
        $evaluation = CoordinatorActivity::create(['school_id' => $this->school->id, 'name' => 'Evaluación de Club', 'authorized_hours' => 2,
            'per_teacher' => true, 'requires_teacher' => true, 'sort_order' => 1, 'is_active' => true]);
        $meeting = CoordinatorActivity::create(['school_id' => $this->school->id, 'name' => 'Junta mensual', 'authorized_hours' => 1, 'sort_order' => 2, 'is_active' => true]);
        CoordinatorSession::create(['school_id' => $this->school->id, 'coordinator_activity_id' => $evaluation->id, 'teacher_id' => $dulce->id, 'date' => self::MONDAY, 'schedule_slot_id' => $this->slots[9]->id]);
        CoordinatorSession::create(['school_id' => $this->school->id, 'coordinator_activity_id' => $meeting->id, 'date' => self::MONDAY, 'schedule_slot_id' => $this->slots[10]->id]);

        // A las 9 evalúa: solo queda el aula de Dulce (cupo 2) en el calendario y en las reservaciones.
        $days = app(AvailabilityPlanner::class)->days($this->school, CarbonImmutable::parse(self::MONDAY));
        $week = app(AgendaService::class)->weekAvailability($this->school, $days);
        $this->assertSame(2, $week[self::MONDAY.'|'.$this->slots[9]->id]['session']['capacity']);

        foreach (range(1, 2) as $i) {
            $this->receptionBooks($this->student(), 9)->assertDispatched('toast', type: 'success');
        }
        $this->assertSame([$dulce->id], Booking::pluck('teacher_id')->unique()->values()->all());
        $this->receptionBooks($this->student(), 9)->assertDispatched('toast', type: 'error', message: __('agenda.validation.full'));

        // Otra actividad (junta) no la bloquea: a las 10 sigue recibiendo alumnos.
        $this->assertSame(4, $week[self::MONDAY.'|'.$this->slots[10]->id]['session']['capacity']);
    }
}
