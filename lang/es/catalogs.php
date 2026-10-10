<?php

return [
    'base_title' => 'Catálogos base',
    'base_subtitle' => 'Lo que recibe cada escuela al darse de alta: catálogos académicos y de configuración. Los cambios aquí no modifican lo que cada escuela ya ajustó; le llegan como novedades.',
    'school_title' => 'Catálogos académicos',
    'school_subtitle' => 'Turnos, horarios, libros, lecciones, actividades y clubes de tu escuela. Puedes ajustarlos sin afectar a otras escuelas.',
    'empty' => 'Aún no hay registros',

    'tabs' => [
        'horarios' => 'Turnos y horarios',
        'libros' => 'Libros y lecciones',
        'actividades' => 'Actividades',
        'clubes' => 'Clubes',
        'salones' => 'Salones',
        'metodos' => 'Métodos de pago',
        'conceptos' => 'Conceptos de cobro',
        'dias' => 'Días sin clase',
    ],

    'shifts' => [
        'title' => 'Turnos',
        'description' => 'Matutino, vespertino, sabatino…',
        'add' => 'Nuevo turno',
        'edit' => 'Editar turno',
        'slots_count' => '{0} Sin horarios|{1} 1 horario|[2,*] :count horarios',
    ],

    'slots' => [
        'title' => 'Horarios',
        'description' => 'Bloques numerados con su hora de inicio y fin, y el turno al que pertenecen.',
        'add' => 'Nuevo horario',
        'edit' => 'Editar horario',
    ],

    'books' => [
        'title' => 'Libros (niveles)',
        'description' => 'Elige un libro para ver sus lecciones.',
        'add' => 'Nuevo libro',
        'edit' => 'Editar libro',
        'lessons_count' => '{0} Sin lecciones|{1} 1 lección|[2,*] :count lecciones',
    ],

    'lessons' => [
        'title' => 'Lecciones',
        'title_for' => 'Lecciones de :book',
        'description' => 'En el orden en que el alumno las cursa. El número de actividad identifica cada lección.',
        'add' => 'Nueva lección',
        'edit' => 'Editar lección',
        'select_book' => 'Elige un libro para ver sus lecciones',
    ],

    'lesson_types' => [
        'lesson' => 'Lección',
        'checkpoint' => 'Check Point',
        'verb_in_context' => 'Verb in context',
    ],

    'activities' => [
        'title' => 'Actividades',
        'description' => 'Códigos con los que el teacher evalúa cada clase.',
        'add' => 'Nueva actividad',
        'edit' => 'Editar actividad',
        'minutes_value' => ':minutes min',
    ],

    'clubs' => [
        'title' => 'Clubes',
        'description' => 'Horas que cuenta cada club según el nivel del alumno.',
        'add' => 'Nuevo club',
        'edit' => 'Editar club',
        'club' => 'Club',
        'level_short' => 'L:level',
        'level_label' => 'L:level · :name',
        'hours_by_level' => 'Horas por nivel',
        'hours_hint' => 'Deja vacío el nivel en el que no se ofrece el club.',
        'not_offered' => 'No se ofrece en este nivel',
        'legend' => 'Horas por nivel (L1, L2, L3…). "—" = el club no se ofrece en ese nivel.',
    ],

    'fields' => [
        'name' => 'Nombre',
        'number' => 'Número',
        'number_short' => 'Núm.',
        'activity_number' => 'Número de actividad',
        'starts_at' => 'Desde',
        'ends_at' => 'Hasta',
        'schedule' => 'Horario',
        'shift' => 'Turno',
        'select_shift' => 'Selecciona un turno',
        'level' => 'Nivel',
        'lesson' => 'Lección',
        'type' => 'Tipo',
        'order' => 'Orden',
        'code' => 'Código',
        'description' => 'Descripción',
        'minutes' => 'Minutos',
        'hours' => 'Horas',
        'actions' => 'Acciones',
    ],

    'actions' => [
        'add' => 'Agregar',
        'save' => 'Guardar',
        'cancel' => 'Cancelar',
        'edit' => 'Editar :name',
        'edit_short' => 'Editar',
        'delete' => 'Eliminar :name',
        'delete_short' => 'Eliminar',
        'activate' => 'Activar :name',
        'deactivate' => 'Desactivar :name',
        'up' => 'Subir :name',
        'down' => 'Bajar :name',
    ],

    'messages' => [
        'saved' => 'Guardado.',
        'activated' => 'Activado.',
        'deactivated' => 'Desactivado. Ya no se ofrecerá para nuevos registros.',
        'deleted' => 'Eliminado.',
        'in_use' => 'No se puede eliminar porque está en uso. Puedes desactivarlo.',
    ],

    'confirm_delete' => [
        'title' => '¿Eliminar este registro?',
        'body' => 'Se eliminará del catálogo. Esta acción no se puede deshacer; si solo quieres dejar de usarlo, desactívalo.',
        'confirm' => 'Eliminar',
    ],

    'updates' => [
        'title' => '{1} Hay 1 novedad en el catálogo base|[2,*] Hay :count novedades en el catálogo base',
        'body' => 'Revísalas y elige cuáles incorporar a tu escuela. Lo que ya ajustaste no se modifica.',
        'review' => 'Revisar novedades',
        'modal_title' => 'Novedades del catálogo base',
        'modal_body' => 'Marca lo que quieres incorporar a los catálogos de tu escuela.',
        'incorporate' => 'Incorporar seleccionadas',
        'incorporate_all' => 'Incorporar',
        'incorporated' => '{0} No se incorporó nada.|{1} Se incorporó 1 registro.|[2,*] Se incorporaron :count registros.',
        'catalogs' => [
            'shifts' => 'Turnos',
            'schedule_slots' => 'Horarios',
            'books' => 'Libros',
            'lessons' => 'Lecciones',
            'activities' => 'Actividades',
            'clubs' => 'Clubes',
            'classrooms' => 'Salones',
            'payment_methods' => 'Métodos de pago',
            'charge_concepts' => 'Conceptos de cobro',
            'holidays' => 'Días sin clase',
        ],
    ],

    'validation' => [
        'code' => 'El código solo lleva letras mayúsculas y números, sin espacios (p. ej. BA, 1P).',
    ],
];
