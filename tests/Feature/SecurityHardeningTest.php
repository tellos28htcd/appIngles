<?php

namespace Tests\Feature;

use App\Jobs\DownloadStudentPhoto;
use App\Livewire\Students\StudentEditor;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pruebas de seguridad del sistema completo: ninguna pantalla interna sin sesión,
 * ningún script sin nonce ni de dominios externos, texto malicioso siempre escapado,
 * archivos disfrazados rechazados y descargas externas solo a direcciones públicas.
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, MenuSeeder::class]);
        $this->school = School::factory()->create(['name' => 'ESCUELA <b>SEGURA</b>']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', Role::SCHOOL_ADMIN)->value('id'), 'school_id' => $this->school->id]);
    }

    public function test_no_internal_page_opens_without_a_session(): void
    {
        $public = ['login', 'password.request', 'password.reset', 'invitation.accept', 'storage.local', 'storage.local.upload', 'up'];
        $checked = 0;

        foreach (app('router')->getRoutes() as $route) {
            /** @var Route $route */
            $name = $route->getName();

            if (! in_array('GET', $route->methods(), true) || $name === null || in_array($name, $public, true)
                || str_starts_with($route->uri(), 'livewire') || str_starts_with($route->uri(), '_boost')) {
                continue;
            }

            $parameters = collect($route->parameterNames())->mapWithKeys(fn (string $parameter) => [$parameter => 1])->all();
            $response = $this->get(route($name, $parameters));

            $this->assertTrue($response->isRedirect(route('login')) || $response->status() === 401 || $response->isRedirect(),
                "La ruta {$name} se abre sin sesión (estado {$response->status()}).");
            $this->assertGuest();
            $checked++;
        }

        $this->assertGreaterThan(30, $checked);
    }

    public function test_pages_only_run_own_scripts_with_the_nonce(): void
    {
        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $absence = $student->absences()->create(['school_id' => $this->school->id, 'starts_on' => '2026-08-01', 'ends_on' => '2026-08-02']);
        $this->actingAs($this->admin());
        $allowedHosts = array_filter([parse_url(config('app.url'), PHP_URL_HOST), '127.0.0.1', 'localhost']);

        foreach ([route('dashboard'), route('students.index'), route('students.show', $student), route('students.absence-print', [$student, $absence]),
            route('availability.index'), route('clubs.schedule'), route('coordinator.schedule'), route('teachers.index')] as $url) {
            $response = $this->get($url)->assertOk();
            $csp = $response->headers->get('Content-Security-Policy');
            preg_match("/'nonce-([A-Za-z0-9]+)'/", $csp, $nonce);

            // La política nunca permite scripts en línea ni de cualquier dominio.
            $this->assertStringNotContainsString("'unsafe-inline'", str($csp)->after('script-src')->before(';')->toString(), $url);
            $this->assertDoesNotMatchRegularExpression('/script-src[^;]*\s(\*|https:|http:)(\s|;|$)/', $csp, $url);

            preg_match_all('/<script\b[^>]*>/i', $response->getContent(), $scripts);
            foreach ($scripts[0] as $tag) {
                $this->assertStringContainsString('nonce="'.$nonce[1].'"', $tag, "Script sin nonce en {$url}: {$tag}");

                if (preg_match('/\ssrc="([^"]+)"/i', $tag, $src) && preg_match('#^(https?:)?//#', $src[1])) {
                    $this->assertContains(parse_url($src[1], PHP_URL_HOST), $allowedHosts, "Script de un dominio externo en {$url}: {$src[1]}");
                }
            }
        }
    }

    public function test_malicious_text_in_the_student_file_is_shown_as_text(): void
    {
        $payload = '<script>alert("xss")</script>';
        $image = '<img src=x onerror=alert(1)>';
        $student = Student::factory()->create(['school_id' => $this->school->id, 'first_name' => $payload, 'notes' => $image, 'street' => $image]);
        $absence = $student->absences()->create(['school_id' => $this->school->id, 'starts_on' => '2026-08-01', 'ends_on' => '2026-08-02', 'reason' => $payload]);
        Teacher::factory()->create(['school_id' => $this->school->id, 'user_id' => null, 'first_name' => $image, 'short_name' => 'MALO', 'panel_number' => 1]);
        $this->actingAs($this->admin());

        foreach ([route('students.index'), route('students.show', $student), route('students.absence-print', [$student, $absence]), route('teachers.index')] as $url) {
            $this->get($url)->assertOk()
                ->assertDontSee($payload, false)
                ->assertDontSee($image, false)
                ->assertDontSee('ESCUELA <b>SEGURA</b>', false);
        }
    }

    public function test_files_disguised_as_images_are_rejected(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin());

        Livewire::test(StudentEditor::class)
            ->set('form.first_name', 'Ana')->set('form.last_name', 'Pérez')
            ->set('form.photo', UploadedFile::fake()->createWithContent('foto.jpg', '<?php echo shell_exec($_GET["c"]); ?>'))
            ->call('save')
            ->assertHasErrors('form.photo');

        $this->assertSame(0, Student::count());
    }

    public function test_external_photo_links_only_reach_public_https_hosts(): void
    {
        foreach ([
            'http://example.com/foto.jpg',            // sin HTTPS
            'https://8.8.8.8/foto.jpg',               // IP escrita directamente
            'https://[::1]/foto.jpg',                  // IPv6 local
            'https://169.254.169.254/latest/meta-data', // metadatos de la nube
            'https://localhost/foto.jpg',              // el propio servidor
            'https://example.com:8443/foto.jpg',       // otro puerto
            'https://user:clave@example.com/foto.jpg', // credenciales en el enlace
            'file:///etc/passwd',
            'gopher://example.com/',
        ] as $url) {
            $this->assertNull(DownloadStudentPhoto::safeAddress($url), $url);
        }
    }
}
