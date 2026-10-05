<?php

return [
    'title' => 'Usuarios',
    'subtitle' => 'Personal con acceso al sistema',
    'subtitle_platform' => 'Usuarios de todas las escuelas',
    'new' => 'Nuevo usuario',
    'create_title' => 'Nuevo usuario',
    'edit_title' => 'Editar usuario',
    'search' => 'Buscar por nombre o correo',
    'no_results' => 'No hay usuarios con estos filtros',
    'no_results_hint' => 'Prueba con otra escuela, rol o estado, o limpia los filtros.',
    'clear_filters' => 'Limpiar filtros',
    'per_page' => 'Registros por página',
    'all_schools' => 'Todas las escuelas',
    'all_roles' => 'Todos los roles',
    'all_statuses' => 'Todos',
    'no_school' => 'Plataforma',
    'never' => 'Nunca',
    'you' => 'Tú',
    'pending_password' => 'Invitación pendiente',

    'status' => [
        'active' => 'Activo',
        'inactive' => 'Inactivo',
    ],

    'columns' => [
        'user' => 'Usuario',
        'role' => 'Rol',
        'school' => 'Escuela',
        'status' => 'Estado',
        'last_login' => 'Último acceso',
        'actions' => 'Acciones',
    ],

    'sections' => [
        'personal' => 'Datos del usuario',
        'access' => 'Acceso',
        'access_hint' => 'Al guardar, el usuario recibirá un correo para crear su contraseña.',
    ],

    'fields' => [
        'first_name' => 'Nombre(s)',
        'last_name' => 'Primer apellido',
        'second_last_name' => 'Segundo apellido',
        'email' => 'Correo',
        'role_id' => 'Rol',
        'school_id' => 'Escuela',
        'notes' => 'Observaciones',
        'select_role' => 'Selecciona un rol',
        'select_school' => 'Selecciona una escuela',
        'platform_no_school' => 'El Administrador plataforma no pertenece a ninguna escuela.',
    ],

    'actions' => [
        'edit' => 'Editar',
        'activate' => 'Activar',
        'deactivate' => 'Desactivar',
        'delete' => 'Eliminar',
        'resend' => 'Reenviar invitación',
        'save' => 'Guardar usuario',
        'cancel' => 'Cancelar',
        'more' => 'Más acciones para :name',
    ],

    'messages' => [
        'created' => 'Usuario creado. Le enviamos un correo para crear su contraseña.',
        'updated' => 'Usuario actualizado.',
        'activated' => 'Usuario activado.',
        'deactivated' => 'Usuario desactivado. Ya no puede iniciar sesión.',
        'deleted' => 'Usuario eliminado.',
        'invitation_sent' => 'Invitación reenviada a :email.',
        'cannot_delete' => 'Este usuario tiene registros en el sistema; solo se puede desactivar.',
    ],

    'confirm_delete' => [
        'title' => '¿Eliminar a :name?',
        'body' => 'Se eliminará de forma definitiva. Esta acción no se puede deshacer.',
        'confirm' => 'Eliminar usuario',
    ],

    'confirm_deactivate' => [
        'title' => '¿Desactivar a :name?',
        'body' => 'No podrá iniciar sesión hasta que lo actives de nuevo. Su información se conserva.',
        'confirm' => 'Desactivar',
    ],

    'validation' => [
        'school_required' => 'Elige la escuela a la que pertenece el usuario.',
        'school_forbidden' => 'El Administrador plataforma no puede pertenecer a una escuela.',
        'role_forbidden' => 'No puedes asignar este rol.',
        'own_role' => 'No puedes cambiar tu propio rol.',
        'email_taken' => 'Este correo ya está registrado en la plataforma. Cada usuario necesita un correo distinto.',
    ],
];
