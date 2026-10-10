<?php

return [
    'title' => 'Teachers',
    'subtitle' => 'Profesores de la escuela, su contratación y su acceso al sistema',
    'new' => 'Nuevo teacher',
    'create_title' => 'Nuevo teacher',
    'create_subtitle' => 'Al guardar se crea su acceso y recibe un correo para crear su contraseña.',
    'edit_title' => 'Editar teacher',
    'search' => 'Buscar por nombre, correo o CURP',
    'all_statuses' => 'Todos los estatus',
    'empty' => 'Aún no hay teachers',
    'empty_hint' => 'Da de alta a los profesores de tu escuela; cada uno recibirá su acceso por correo.',
    'incomplete' => 'Datos incompletos',
    'hours_per_week' => '{1} 1 h por semana|[2,*] :count h por semana',
    'hours_max' => '{1} máx. 1 h/sem|[2,*] máx. :count h/sem',
    'hours_assigned' => 'horas asignadas (máx. 48 h/sem)',

    'status' => [
        'active' => 'Activo',
        'leave' => 'Licencia',
        'paid_leave' => 'Permiso con goce',
        'unpaid_leave' => 'Permiso sin goce',
        'terminated' => 'Baja',
    ],

    'contract_types' => [
        'full_time' => 'Tiempo completo',
        'part_time' => 'Medio tiempo',
        'hourly' => 'Hora clase',
    ],

    'sections' => [
        'personal' => 'Datos personales',
        'address' => 'Domicilio',
        'contract' => 'Contratación',
        'contract_hint' => 'El tope de horas por semana nunca se podrá rebasar al asignarle clases en la agenda.',
        'access' => 'Acceso al sistema',
        'access_hint' => 'Con este correo entrará al sistema. Al guardar recibirá un enlace para crear su contraseña.',
        'access_hint_edit' => 'Si cambias el correo, con el nuevo inicia sesión. Solo un teacher Activo puede entrar.',
    ],

    'fields' => [
        'school_id' => 'Escuela',
        'first_name' => 'Nombre(s)',
        'last_name' => 'Apellido paterno',
        'second_last_name' => 'Apellido materno',
        'curp' => 'CURP',
        'rfc' => 'RFC',
        'optional' => 'Opcional',
        'email' => 'Correo',
        'photo' => 'Foto',
        'photo_choose' => 'Elegir foto',
        'photo_remove' => 'Quitar foto',
        'photo_hint' => 'JPG, PNG o WebP, máximo 2 MB.',
        'street' => 'Calle',
        'exterior_number' => 'Núm. ext.',
        'interior_number' => 'Núm. int.',
        'neighborhood' => 'Colonia',
        'postal_code' => 'Código postal',
        'state_id' => 'Entidad federativa',
        'municipality_id' => 'Municipio',
        'contract_type' => 'Tipo de contratación',
        'weekly_hours' => 'Horas máximas por semana',
        'weekly_hours_fixed' => 'Fijo según el tipo de contratación.',
        'weekly_hours_hint' => 'Horas asignadas; nunca más de 48 por semana.',
        'status' => 'Estatus',
        'status_hint' => 'Solo un teacher Activo inicia sesión y recibe clases.',
    ],

    'actions' => [
        'save' => 'Guardar teacher',
        'cancel' => 'Cancelar',
        'edit' => 'Editar a :name',
        'edit_short' => 'Editar',
        'delete' => 'Eliminar a :name',
        'delete_short' => 'Eliminar',
    ],

    'messages' => [
        'created' => 'Teacher registrado. Le enviamos un correo para crear su contraseña.',
        'created_mail_failed' => 'Teacher registrado, pero no se pudo enviar el correo. Revisa la configuración de correo y usa "Reenviar invitación".',
        'updated' => 'Teacher actualizado.',
        'deleted' => 'Teacher eliminado.',
    ],

    'confirm_delete' => [
        'title' => '¿Eliminar a :name?',
        'body' => 'Se eliminará el teacher y su acceso al sistema. Si solo dejará de trabajar, mejor cambia su estatus a Baja para conservar su historial.',
        'confirm' => 'Eliminar teacher',
    ],

    'validation' => [
        'curp' => 'La CURP debe tener 18 caracteres con el formato oficial (p. ej. GOMA850101HQTRRN09).',
        'rfc' => 'El RFC de persona física tiene 13 caracteres (p. ej. GOMA850101AB1).',
        'weekly_hours' => 'Un teacher nunca puede tener más de 48 horas por semana.',
    ],
];
