<?php

namespace Tests\Feature\Kardex;

use App\Actions\Agenda\AgendaService;
use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Enums\ClubModality;
use App\Enums\GradeType;
use App\Enums\LessonType;
use App\Enums\ReasonAudience;
use App\Livewire\Catalogs\ActivitiesTab;
use App\Livewire\Catalogs\StudentCatalogTab;
use App\Livewire\Kardex\GradesDay;
use App\Livewire\Kardex\MyKardex;
use App\Models\Activity;
use App\Models\Book;
use App\Models\Booking;
use App\Models\Classroom;
use App\Models\Club;
use App\Models\ClubSession;
use App\Models\Grade;
use App\Models\KardexEntry;
use App\Models\Lesson;
use App\Models\Role;
use App\Models\ScheduleSlot;
use App\Models\School;
use App\Models\Shift;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\UngradedReason;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KardexGradesTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-07-27';

    private School $school;

    /** @var array<int, ScheduleSlot> */
    private array $slots = [];

    private Classroom $room;

    private Book $book;

    /** @var list<Lesson> */
    private array $lessons = [];

    /** @var array<string, Grade> */
    private array $grades = [];

    /** @var array<string, UngradedReason> */
    private array $reasons = [];

    private Teacher $melissa;

    private Teacher $dulce;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, MenuSeeder::class]);
        $this->travelTo(CarbonImmutable::parse(self::TODAY.' 12:00', 'America/Mexico_City'));
        $this->school = School::factory()->create(['timezone' => 'America/Mexico_City']);
        [$this->slots, $this->room, $this->book, $this->lessons] = $this->academics($this->school);

        foreach (Grade::DEFAULTS as $code => [$name, $type]) {
            $this->grades[$code] = Grade::create(['school_id' => $this->school->id, 'code' => $code, 'name' => $name, 'type' => $type, 'sort_order' => count($this->grades) + 1, 'is_active' => true]);
        }
        foreach (UngradedReason::DEFAULTS as $name => $audience) {
            $this->reasons[$name] = UngradedReason::create(['school_id' => $this->school->id, 'name' => $name, 'audience' => $audience, 'sort_order' => count($this->reasons) + 1, 'is_active' => true]);
        }

        $this->melissa = Teacher::factory()->create(['school_id' => $this->school->id, 'short_name' => 'MELISSA', 'panel_number' => 1]);
        $this->dulce = Teacher::factory()->create(['school_id' => $this->school->id, 'short_name' => 'DULCE', 'panel_number' => 2]);
    }

    /** @return array{0: array<int, ScheduleSlot>, 1: Classroom, 2: Book, 3: list<Lesson>} */
    private function academics(School $school): array
    {
        $shift = Shift::create(['school_id' => $school->id, 'name' => 'Matutino', 'sort_order' => 1, 'is_active' => true]);
        $slots = [];
        foreach ([9, 10, 11] as $number => $hour) {
            $slots[$hour] = ScheduleSlot::create(['school_id' => $school->id, 'shift_id' => $shift->id, 'number' => $number + 1,
                'starts_at' => sprintf('%02d:00:00', $hour), 'ends_at' => sprintf('%02d:00:00', $hour + 1), 'is_active' => true]);
        }
        foreach ([[1, 'BA'], [2, '1P'], [3, 'M'], [4, 'WS'], [5, '2P'], [6, 'WP'], [7, 'SP']] as [$number, $code]) {
            Activity::create(['school_id' => $school->id, 'number' => $number, 'code' => $code, 'description' => $code,
                'grade_type' => GradeType::forActivityNumber($number), 'is_active' => true]);
        }
        $book = Book::create(['school_id' => $school->id, 'level' => 1, 'name' => 'Book 1', 'is_active' => true]);
        $lessons = [];
        foreach ([1, 2] as $number) {
            $lessons[] = Lesson::create(['school_id' => $school->id, 'book_id' => $book->id, 'number' => $number, 'name' => "Lesson $number",
                'type' => LessonType::Lesson, 'sort_order' => $number, 'is_active' => true]);
        }

        return [$slots, Classroom::create(['school_id' => $school->id, 'name' => '1', 'capacity' => 5, 'is_active' => true]), $book, $lessons];
    }

    private function user(string $roleSlug, ?School $school = null): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $roleSlug)->value('id'), 'school_id' => ($school ?? $this->school)->id]);
    }

    private function teacherUser(Teacher $teacher): User
    {
        return User::findOrFail($teacher->user_id);
    }

    private function booking(Student $student, Teacher $teacher, int $activity, int $hour = 9, string $date = self::TODAY, int $lesson = 0): Booking
    {
        return Booking::create([
            'school_id' => $this->school->id, 'student_id' => $student->id, 'date' => $date, 'schedule_slot_id' => $this->slots[$hour]->id,
            'kind' => BookingKind::Session, 'modality' => ClubModality::InPerson, 'teacher_id' => $teacher->id, 'classroom_id' => $this->room->id,
            'is_waitlist' => false, 'status' => BookingStatus::Attended, 'book_id' => $this->book->id, 'lesson_id' => $this->lessons[$lesson]->id, 'activity_number' => $activity,
        ]);
    }

    /** @return list<string> "L1-L1-A1-BA F" de lo calificado al alumno, en orden. */
    private function kardex(Student $student): array
    {
        return KardexEntry::where('student_id', $student->id)->with(['grade', 'book', 'lesson'])->orderBy('sort_order')->get()
            ->map(fn (KardexEntry $entry) => $entry->practiceLabel().' '.$entry->grade->code)->all();
    }

    public function test_the_teacher_only_sees_their_students_and_reception_sees_everyone(): void
    {
        $ana = Student::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Ana']);
        $beto = Student::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Beto']);
        $this->booking($ana, $this->melissa, 1);
        $this->booking($beto, $this->dulce, 1);

        $this->actingAs($this->teacherUser($this->melissa));
        Livewire::test(GradesDay::class)->assertSee($ana->fullName())->assertDontSee($beto->fullName());

        $this->actingAs($this->user(Role::RECEPTION));
        Livewire::test(GradesDay::class)->assertSee($ana->fullName())->assertSee($beto->fullName());

        // La teacher no abre la ventana de un alumno de otra teacher; el alumno no entra.
        $this->actingAs($this->teacherUser($this->melissa));
        Livewire::test(GradesDay::class)->call('open', Booking::where('student_id', $beto->id)->value('id'))->assertForbidden();
        $this->actingAs($this->user(Role::STUDENT));
        Livewire::test(GradesDay::class)->assertForbidden();
    }

    public function test_the_window_shows_three_practices_and_saves_only_the_graded_ones_in_order(): void
    {
        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $booking = $this->booking($student, $this->melissa, 1);
        $this->actingAs($this->teacherUser($this->melissa));

        $page = Livewire::test(GradesDay::class)->call('open', $booking->id)
            ->assertSee('L1-L1-A1-BA')->assertSee('L1-L1-A2-1P')->assertSee('L1-L1-A3-M')->assertDontSee('L1-L1-A4-WS');

        // Sin huecos: no se califica la 2.ª dejando vacía la 1.ª.
        $page->set('grades', ['', (string) $this->grades['F']->id, ''])->call('save')->assertHasErrors('grades');
        // Las actividades 1–3 solo llevan firma (F).
        $page->set('grades', [(string) $this->grades['A']->id, '', ''])->call('save')->assertHasErrors('grades.0');

        $page->set('grades', [(string) $this->grades['F']->id, (string) $this->grades['F']->id, ''])->call('save')
            ->assertHasNoErrors()->assertSet('gradingId', null)->assertDispatched('toast', type: 'success');
        $this->assertSame(['L1-L1-A1-BA F', 'L1-L1-A2-1P F'], $this->kardex($student));

        // Al volver a abrir aparecen las 3 siguientes: A3 (firma) y A4–A5 (A, B o C).
        $page->call('open', $booking->id)->assertSee('L1-L1-A3-M')->assertSee('L1-L1-A4-WS')->assertSee('L1-L1-A5-2P')
            ->set('grades', [(string) $this->grades['F']->id, (string) $this->grades['B']->id, (string) $this->grades['A']->id])->call('save')->assertHasNoErrors();
        $this->assertSame(['L1-L1-A1-BA F', 'L1-L1-A2-1P F', 'L1-L1-A3-M F', 'L1-L1-A4-WS B', 'L1-L1-A5-2P A'], $this->kardex($student));
        $this->assertSame($this->teacherUser($this->melissa)->id, KardexEntry::first()->user_id);
        $this->assertSame($this->melissa->id, KardexEntry::first()->teacher_id);

        // La agenda asigna la siguiente sin calificar del Kardex.
        $this->assertSame('L1-L1-A6-WP', app(AgendaService::class)->nextActivity($student->fresh())['label']);
    }

    public function test_grading_needs_the_attendance_first_but_it_can_stay_ungraded(): void
    {
        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $booking = $this->booking($student, $this->melissa, 1);
        $booking->update(['status' => BookingStatus::Booked]);
        $this->actingAs($this->teacherUser($this->melissa));

        // En el listado, sin asistencia no sale el botón "Calificaciones".
        // (El nombre del alumno también abre la ventana: con el botón serían 2 clics que abren.)
        $html = Livewire::test(GradesDay::class)->assertSee(__('kardex.no_attendance'))->html();
        $this->assertSame(1, substr_count($html, 'wire:click="open('.$booking->id.')"'));

        $page = Livewire::test(GradesDay::class)->call('open', $booking->id)->assertSee(__('kardex.not_attended'))->assertDontSee(__('kardex.actions.save'))
            ->set('grades', [(string) $this->grades['F']->id, '', ''])->call('save')->assertHasErrors('grades');
        $this->assertSame([], $this->kardex($student));

        // "Sin calificación" sí se puede (por ejemplo, si no vino).
        $page->set('ungraded', true)->set('ungradedReasonId', $this->reasons['Pendiente de revisar']->id)->set('ungradedComment', 'No vino')
            ->call('saveUngraded')->assertHasNoErrors();

        // Con la asistencia registrada ya se califica.
        $other = $this->booking(Student::factory()->create(['school_id' => $this->school->id]), $this->melissa, 1);
        $this->assertSame(2, substr_count(Livewire::test(GradesDay::class)->html(), 'wire:click="open('.$other->id.')"'));
        Livewire::test(GradesDay::class)->call('open', $other->id)->assertDontSee(__('kardex.not_attended'))
            ->set('grades', [(string) $this->grades['F']->id, '', ''])->call('save')->assertHasNoErrors();
    }

    public function test_the_kardex_continues_into_the_next_lesson_and_moves_the_upcoming_bookings(): void
    {
        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $today = $this->booking($student, $this->melissa, 6);
        $later = $this->booking($student, $this->melissa, 7, hour: 10);
        $tomorrow = $this->booking($student, $this->melissa, 1, date: '2026-07-28', lesson: 1);
        $this->actingAs($this->teacherUser($this->melissa));

        // En la asesoría avanzó: A6, A7 y la A1 de la lección 2.
        Livewire::test(GradesDay::class)->call('open', $today->id)->assertSee('L1-L2-A1-BA')
            ->set('grades', [(string) $this->grades['A']->id, (string) $this->grades['C']->id, (string) $this->grades['F']->id])->call('save')->assertHasNoErrors();

        // Las reservaciones que siguen (sin calificar) se recorren a lo que sigue en el Kardex.
        $this->assertSame([2, 3], [$later->fresh()->activity_number, $tomorrow->fresh()->activity_number]);
        $this->assertSame($this->lessons[1]->id, $later->fresh()->lesson_id);
    }

    public function test_grading_a_previous_day_needs_a_reason_and_a_comment_and_only_up_to_four_days(): void
    {
        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $yesterday = $this->booking($student, $this->melissa, 1, date: '2026-07-26');
        $this->booking($student, $this->melissa, 2, date: '2026-07-22');
        $this->actingAs($this->user(Role::RECEPTION));

        $page = Livewire::test(GradesDay::class)->set('date', '2026-07-26')->assertSee($student->fullName())->assertSee(__('kardex.late_day'))
            ->call('open', $yesterday->id)->set('grades', [(string) $this->grades['F']->id, '', ''])->call('save')
            ->assertHasErrors(['lateReasonId', 'lateComment']);
        $page->set('lateReasonId', $this->reasons['Junta academica']->id)->set('lateComment', 'El teacher no alcanzó a capturar')->call('save')->assertHasNoErrors();
        $entry = KardexEntry::sole();
        $this->assertSame([$this->reasons['Junta academica']->id, 'El teacher no alcanzó a capturar', '2026-07-26'], [$entry->late_reason_id, $entry->late_comment, $entry->session_date->toDateString()]);

        // Más de 4 días atrás o en el futuro no se puede elegir (recepción).
        Livewire::test(GradesDay::class)->set('date', '2026-07-22')->assertSet('date', self::TODAY)
            ->set('date', '2026-07-28')->assertSet('date', self::TODAY);
    }

    public function test_each_graded_practice_can_take_an_observation_from_the_reasons_of_the_teacher_or_the_staff(): void
    {
        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $booking = $this->booking($student, $this->melissa, 1);
        $this->actingAs($this->teacherUser($this->melissa));

        // El teacher ve sus motivos y los de ambos, no los del personal.
        $page = Livewire::test(GradesDay::class)->call('open', $booking->id)
            ->assertSee('Repetir')->assertSee('Material extra')->assertDontSee('Convivencia');

        // Un motivo del personal no se acepta para el teacher.
        $page->set('grades', [(string) $this->grades['F']->id, (string) $this->grades['F']->id, ''])
            ->set('observations', [(string) $this->reasons['Convivencia']->id, '', ''])->call('save')->assertHasErrors('observations.0');
        $this->assertSame(0, KardexEntry::count());

        // Observación opcional por práctica: la primera con "Repetir", la segunda sin observación.
        $page->set('observations', [(string) $this->reasons['Repetir']->id, '', ''])->call('save')->assertHasNoErrors();
        $this->assertSame([$this->reasons['Repetir']->id, null], KardexEntry::orderBy('sort_order')->pluck('observation_reason_id')->all());
        Livewire::test(GradesDay::class)->call('open', $booking->id)->assertSee('Repetir');

        // Recepción ve los del teacher y los del personal; Administración y Coordinación académica, solo los del personal.
        $other = $this->booking(Student::factory()->create(['school_id' => $this->school->id]), $this->melissa, 1)->id;
        $this->actingAs($this->user(Role::RECEPTION));
        Livewire::test(GradesDay::class)->call('open', $other)
            ->assertSee('Convivencia')->assertSee('Material extra')->assertSee('Pendiente de completar');
        foreach ([Role::SCHOOL_ADMIN, Role::ACADEMIC_COORDINATOR] as $role) {
            $this->actingAs($this->user($role));
            Livewire::test(GradesDay::class)->call('open', $other)
                ->assertSee('Convivencia')->assertSee('Material extra')->assertDontSee('Pendiente de completar');
        }
    }

    public function test_a_presentation_is_signed_once_without_moving_the_path_and_mentoring_is_not_graded(): void
    {
        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $other = Student::factory()->create(['school_id' => $this->school->id]);
        $presentation = Booking::create(['school_id' => $this->school->id, 'student_id' => $student->id, 'date' => self::TODAY, 'schedule_slot_id' => $this->slots[9]->id,
            'kind' => BookingKind::Presentation, 'modality' => ClubModality::InPerson, 'teacher_id' => $this->melissa->id, 'classroom_id' => $this->room->id,
            'is_waitlist' => false, 'status' => BookingStatus::Attended]);
        Booking::create(['school_id' => $this->school->id, 'student_id' => $other->id, 'date' => self::TODAY, 'schedule_slot_id' => $this->slots[9]->id,
            'kind' => BookingKind::Mentoring, 'modality' => ClubModality::InPerson, 'teacher_id' => $this->melissa->id, 'classroom_id' => $this->room->id,
            'is_waitlist' => false, 'status' => BookingStatus::Booked]);
        $next = app(AgendaService::class)->nextActivity($student)['label'];

        // Desde la Agenda (botón "Calificaciones"): la ventana de calificar, solo con la firma F.
        $this->actingAs($this->teacherUser($this->melissa));
        $page = Livewire::test(GradesDay::class, ['embedded' => true])->dispatch('kardex-grade', bookingId: $presentation->id)
            ->assertSet('gradingId', $presentation->id)->assertSee(__('agenda.kinds.presentation'));
        $this->assertSame([$this->grades['F']->id => 'F'], $page->viewData('grading')['options'][0]);
        $page->set('grades', [(string) $this->grades['F']->id, '', ''])->call('save')->assertHasNoErrors()->assertDispatched('kardex-saved');

        $entry = KardexEntry::sole();
        $this->assertSame(BookingKind::Presentation, $entry->kind);
        $this->assertSame([__('agenda.kinds.presentation').' F'], $this->kardex($student));
        // No mueve la ruta de prácticas; una segunda firma no se acepta.
        $this->assertSame($next, app(AgendaService::class)->nextActivity($student->fresh())['label']);
        Livewire::test(GradesDay::class)->call('open', $presentation->id)->set('grades', [(string) $this->grades['F']->id, '', ''])->call('save')->assertHasErrors('grades');

        // La mentoría especial no aparece en Calificaciones.
        Livewire::test(GradesDay::class)->assertSee($student->fullName())->assertDontSee($other->fullName());
    }

    public function test_a_booking_can_stay_ungraded_with_a_reason_and_the_kardex_does_not_advance(): void
    {
        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $booking = $this->booking($student, $this->melissa, 1);
        $this->actingAs($this->teacherUser($this->melissa));

        Livewire::test(GradesDay::class)->call('open', $booking->id)->set('ungraded', true)->call('saveUngraded')->assertHasErrors('ungradedReasonId')
            ->set('ungradedReasonId', $this->reasons['Pendiente de revisar']->id)->set('ungradedComment', 'No terminó el libro')->call('saveUngraded')->assertHasNoErrors()
            ->assertSee(__('kardex.ungraded_short', ['reason' => 'Pendiente de revisar']))->assertSee('No terminó el libro');

        $this->assertSame([$this->reasons['Pendiente de revisar']->id, 'No terminó el libro'], [$booking->fresh()->ungraded_reason_id, $booking->fresh()->ungraded_comment]);
        $this->assertSame([], $this->kardex($student));
        // La práctica sigue pendiente: se le vuelve a asignar.
        $this->assertSame('L1-L1-A1-BA', app(AgendaService::class)->nextActivity($student->fresh())['label']);
    }

    public function test_a_club_is_signed_with_f_once(): void
    {
        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $club = Club::create(['school_id' => $this->school->id, 'name' => 'Conversation Club', 'sort_order' => 1, 'is_active' => true,
            'codes' => [['code' => 'C12', 'levels' => [1], 'color' => 2, 'is_active' => true]]]);
        $session = ClubSession::create(['school_id' => $this->school->id, 'club_id' => $club->id, 'code' => 'C12', 'teacher_id' => $this->melissa->id,
            'classroom_id' => $this->room->id, 'date' => self::TODAY, 'schedule_slot_id' => $this->slots[11]->id, 'modality' => 'in_person']);
        $booking = Booking::create(['school_id' => $this->school->id, 'student_id' => $student->id, 'date' => self::TODAY, 'schedule_slot_id' => $this->slots[11]->id,
            'kind' => BookingKind::Club, 'modality' => ClubModality::InPerson, 'club_session_id' => $session->id, 'teacher_id' => $this->melissa->id,
            'classroom_id' => $this->room->id, 'is_waitlist' => false, 'status' => BookingStatus::Attended]);
        $this->actingAs($this->teacherUser($this->melissa));

        $page = Livewire::test(GradesDay::class)->call('open', $booking->id)->assertSee('C12')
            ->set('grades', [(string) $this->grades['A']->id, '', ''])->call('save')->assertHasErrors('grades.0')
            ->set('grades', [(string) $this->grades['F']->id, '', ''])->call('save')->assertHasNoErrors();

        $entry = KardexEntry::sole();
        $this->assertSame([BookingKind::Club, 'C12', $this->grades['F']->id], [$entry->kind, $entry->club_code, $entry->grade_id]);
        $page->call('open', $booking->id)->assertSee(__('kardex.nothing_to_grade'));
    }

    public function test_only_the_coordinator_or_the_admin_correct_and_only_the_last_practice_is_removed(): void
    {
        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $booking = $this->booking($student, $this->melissa, 4);
        $this->actingAs($this->teacherUser($this->melissa));
        Livewire::test(GradesDay::class)->call('open', $booking->id)
            ->set('grades', [(string) $this->grades['A']->id, (string) $this->grades['B']->id, ''])->call('save')->assertHasNoErrors();
        [$first, $last] = KardexEntry::orderBy('sort_order')->get()->all();

        Livewire::test(GradesDay::class)->call('open', $booking->id)->call('changeGrade', $first->id)->assertForbidden();

        foreach ([Role::ACADEMIC_COORDINATOR, Role::SCHOOL_ADMIN] as $role) {
            $this->actingAs($this->user($role));
            Livewire::test(GradesDay::class)->call('open', $booking->id)->set("corrections.{$first->id}", (string) $this->grades['C']->id)->call('changeGrade', $first->id)
                ->assertHasNoErrors()->assertDispatched('toast', type: 'success')->assertDispatched('kardex-saved');
        }
        $this->assertSame($this->grades['C']->id, $first->fresh()->grade_id);

        // Solo se quita la última calificada, para no romper el orden.
        Livewire::test(GradesDay::class)->call('open', $booking->id)->call('removeEntry', $first->id)->assertHasErrors('correction')
            ->call('removeEntry', $last->id)->assertHasNoErrors()
            // La Agenda se refresca (su aviso "Ya calificado") sin recargar la página.
            ->assertDispatched('kardex-saved');
        $this->assertSame(['L1-L1-A4-WS C'], $this->kardex($student));
    }

    public function test_the_student_sees_their_own_kardex(): void
    {
        $user = $this->user(Role::STUDENT);
        $student = Student::factory()->create(['school_id' => $this->school->id, 'user_id' => $user->id]);
        $other = Student::factory()->create(['school_id' => $this->school->id]);
        $this->actingAs($this->teacherUser($this->melissa));
        foreach ([$student, $other] as $item) {
            Livewire::test(GradesDay::class)->call('open', $this->booking($item, $this->melissa, 1)->id)->set('grades', [(string) $this->grades['F']->id, '', ''])->call('save');
        }

        $this->actingAs($user);
        Livewire::test(MyKardex::class)->assertOk()->assertSee('L1-L1-A1-BA')->assertSee('F · Firma')->assertSee('L1-L1-A2-1P')
            ->assertSee(__('kardex.progress_value', ['graded' => 1, 'total' => 14]))->assertSee('MELISSA');
        $this->assertSame(1, KardexEntry::where('student_id', $student->id)->count());

        $this->actingAs($this->user(Role::RECEPTION));
        Livewire::test(MyKardex::class)->assertForbidden();
    }

    public function test_another_school_cannot_see_or_grade_the_bookings(): void
    {
        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $booking = $this->booking($student, $this->melissa, 1);
        $other = School::factory()->create(['timezone' => 'America/Mexico_City']);

        $this->actingAs($this->user(Role::RECEPTION, $other));
        Livewire::test(GradesDay::class)->assertDontSee($student->fullName())->call('open', $booking->id)->assertNotFound();
        $this->assertSame(0, KardexEntry::withoutGlobalScope('school')->count());
    }

    public function test_the_kardex_catalogs_and_the_grade_type_of_each_activity_are_editable(): void
    {
        $this->actingAs($this->user(Role::SCHOOL_ADMIN));

        Livewire::test(StudentCatalogTab::class, ['schoolId' => $this->school->id, 'catalog' => 'calificaciones'])
            ->assertSee('Excelente')->assertSee('Firma')
            ->call('edit')->set('values.code', 'e')->set('values.name', 'Especial')->set('values.type', GradeType::Score->value)->call('save')->assertHasNoErrors();
        $this->assertSame(GradeType::Score, Grade::where('code', 'E')->sole()->type);

        Livewire::test(StudentCatalogTab::class, ['schoolId' => $this->school->id, 'catalog' => 'motivos'])
            ->assertSee('Repetir')->assertSee('Convivencia')->assertSee(__('kardex.reason_audiences.staff'))
            ->call('edit')->set('values.name', 'Dictado')->set('values.audience', ReasonAudience::Teacher->value)->call('save')->assertHasNoErrors();
        $this->assertSame(ReasonAudience::Teacher, UngradedReason::where('name', 'Dictado')->sole()->audience);

        $activity = Activity::where('number', 3)->sole();
        $this->assertSame(GradeType::Signature, $activity->grade_type);
        Livewire::test(ActivitiesTab::class, ['schoolId' => $this->school->id])->call('edit', $activity->id)->assertSet('gradeType', 'signature')
            ->set('gradeType', 'score')->call('save')->assertHasNoErrors();
        $this->assertSame(GradeType::Score, $activity->fresh()->grade_type);
    }
}
