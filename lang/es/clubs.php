<?php

return [
    'title' => 'Programación de clubs',
    'subtitle' => 'Plan mensual: club, teacher y modalidad en cada hora',
    'previous_month' => 'Mes anterior',
    'next_month' => 'Mes siguiente',
    'hour' => 'Hora',
    'physical_rooms' => '{0} Sin aula audiovisual física|{1} Aula audiovisual: :names|[2,*] Aulas audiovisuales: :names',
    'virtual_rooms' => '{0} sin aula virtual|{1} aula virtual: :names|[2,*] aulas virtuales: :names',
    'sessions_count' => '{0} sin clubs programados|{1} 1 club programado|[2,*] :count clubs programados',
    'no_rooms' => 'No hay aulas para clubs',
    'no_rooms_hint' => 'En Configuración → Aulas marca el aula audiovisual (para clubs) para los presenciales y registra aulas virtuales para los en línea.',
    'no_free_room' => 'No hay aula libre de esta modalidad',
    'no_virtual_hint' => 'Cada club en línea usa un aula virtual: registra las que necesites en Configuración → Aulas (marca "Aula virtual").',
    'reserved' => 'Reservada para otra actividad',
    'save' => 'Guardar club',
    'room' => 'Aula :name',
    'any_room' => 'Cualquier aula libre',
    'orphan' => 'el teacher ya no tiene esta hora en el panel (revísalo)',
    'add' => 'Programar club',
    'edit' => 'Club programado',
    'add_at' => 'Programar club el :day a las :hour',
    'delete' => 'Eliminar club',
    'confirm_delete' => '¿Eliminar este club? La hora del teacher vuelve a quedar libre para clases.',
    'no_teachers' => 'Ningún teacher tiene libre esta hora',
    'teacher_hint' => 'Solo aparecen quienes tienen esta hora en su horario base o la cubren por incidencia.',
    'repeat' => 'Repetir en las semanas siguientes del mes',
    'repeat_hint' => 'Mismo club, teacher y modalidad el mismo día y hora; si alguna semana no se puede, se avisa.',

    'summary' => [
        'teacher' => 'Resumen por teacher',
        'type' => 'Resumen por tipo',
        'previous' => 'mes anterior',
        'hours' => 'Horas',
        'total' => 'Total',
        'empty' => 'Sin clubs programados.',
    ],

    'fields' => [
        'code' => 'Club',
        'teacher' => 'Teacher',
        'modality' => 'Modalidad',
        'room' => 'Aula audiovisual',
        'virtual_room' => 'Aula virtual',
    ],

    'modality' => [
        'in_person' => 'Presencial',
        'online' => 'En línea',
    ],

    'validation' => [
        'code' => 'Elige un club (abreviatura) activo.',
        'slot' => 'Esa hora no se trabaja ese día.',
        'holiday' => 'Es día festivo: no se programan clubs.',
        'teacher' => 'El teacher no tiene esa hora libre (horario base, incidencias u otro club).',
        'no_room_in_person' => 'No hay aula audiovisual física libre a esa hora (ocupada o reservada para otra actividad).',
        'no_room_online' => 'No hay aula virtual libre a esa hora: registra otra en Configuración → Aulas (marca "Aula virtual").',
        'room_taken' => 'Esa aula audiovisual no está libre a esa hora.',
    ],

    'messages' => [
        'saved' => 'Club programado.',
        'saved_with_skips' => 'Club programado. No se pudo repetir en: :days',
        'deleted' => 'Club eliminado.',
    ],
];
