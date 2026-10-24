<?php

return [
    'title' => 'Horas Coordinador',
    'subtitle' => 'Plan mensual de las actividades de coordinación',
    'month_totals' => 'Programadas :programmed h de :authorized h autorizadas',
    'authorized' => 'Horas autorizadas al mes',
    'programmed' => 'Horas programadas en el mes',
    'all_match' => 'Todo coincide',
    'not_matching' => 'Aún no coincide',
    'no_activities' => 'No hay actividades activas: agrégalas en Catálogos académicos → Actividades de coordinación.',
    'uses_teacher_hour' => 'Ocupa la hora del teacher (se marca en el panel de disponibilidad)',
    'coordination_option' => 'Actividad Coordinación',
    'coordination_short' => 'Coordinación',
    'choose_activity_first' => 'Primero elige la actividad',
    'teacher_hint' => 'Revisión de Planeaciones: teachers con esa hora libre. Evaluaciones y demás: el teacher que está trabajando a esa hora. Programación de clubs y juntas: "Actividad Coordinación".',
    'repeat_hint' => 'Misma actividad (y teacher) el mismo día y hora; si alguna semana no se puede, se avisa.',
    'add' => 'Programar actividad',
    'edit' => 'Actividad programada',
    'add_at' => 'Programar actividad el :day a las :hour',
    'delete' => 'Eliminar actividad',
    'confirm_delete' => '¿Eliminar esta hora del coordinador?',
    'save' => 'Guardar actividad',

    'state' => [
        'match' => 'Coincide con las horas autorizadas',
        'over' => 'Pasa de las horas autorizadas',
        'under' => 'Faltan horas por programar',
    ],

    'fields' => [
        'activity' => 'Actividad',
        'teacher' => 'Teacher',
    ],

    'summary' => [
        'teacher' => 'Resumen por teacher',
        'activity' => 'Resumen por actividad',
        'empty' => 'Sin horas programadas.',
    ],

    'validation' => [
        'activity' => 'Elige una actividad activa.',
        'slot' => 'Esa hora no se trabaja ese día.',
        'holiday' => 'Es día festivo.',
        'busy' => 'Ya hay una actividad del coordinador a esa hora.',
        'teacher' => 'El teacher no tiene esa hora libre (horario base, incidencias, club u otra actividad).',
        'teacher_working' => 'El teacher no está trabajando a esa hora (horario base o incidencias).',
        'over' => 'No se pueden agregar más horas a ":activity": ya llegó a sus :hours h autorizadas del mes.',
    ],

    'messages' => [
        'saved' => 'Actividad programada.',
        'saved_with_skips' => 'Actividad programada. No se pudo repetir en: :days',
        'deleted' => 'Actividad eliminada.',
    ],
];
