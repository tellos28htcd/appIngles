<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Navigation;

/**
 * Bitácora (solo consulta; nadie la edita ni la borra).
 * - Plataforma: solo el Super Administrador, con todas las escuelas.
 * - Configuración: quien tenga la opción en Roles y permisos, y solo de su escuela.
 */
class AuditLogPolicy
{
    public function viewPlatform(User $actor): bool
    {
        return $actor->isPlatformAdmin();
    }

    public function viewSchool(User $actor): bool
    {
        return $actor->school_id !== null && Navigation::allows($actor, 'school-audit-logs.index');
    }
}
