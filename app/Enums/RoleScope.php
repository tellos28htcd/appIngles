<?php

namespace App\Enums;

enum RoleScope: string
{
    /** Opera fuera de cualquier escuela (dueño del SaaS). */
    case Platform = 'platform';

    /** Pertenece a una escuela (tenant). */
    case School = 'school';
}
