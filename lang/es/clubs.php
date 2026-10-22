<?php

return [
    'title' => 'Programación de clubs',
    'subtitle' => 'Plan mensual: club, teacher y modalidad en cada hora',
    'previous_month' => 'Mes anterior',
    'next_month' => 'Mes siguiente',
    'hour' => 'Hora',
    'rooms_count' => '{0} Sin aula audiovisual|{1} Aula audiovisual: :names|[2,*] Aulas audiovisuales: :names',
    'sessions_count' => '{0} sin clubs programados|{1} 1 club programado|[2,*] :count clubs programados',
    'no_rooms' => 'No hay aula audiovisual para clubs',
    'no_rooms_hint' => 'Marca en Configuración → Aulas cuál es el aula audiovisual (para clubs).',
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
    'repeat_hint' => 'Mismo día y hora; si alguna semana no se puede, se avisa.',

    'fields' => [
        'code' => 'Club',
        'teacher' => 'Teacher',
        'modality' => 'Modalidad',
        'room' => 'Aula audiovisual',
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
        'no_room' => 'No hay aula audiovisual libre a esa hora (ocupada o en periodo sin disponibilidad).',
        'room_taken' => 'Esa aula audiovisual no está libre a esa hora.',
    ],

    'messages' => [
        'saved' => 'Club programado.',
        'saved_with_skips' => 'Club programado. No se pudo repetir en: :days',
        'deleted' => 'Club eliminado.',
    ],
];
