<?php

namespace Tests\Feature\Students;

use App\Enums\StudentStatus;
use App\Livewire\BirthdayGreeting;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Notifications\StudentBirthday;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StudentBirthdayTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, MenuSeeder::class]);
        $this->school = School::factory()->create(['name' => 'QUERÉTARO NORTE']);
    }

    private function student(string $birthDate, array $attributes = [], bool $withUser = true): Student
    {
        $user = $withUser ? User::factory()->create(['role_id' => Role::where('slug', Role::STUDENT)->value('id'), 'school_id' => $this->school->id]) : null;

        return Student::factory()->create([
            'school_id' => $this->school->id, 'user_id' => $user?->id, 'first_name' => 'ANA', 'birth_date' => $birthDate,
            'status' => StudentStatus::Active, ...$attributes,
        ]);
    }

    public function test_only_students_with_access_who_turn_years_today_are_greeted_once(): void
    {
        $ana = $this->student('2000-10-21');
        $this->student('2001-10-21', withUser: false);
        $this->student('2002-10-21', ['status' => StudentStatus::Withdrawn]);
        $this->student('2000-10-22');

        $this->artisan('students:birthday-greetings', ['--date' => '2026-10-21'])->assertSuccessful();
        $this->artisan('students:birthday-greetings', ['--date' => '2026-10-21'])->assertSuccessful();

        $this->assertDatabaseCount('notifications', 1);
        $notification = $ana->user->notifications()->sole();
        $this->assertSame(StudentBirthday::class, $notification->type);
        $this->assertSame(['2026-10-21', 26], [$notification->data['birthday_on'], $notification->data['age']]);
    }

    public function test_february_29_birthdays_are_greeted_on_the_28th_in_common_years(): void
    {
        $leap = $this->student('2004-02-29');

        $this->artisan('students:birthday-greetings', ['--date' => '2027-02-28']);

        $this->assertSame(1, $leap->user->notifications()->count());
    }

    public function test_mail_is_only_sent_when_enabled(): void
    {
        $ana = $this->student('2000-10-21');
        $notification = new StudentBirthday($ana, '2026-10-21');

        $this->assertSame(['database'], $notification->via($ana->user));

        config(['appingles.birthdays.mail' => true]);
        $this->assertSame(['database', 'mail'], $notification->via($ana->user));
    }

    public function test_the_greeting_shows_until_it_is_dismissed_and_for_seven_days_at_most(): void
    {
        $ana = $this->student('2000-10-21');
        $this->travelTo(now()->setDate(2026, 10, 21)->setTime(9, 0));
        $this->artisan('students:birthday-greetings');
        $this->actingAs($ana->user);

        // Si no entró ese día, la ve la próxima vez (hasta 7 días después).
        $this->travelTo(now()->setDate(2026, 10, 25));
        $this->get(route('dashboard'))->assertOk()->assertSee('¡Feliz cumpleaños número 26, ANA!');

        Livewire::test(BirthdayGreeting::class)->assertSee('Marcar como leída')->call('dismiss')->assertDontSee('Marcar como leída');
        $this->assertNotNull($ana->user->notifications()->sole()->read_at);
        $this->get(route('dashboard'))->assertDontSee('¡Feliz cumpleaños número 26, ANA!');

        // Sin abrirla, después de 7 días ya no aparece.
        $ana->user->notifications()->update(['read_at' => null]);
        $this->travelTo(now()->setDate(2026, 10, 29));
        Livewire::test(BirthdayGreeting::class)->assertDontSee('Marcar como leída');
    }

    public function test_reception_and_school_admin_see_todays_birthdays_on_the_dashboard(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 21)->setTime(9, 0));
        $this->student('2000-10-21', ['first_name' => 'CUMPLEAÑERA'], withUser: false);
        $other = School::factory()->create();
        Student::factory()->create(['school_id' => $other->id, 'first_name' => 'AJENA', 'birth_date' => '2000-10-21', 'status' => StudentStatus::Active]);

        foreach ([Role::RECEPTION, Role::SCHOOL_ADMIN] as $role) {
            $this->actingAs(User::factory()->create(['role_id' => Role::where('slug', $role)->value('id'), 'school_id' => $this->school->id]))
                ->get(route('dashboard'))->assertOk()->assertSee('Cumpleaños de hoy')->assertSee('CUMPLEAÑERA')->assertDontSee('AJENA');
        }

        $this->actingAs(User::factory()->create(['role_id' => Role::where('slug', Role::ACADEMIC_COORDINATOR)->value('id'), 'school_id' => $this->school->id]))
            ->get(route('dashboard'))->assertOk()->assertDontSee('Cumpleaños de hoy');
    }

    public function test_the_command_is_scheduled_every_day(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('students:birthday-greetings');
    }
}
