<?php

namespace Tests\Feature\Agenda;

use App\Actions\Agenda\AgendaService;
use App\Actions\Teachers\AvailabilityPlanner;
use App\Enums\BookingStatus;
use App\Enums\CancellationReason;
use App\Enums\LessonType;
use App\Enums\SelfBooking;
use App\Livewire\Agenda\AgendaDay;
use App\Livewire\Agenda\MyAgenda;
use App\Models\Book;
use App\Models\Booking;
use App\Models\Classroom;
use App\Models\Club;
use App\Models\ClubSession;
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
        return Livewire::withQueryParams(['fecha' => self::MONDAY])->test(AgendaDay::class)
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
        Livewire::withQueryParams(['fecha' => self::MONDAY])->test(AgendaDay::class)
            ->call('confirmCancel', $freed->id)->set('reason', CancellationReason::Notice->value)->call('cancel')
            ->assertSet('cancellingId', null);
        $this->assertSame([$waiting->id], app(AgendaService::class)->waitlistProposals($this->school, CarbonImmutable::parse(self::MONDAY))->modelKeys());

        Livewire::withQueryParams(['fecha' => self::MONDAY])->test(AgendaDay::class)->call('promote', $waiting->id)->assertDispatched('toast', type: 'success');
        $waiting->refresh();
        $this->assertFalse($waiting->is_waitlist);
        $this->assertSame($freed->classroom_id, $waiting->classroom_id);
        $this->assertSame(['waitlisted', 'promoted'], $waiting->events()->pluck('event')->all());
        $this->assertSame(['booked', 'cancelled'], $freed->events()->pluck('event')->all());
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
                'cancellation_reason' => $result === 'attended' ? null : CancellationReason::NoShow]);
        }

        $this->receptionBooks($student, 9)->assertDispatched('toast', type: 'success');
        $this->receptionBooks($student, 10)->assertDispatched('toast', type: 'error', message: __('agenda.validation.daily_limit', ['hours' => 1]));
    }

    public function test_reception_marks_attendance(): void
    {
        $this->actingAs($this->user(Role::RECEPTION));
        $this->receptionBooks($this->student(), 9);
        $booking = Booking::with('student')->sole();

        Livewire::withQueryParams(['fecha' => self::MONDAY])->test(AgendaDay::class)
            ->assertSee($booking->student->fullName())->assertSee('L1-L1-A1')
            ->call('markAttended', $booking->id)->assertDispatched('toast', type: 'success');

        $this->assertSame(BookingStatus::Attended, $booking->refresh()->status);
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
        Livewire::test(MyAgenda::class)->call('selectDay', self::MONDAY)->set('option', $option)->call('book')
            ->assertDispatched('toast', type: 'error', message: __('agenda.validation.self_disabled'));

        // "Día anterior": hoy (domingo) puede reservar el lunes.
        $this->school->update(['self_booking' => SelfBooking::DayBefore]);
        Livewire::test(MyAgenda::class)->call('selectDay', self::MONDAY)->set('option', $option)->call('book')
            ->assertDispatched('toast', type: 'success');
        $mine = Booking::with('classroom')->sole();
        $this->assertFalse($mine->booked_by_reception);

        // No cancela la de otro alumno; la suya sí (con "Aviso para cancelar").
        $other = Booking::create(['school_id' => $this->school->id, 'student_id' => $this->student()->id, 'date' => self::MONDAY,
            'schedule_slot_id' => $this->slots[10]->id, 'kind' => 'session', 'modality' => 'in_person', 'status' => BookingStatus::Booked]);
        Livewire::test(MyAgenda::class)->call('cancel', $other->id)->assertForbidden();
        Livewire::test(MyAgenda::class)->assertSee(__('agenda.room', ['name' => $mine->classroom->name]))->call('cancel', $mine->id)->assertDispatched('toast', type: 'success');
        $this->assertSame(CancellationReason::Notice, $mine->refresh()->cancellation_reason);
    }

    public function test_the_student_cannot_book_the_same_day_with_day_before(): void
    {
        $this->travelTo(CarbonImmutable::parse(self::MONDAY.' 06:00', 'America/Mexico_City'));
        $this->school->update(['self_booking' => SelfBooking::DayBefore]);
        $user = $this->user(Role::STUDENT);
        $this->student($user);
        $this->actingAs($user);

        Livewire::test(MyAgenda::class)->set('option', $this->slots[9]->id.'|session|in_person|')->call('book')
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

        $clubs = collect(Livewire::withQueryParams(['fecha' => self::MONDAY])->test(AgendaDay::class)->call('create')->call('pickStudent', $ana->id)->get('options'))
            ->flatMap(fn (array $hour) => $hour['options'])->pluck('club_session_id')->filter()->values()->all();
        $this->assertSame([$level1->id], $clubs);

        $this->receptionBooks($ana, 10, 'club', 'in_person', $level1->id)->assertDispatched('toast', type: 'success');
        $this->receptionBooks($luis, 10, 'club', 'in_person', $level1->id)->assertDispatched('toast', type: 'error', message: __('agenda.validation.full'));
        $this->receptionBooks($luis, 10, 'club', 'in_person', $level3->id)->assertDispatched('toast', type: 'error', message: __('agenda.validation.club_not_allowed'));
        $this->assertSame('C12', Booking::with('clubSession')->sole()->activityLabel());
    }

    public function test_online_sessions_use_the_virtual_rooms_per_slot(): void
    {
        $this->school->update(['virtual_rooms_per_slot' => 1, 'virtual_room_capacity' => 1]);
        $this->actingAs($this->user(Role::RECEPTION));

        $this->receptionBooks($this->student(), 9, 'session', 'online')->assertDispatched('toast', type: 'success');
        $this->receptionBooks($this->student(), 9, 'session', 'online')->assertDispatched('toast', type: 'error', message: __('agenda.validation.full'));
        $this->assertSame(__('agenda.virtual_room', ['number' => 1]), Booking::with('classroom')->sole()->placeLabel());
    }

    public function test_another_school_cannot_see_or_change_the_bookings(): void
    {
        $this->actingAs($this->user(Role::RECEPTION));
        $this->receptionBooks($this->student(), 9);
        $booking = Booking::with('student')->sole();

        $other = School::factory()->create();
        $this->actingAs($this->user(Role::RECEPTION, $other));

        Livewire::withQueryParams(['fecha' => self::MONDAY])->test(AgendaDay::class)->assertDontSee($booking->student->fullName());
        Livewire::withQueryParams(['fecha' => self::MONDAY])->test(AgendaDay::class)->call('markAttended', $booking->id)->assertNotFound();
        $this->assertSame(BookingStatus::Booked, $booking->refresh()->status);
    }
}
