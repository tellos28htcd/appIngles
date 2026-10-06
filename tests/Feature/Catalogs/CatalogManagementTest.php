<?php

namespace Tests\Feature\Catalogs;

use App\Actions\Catalogs\CopyBaseCatalogs;
use App\Livewire\Catalogs\ActivitiesTab;
use App\Livewire\Catalogs\BooksTab;
use App\Livewire\Catalogs\ClubsTab;
use App\Livewire\Catalogs\SchoolCatalogs;
use App\Livewire\Catalogs\ShiftsTab;
use App\Livewire\Schools\SchoolEditor;
use App\Models\Activity;
use App\Models\Book;
use App\Models\Club;
use App\Models\Lesson;
use App\Models\Municipality;
use App\Models\Role;
use App\Models\ScheduleSlot;
use App\Models\School;
use App\Models\Shift;
use App\Models\State;
use App\Models\User;
use Database\Seeders\CatalogBaseSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private School $schoolA;

    private School $schoolB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, MenuSeeder::class, CatalogBaseSeeder::class]);
        $this->superAdmin = User::factory()->create(['role_id' => $this->roleId(Role::PLATFORM_ADMIN)]);

        $copy = app(CopyBaseCatalogs::class);
        $this->schoolA = School::factory()->create(['name' => 'ESCUELA A']);
        $this->schoolB = School::factory()->create(['name' => 'ESCUELA B']);
        $copy->handle($this->schoolA);
        $copy->handle($this->schoolB);
    }

    private function roleId(string $slug): int
    {
        return Role::where('slug', $slug)->value('id');
    }

    private function schoolAdmin(School $school): User
    {
        return User::factory()->create(['role_id' => $this->roleId(Role::SCHOOL_ADMIN), 'school_id' => $school->id]);
    }

    private function catalogCount(string $model, ?int $schoolId): int
    {
        return $model::withoutGlobalScope('school')->ofCatalog($schoolId)->count();
    }

    // ---- Catálogo base (datos del Excel) -----------------------------------

    public function test_base_catalog_is_seeded_from_the_excel(): void
    {
        $this->assertSame(3, $this->catalogCount(Shift::class, null));
        $this->assertSame(14, $this->catalogCount(ScheduleSlot::class, null));
        $this->assertSame(['Beginners', 'Intermediate', 'Advanced'], Book::ofCatalog(null)->orderBy('level')->pluck('name')->all());
        $this->assertSame(54, $this->catalogCount(Lesson::class, null));
        $this->assertSame(['BA', '1P', 'M', 'WS', '2P', 'WP', 'SP', 'PR'], Activity::ofCatalog(null)->orderBy('number')->pluck('code')->all());

        $conversation = Club::ofCatalog(null)->where('name', 'Conversation Club')->first();
        $this->assertEquals([1 => 10.0, 2 => 12.0, 3 => 15.0], $conversation->books->mapWithKeys(fn ($book) => [$book->level => (float) $book->pivot->hours])->sortKeys()->all());
        $this->assertSame(1, Club::ofCatalog(null)->where('name', 'Ted Talks Club')->first()->books()->count());

        // Orden del Excel: Lesson 7 → Check Point C1 (15) → Verb in context 1 (16) → Lesson 8.
        $beginners = Book::ofCatalog(null)->where('level', 1)->first();
        $this->assertSame([7, 15, 16, 8], $beginners->lessons->pluck('number')->slice(6, 4)->values()->all());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(CatalogBaseSeeder::class);

        $this->assertSame(14, $this->catalogCount(ScheduleSlot::class, null));
        $this->assertSame(14, $this->catalogCount(ScheduleSlot::class, $this->schoolA->id));
    }

    // ---- Copia a la escuela -------------------------------------------------

    public function test_new_school_receives_a_full_copy_of_the_base(): void
    {
        $state = State::create(['code' => '22', 'name' => 'Querétaro']);
        $municipality = Municipality::create(['state_id' => $state->id, 'code' => '014', 'name' => 'Querétaro']);
        $this->actingAs($this->superAdmin);

        Livewire::test(SchoolEditor::class)
            ->set('form.code', '026')
            ->set('form.name', 'QUERÉTARO NORTE')
            ->set('form.state_id', $state->id)
            ->set('form.municipality_id', $municipality->id)
            ->set('form.session_capacity', 5)
            ->set('form.club_min_lesson_number', 7)
            ->call('save')
            ->assertHasNoErrors();

        $school = School::where('code', '026')->sole();
        $this->assertSame(7, $school->club_min_lesson_number);
        $this->assertSame(14, $this->catalogCount(ScheduleSlot::class, $school->id));
        $this->assertSame(54, $this->catalogCount(Lesson::class, $school->id));
        $this->assertSame(8, $this->catalogCount(Activity::class, $school->id));

        // Las dependencias apuntan a la copia de la escuela, no a la base.
        $slot = ScheduleSlot::withoutGlobalScope('school')->ofCatalog($school->id)->first();
        $this->assertSame($school->id, $slot->shift->school_id);
        $lesson = Lesson::withoutGlobalScope('school')->ofCatalog($school->id)->first();
        $this->assertSame($school->id, $lesson->book->school_id);
        $club = Club::withoutGlobalScope('school')->ofCatalog($school->id)->where('name', 'Conversation Club')->first();
        $this->assertTrue($club->books()->withoutGlobalScope('school')->get()->every(fn ($book) => $book->school_id === $school->id));
    }

    // ---- Permisos y aislamiento ------------------------------------------------

    public function test_only_super_admin_manages_the_base(): void
    {
        $this->actingAs($this->schoolAdmin($this->schoolA))->get(route('base-catalogs.index'))->assertForbidden();
        Livewire::test(ShiftsTab::class, ['schoolId' => null])->assertForbidden();

        $this->actingAs($this->superAdmin)->get(route('base-catalogs.index'))->assertOk()->assertSee('Turnos y horarios');
    }

    public function test_super_admin_is_sent_to_the_base_from_school_catalogs(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(SchoolCatalogs::class)->assertRedirect(route('base-catalogs.index'));
    }

    public function test_school_admin_only_sees_and_edits_his_own_copy(): void
    {
        $this->actingAs($this->schoolAdmin($this->schoolA));

        $this->get(route('catalogs.index'))->assertOk()->assertSee('Catálogos académicos');

        // El global scope limita cualquier consulta a su escuela.
        $this->assertSame(8, Activity::count());
        $this->assertTrue(Activity::all()->every(fn ($activity) => $activity->school_id === $this->schoolA->id));

        // No puede montar la pestaña de otra escuela ni tocar un registro ajeno por ID.
        Livewire::test(ActivitiesTab::class, ['schoolId' => $this->schoolB->id])->assertForbidden();

        $foreign = Activity::withoutGlobalScope('school')->ofCatalog($this->schoolB->id)->first();
        Livewire::test(ActivitiesTab::class, ['schoolId' => $this->schoolA->id])->call('edit', $foreign->id)->assertNotFound();
        Livewire::test(ActivitiesTab::class, ['schoolId' => $this->schoolA->id])->call('toggle', $foreign->id)->assertNotFound();
        $this->assertTrue($foreign->fresh()->is_active);
    }

    public function test_teacher_cannot_manage_catalogs(): void
    {
        $teacher = User::factory()->create(['role_id' => $this->roleId(Role::TEACHER), 'school_id' => $this->schoolA->id]);

        $this->actingAs($teacher)->get(route('catalogs.index'))->assertForbidden();
    }

    // ---- Edición sin afectar a otros --------------------------------------------

    public function test_school_changes_do_not_affect_the_base_or_other_schools(): void
    {
        $this->actingAs($this->schoolAdmin($this->schoolA));
        $mentoria = Activity::where('code', 'M')->first();

        Livewire::test(ActivitiesTab::class, ['schoolId' => $this->schoolA->id])
            ->call('edit', $mentoria->id)
            ->set('description', 'Mentoría personalizada')
            ->set('minutes', 20)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Mentoría personalizada', $mentoria->fresh()->description);
        $this->assertSame('Mentoría', Activity::withoutGlobalScope('school')->ofCatalog(null)->where('code', 'M')->value('description'));
        $this->assertSame('Mentoría', Activity::withoutGlobalScope('school')->ofCatalog($this->schoolB->id)->where('code', 'M')->value('description'));
    }

    public function test_activity_code_is_unique_within_the_catalog_only(): void
    {
        $this->actingAs($this->schoolAdmin($this->schoolA));

        Livewire::test(ActivitiesTab::class, ['schoolId' => $this->schoolA->id])
            ->call('edit')
            ->set('code', 'ba')
            ->set('description', 'Repetido')
            ->call('save')
            ->assertHasErrors(['code' => 'unique']);

        Livewire::test(ActivitiesTab::class, ['schoolId' => $this->schoolA->id])
            ->call('edit')
            ->set('code', 'RL')
            ->set('description', 'Reading log')
            ->set('minutes', 15)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(9, Activity::count());
        $this->assertSame(8, $this->catalogCount(Activity::class, $this->schoolB->id));
    }

    public function test_schedule_slot_end_must_be_after_start(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(ShiftsTab::class, ['schoolId' => null])
            ->call('editSlot')
            ->set('slotStartsAt', '10:00')
            ->set('slotEndsAt', '09:00')
            ->set('slotShiftId', Shift::ofCatalog(null)->value('id'))
            ->call('saveSlot')
            ->assertHasErrors(['slotEndsAt']);
    }

    public function test_slot_shift_must_belong_to_the_same_catalog(): void
    {
        $this->actingAs($this->schoolAdmin($this->schoolA));
        $foreignShift = Shift::withoutGlobalScope('school')->ofCatalog($this->schoolB->id)->first();

        Livewire::test(ShiftsTab::class, ['schoolId' => $this->schoolA->id])
            ->call('editSlot')
            ->set('slotStartsAt', '21:00')
            ->set('slotEndsAt', '22:00')
            ->set('slotShiftId', $foreignShift->id)
            ->call('saveSlot')
            ->assertHasErrors(['slotShiftId']);
    }

    public function test_shift_with_slots_cannot_be_deleted_but_can_be_deactivated(): void
    {
        $this->actingAs($this->schoolAdmin($this->schoolA));
        $matutino = Shift::where('name', 'Matutino')->first();

        Livewire::test(ShiftsTab::class, ['schoolId' => $this->schoolA->id])
            ->call('confirmShiftDeletion', $matutino->id)
            ->assertSet('confirmingDeletion', null)
            ->assertDispatched('toast')
            ->call('toggleShift', $matutino->id);

        $this->assertModelExists($matutino);
        $this->assertFalse($matutino->fresh()->is_active);
    }

    public function test_club_hours_are_saved_per_level(): void
    {
        $this->actingAs($this->schoolAdmin($this->schoolA));
        $books = Book::orderBy('level')->get();

        Livewire::test(ClubsTab::class, ['schoolId' => $this->schoolA->id])
            ->call('edit')
            ->set('name', 'Debate Club')
            ->set('hours', [(string) $books[0]->id => '', (string) $books[1]->id => '4', (string) $books[2]->id => '6.5'])
            ->call('save')
            ->assertHasNoErrors();

        $club = Club::where('name', 'Debate Club')->sole();
        $this->assertEquals([$books[1]->id => 4.0, $books[2]->id => 6.5], $club->books->mapWithKeys(fn ($book) => [$book->id => (float) $book->pivot->hours])->sortKeys()->all());
    }

    public function test_lessons_belong_to_the_selected_book_and_can_be_reordered(): void
    {
        $this->actingAs($this->schoolAdmin($this->schoolA));
        $beginners = Book::where('level', 1)->first();

        $component = Livewire::test(BooksTab::class, ['schoolId' => $this->schoolA->id])
            ->assertSet('selectedBookId', $beginners->id);

        $first = $beginners->lessons->first();
        $component->call('moveLesson', $first->id, 1);

        $this->assertSame('Lesson 2', $beginners->fresh()->lessons->first()->name);
    }

    // ---- Novedades del catálogo base -----------------------------------------

    public function test_school_sees_and_incorporates_base_updates_without_losing_its_changes(): void
    {
        // La escuela ajusta su copia y el Super Admin agrega un club nuevo a la base.
        Activity::withoutGlobalScope('school')->ofCatalog($this->schoolA->id)->where('code', 'BA')->update(['description' => 'Libro contestado']);
        $this->actingAs($this->superAdmin);
        Livewire::test(ClubsTab::class, ['schoolId' => null])
            ->call('edit')
            ->set('name', 'Podcast Club')
            ->call('save')
            ->assertHasNoErrors();

        $this->actingAs($this->schoolAdmin($this->schoolA));
        $page = Livewire::test(SchoolCatalogs::class)
            ->assertSee('Hay 1 novedad en el catálogo base')
            ->call('reviewUpdates')
            ->assertSee('Podcast Club')
            ->call('incorporateUpdates')
            ->assertDontSee('novedad en el catálogo base');

        $this->assertTrue(Club::where('name', 'Podcast Club')->exists());
        $this->assertSame('Libro contestado', Activity::where('code', 'BA')->value('description'));
        $this->assertFalse(Club::withoutGlobalScope('school')->ofCatalog($this->schoolB->id)->where('name', 'Podcast Club')->exists());
    }
}
