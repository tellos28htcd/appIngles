<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\TeacherPhotoController;
use App\Livewire\Audit\AuditLogIndex;
use App\Livewire\Auth\AcceptInvitation;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Catalogs\BaseCatalogs;
use App\Livewire\Catalogs\SchoolCatalogs;
use App\Livewire\Dashboard;
use App\Livewire\Menu\MenuManager;
use App\Livewire\Roles\RoleEditor;
use App\Livewire\Roles\RoleIndex;
use App\Livewire\Schools\SchoolEditor;
use App\Livewire\Schools\SchoolIndex;
use App\Livewire\Settings\ChargeConceptIndex;
use App\Livewire\Settings\ClassroomIndex;
use App\Livewire\Settings\HolidayIndex;
use App\Livewire\Settings\MySchool;
use App\Livewire\Settings\PaymentMethodIndex;
use App\Livewire\Teachers\TeacherEditor;
use App\Livewire\Teachers\TeacherIndex;
use App\Livewire\Users\UserEditor;
use App\Livewire\Users\UserIndex;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/inicio');

Route::middleware('guest')->group(function () {
    Route::livewire('/login', Login::class)->name('login');
    Route::livewire('/recuperar-contrasena', ForgotPassword::class)->name('password.request');
});

// Enlaces que llegan por correo: se abren aunque haya otra sesión iniciada (la pantalla ofrece cerrarla).
Route::livewire('/restablecer-contrasena/{token}', ResetPassword::class)->name('password.reset');
Route::livewire('/crear-contrasena/{token}', AcceptInvitation::class)->name('invitation.accept');

Route::middleware(['auth', 'auth.session', 'active', 'menu.access'])->group(function () {
    Route::livewire('/inicio', Dashboard::class)->name('dashboard');

    Route::livewire('/escuelas', SchoolIndex::class)->name('schools.index');
    Route::livewire('/escuelas/nueva', SchoolEditor::class)->name('schools.create');
    Route::livewire('/escuelas/{school}/editar', SchoolEditor::class)->name('schools.edit');

    Route::livewire('/roles', RoleIndex::class)->name('roles.index');
    Route::livewire('/roles/nuevo', RoleEditor::class)->name('roles.create');
    Route::livewire('/roles/{role}/editar', RoleEditor::class)->name('roles.edit');

    Route::livewire('/menu', MenuManager::class)->name('menu.index');

    // Bitácora: Plataforma (todas las escuelas) y Configuración (solo la escuela del usuario).
    Route::livewire('/bitacora', AuditLogIndex::class)->name('audit-logs.index');
    Route::livewire('/configuracion/bitacora', AuditLogIndex::class)->name('school-audit-logs.index');

    Route::livewire('/catalogos-base', BaseCatalogs::class)->name('base-catalogs.index');
    Route::livewire('/configuracion/catalogos', SchoolCatalogs::class)->name('catalogs.index');
    Route::livewire('/configuracion/mi-escuela', MySchool::class)->name('my-school.edit');
    Route::livewire('/configuracion/salones', ClassroomIndex::class)->name('classrooms.index');
    Route::livewire('/configuracion/metodos-de-pago', PaymentMethodIndex::class)->name('payment-methods.index');
    Route::livewire('/configuracion/conceptos-de-cobro', ChargeConceptIndex::class)->name('charge-concepts.index');
    Route::livewire('/configuracion/dias-festivos', HolidayIndex::class)->name('holidays.index');

    Route::livewire('/teachers', TeacherIndex::class)->name('teachers.index');
    Route::livewire('/teachers/nuevo', TeacherEditor::class)->name('teachers.create');
    Route::livewire('/teachers/{teacher}/editar', TeacherEditor::class)->name('teachers.edit');

    Route::livewire('/usuarios', UserIndex::class)->name('users.index');
    Route::livewire('/usuarios/nuevo', UserEditor::class)->name('users.create');
    Route::livewire('/usuarios/{user}/editar', UserEditor::class)->name('users.edit');
});

// Foto privada del teacher: la ve quien lo administra y el propio teacher (fuera del control por menú).
Route::get('/fotos/teachers/{teacher}', TeacherPhotoController::class)
    ->middleware(['auth', 'auth.session', 'active'])
    ->name('teacher-photos.show');

Route::post('/logout', LogoutController::class)->middleware('auth')->name('logout');
