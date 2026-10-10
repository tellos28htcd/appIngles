<?php

namespace Tests\Feature\Audit;

use App\Enums\LoginEvent;
use App\Livewire\Audit\AuditLogIndex;
use App\Livewire\Auth\Login;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\LoginLog;
use App\Models\Role;
use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;

    private School $schoolB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, MenuSeeder::class]);
        $this->schoolA = School::factory()->create(['name' => 'ESCUELA A']);
        $this->schoolB = School::factory()->create(['name' => 'ESCUELA B']);
    }

    private function user(string $roleSlug, ?School $school): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $roleSlug)->value('id'), 'school_id' => $school?->id]);
    }

    private function schoolAuditUrl(): string
    {
        return route('school-audit-logs.index');
    }

    public function test_user_changes_are_audited_without_sensitive_values(): void
    {
        $admin = $this->user(Role::SCHOOL_ADMIN, $this->schoolA);
        $this->actingAs($admin);

        $staff = $this->user(Role::RECEPTION, $this->schoolA);
        $staff->forceFill(['first_name' => 'Rosa', 'password' => 'Academia2026', 'last_login_at' => now()])->save();

        $log = AuditLog::where('auditable_type', User::class)->where('auditable_id', $staff->id)->where('event', 'updated')->sole();
        $this->assertSame($this->schoolA->id, $log->school_id);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('Rosa', $log->new_values['first_name']);
        $this->assertSame(AuditLog::REDACTED, $log->new_values['password']);
        $this->assertSame(AuditLog::REDACTED, $log->old_values['password']);
        $this->assertArrayNotHasKey('last_login_at', $log->new_values);
        $this->assertStringNotContainsString('Academia2026', $log->toJson());
        $this->assertStringNotContainsString('$2y$', $log->toJson());
    }

    public function test_only_a_login_timestamp_change_is_not_audited(): void
    {
        $user = $this->user(Role::SCHOOL_ADMIN, $this->schoolA);
        $user->forceFill(['last_login_at' => now()])->save();

        $this->assertSame(0, AuditLog::where('auditable_type', User::class)->where('event', 'updated')->count());
    }

    public function test_school_events_belong_to_that_school_and_teacher_photo_path_is_hidden(): void
    {
        $this->actingAs($this->user(Role::PLATFORM_ADMIN, null));
        $this->schoolA->update(['phone' => '4421234567', 'last_folio_series_a' => 10]);

        $log = AuditLog::where('auditable_type', School::class)->where('event', 'updated')->sole();
        $this->assertSame($this->schoolA->id, $log->school_id);
        $this->assertSame(['phone' => '4421234567'], $log->new_values);
        $this->assertSame('ESCUELA A', $log->auditable_label);

        $teacher = Teacher::factory()->create(['school_id' => $this->schoolA->id]);
        $teacher->update(['photo_path' => 'teachers/1/photo-1-abc.jpg']);

        $teacherLog = AuditLog::where('auditable_type', Teacher::class)->where('event', 'updated')->sole();
        $this->assertSame(AuditLog::REDACTED, $teacherLog->new_values['photo_path']);
        $this->assertSame($teacher->fullName(), $teacherLog->auditable_label);
    }

    public function test_login_logs_carry_the_users_school(): void
    {
        $user = $this->user(Role::SCHOOL_ADMIN, $this->schoolA);
        $user->forceFill(['password' => 'Academia2026'])->save();

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'Academia2026')
            ->call('login');

        $this->assertSame($this->schoolA->id, LoginLog::withoutGlobalScope('school')->where('event', LoginEvent::Succeeded)->sole()->school_id);
    }

    public function test_school_admin_only_sees_its_own_school(): void
    {
        $this->actingAs($this->user(Role::PLATFORM_ADMIN, null));
        Classroom::create(['school_id' => $this->schoolA->id, 'name' => 'SALÓN AZUL', 'capacity' => 5]);
        Classroom::create(['school_id' => $this->schoolB->id, 'name' => 'SALÓN ROJO', 'capacity' => 5]);
        $foreignLog = AuditLog::where('auditable_label', 'SALÓN ROJO')->sole();
        LoginLog::create(['school_id' => $this->schoolA->id, 'email' => 'propio@appingles.com', 'event' => LoginEvent::Failed]);
        LoginLog::create(['school_id' => $this->schoolB->id, 'email' => 'ajeno@appingles.com', 'event' => LoginEvent::Failed]);

        $this->flushSession();
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolA));

        $this->get($this->schoolAuditUrl())->assertOk()->assertSee('SALÓN AZUL')->assertDontSee('SALÓN ROJO');
        $this->get($this->schoolAuditUrl().'?vista=accesos')->assertOk()->assertSee('propio@appingles.com')->assertDontSee('ajeno@appingles.com');

        // Ni manipulando el filtro de escuela ni pidiendo el detalle de un registro ajeno.
        Livewire::withQueryParams(['escuela' => (string) $this->schoolB->id])
            ->test(AuditLogIndex::class)
            ->assertSet('school', '')
            ->assertDontSee('SALÓN ROJO')
            ->call('showDetail', $foreignLog->id)
            ->assertNotFound();
    }

    public function test_platform_actions_are_not_visible_to_schools(): void
    {
        $this->actingAs($this->user(Role::PLATFORM_ADMIN, null));
        Classroom::create(['school_id' => null, 'name' => 'SALÓN BASE', 'capacity' => 5]);

        $this->flushSession();
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolA));
        $this->get($this->schoolAuditUrl())->assertOk()->assertDontSee('SALÓN BASE');
    }

    public function test_platform_admin_sees_every_school_and_can_filter(): void
    {
        $this->actingAs($this->user(Role::PLATFORM_ADMIN, null));
        Classroom::create(['school_id' => $this->schoolA->id, 'name' => 'SALÓN AZUL', 'capacity' => 5]);
        Classroom::create(['school_id' => $this->schoolB->id, 'name' => 'SALÓN ROJO', 'capacity' => 5]);

        $this->get(route('audit-logs.index'))->assertOk()->assertSee('SALÓN AZUL')->assertSee('SALÓN ROJO');
        $this->get(route('audit-logs.index', ['escuela' => $this->schoolB->id]))->assertOk()->assertDontSee('SALÓN AZUL')->assertSee('SALÓN ROJO');
        $this->get(route('audit-logs.index', ['modulo' => 'classroom', 'accion' => 'deleted']))->assertOk()->assertDontSee('SALÓN AZUL');
    }

    public function test_detail_shows_field_changes(): void
    {
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolA));
        $classroom = Classroom::create(['name' => 'SALÓN AZUL', 'capacity' => 5]);
        $classroom->update(['capacity' => 6]);
        $log = AuditLog::where('event', 'updated')->sole();

        Livewire::withQueryParams([])->test(AuditLogIndex::class)
            ->call('showDetail', $log->id)
            ->assertSet('viewingId', $log->id)
            ->assertSee('Capacidad');
    }

    public function test_access_by_role(): void
    {
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolA))->get(route('audit-logs.index'))->assertForbidden();

        $this->flushSession();
        $this->actingAs($this->user(Role::ACADEMIC_COORDINATOR, $this->schoolA))->get($this->schoolAuditUrl())->assertForbidden();

        $this->flushSession();
        $this->actingAs($this->user(Role::PLATFORM_ADMIN, null))->get($this->schoolAuditUrl())->assertRedirect(route('audit-logs.index'));
    }

    public function test_export_downloads_only_the_filtered_school_rows(): void
    {
        $this->actingAs($this->user(Role::PLATFORM_ADMIN, null));
        Classroom::create(['school_id' => $this->schoolB->id, 'name' => 'SALÓN ROJO', 'capacity' => 5]);

        $this->flushSession();
        $this->actingAs($this->user(Role::SCHOOL_ADMIN, $this->schoolA));
        Classroom::create(['name' => 'SALÓN AZUL', 'capacity' => 5]);

        $response = Livewire::test(AuditLogIndex::class)->call('export');
        $response->assertFileDownloaded();

        $csv = $this->streamedCsv($response);
        $this->assertStringContainsString('SALÓN AZUL', $csv);
        $this->assertStringNotContainsString('SALÓN ROJO', $csv);
    }

    private function streamedCsv(mixed $component): string
    {
        $download = data_get($component->effects, 'download');

        return base64_decode($download['content']);
    }
}
