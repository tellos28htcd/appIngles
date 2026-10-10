<?php

namespace Database\Seeders;

use App\Enums\RoleScope;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Roles base del catálogo. Solo crea los que faltan: el nombre, la
 * descripción y el estado se editan desde Plataforma → Roles y permisos.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [Role::PLATFORM_ADMIN, 'Administrador plataforma', RoleScope::Platform, 'Dueño del SaaS: da de alta escuelas y opera fuera de ellas.'],
            [Role::SCHOOL_ADMIN, 'Administrador escuela', RoleScope::School, 'Director o dueño de la academia: configura la escuela y su personal.'],
            [Role::STUDENT_SERVICES, 'Atención al alumno', RoleScope::School, 'Atiende dudas, trámites y seguimiento de alumnos y tutores.'],
            [Role::ACADEMIC_COORDINATOR, 'Coordinador académico', RoleScope::School, 'Supervisa niveles, avance, evaluaciones y teachers.'],
            [Role::TEACHER, 'Teacher', RoleScope::School, 'Imparte clases, pasa lista y evalúa a los alumnos de sus sesiones.'],
            [Role::MENTOR, 'Mentor / Administrador', RoleScope::School, 'Acompaña a los alumnos y apoya en la administración.'],
            [Role::RECEPTION, 'Recepción', RoleScope::School, 'Agenda reservaciones, registra pagos y atiende en mostrador.'],
            [Role::GUARDIAN, 'Tutor', RoleScope::School, 'Padre, madre o tutor: consulta el avance y los pagos de sus alumnos.'],
            [Role::STUDENT, 'Alumno', RoleScope::School, 'Consulta su avance en las lecciones, agenda clases y clubes, y revisa su estado de cuenta.'],
        ];

        foreach ($roles as $order => [$slug, $name, $scope, $description]) {
            Role::firstOrNew(['slug' => $slug], [
                'name' => $name,
                'description' => $description,
                'sort_order' => ($order + 1) * 10,
                'is_active' => true,
            ])->forceFill(['scope' => $scope, 'is_system' => true])->save();
        }
    }
}
