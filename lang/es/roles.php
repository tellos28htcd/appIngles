<?php

return [
    'title' => 'Roles y permisos',
    'subtitle' => 'Qué puede ver y hacer cada rol en el sistema (igual para todas las escuelas)',
    'new' => 'Nuevo rol',
    'create_title' => 'Nuevo rol',
    'edit_title' => 'Editar rol',
    'editor_subtitle' => 'Datos del rol y los módulos a los que tiene acceso',
    'custom' => 'Personalizado',
    'full_access' => 'Todo',
    'locked' => 'Acceso total, no editable',
    'always' => 'Siempre incluido',
    'select_all' => 'Marcar todo',
    'select_none' => 'Quitar todo',
    'selected_count' => ':count de :total opciones asignadas',

    'scope' => [
        'platform' => 'Plataforma',
        'school' => 'Escuela',
    ],

    'status' => [
        'active' => 'Activo',
        'inactive' => 'Inactivo',
    ],

    'columns' => [
        'users' => 'Usuarios',
        'modules' => 'Opciones del menú',
    ],

    'sections' => [
        'data' => 'Datos del rol',
        'access' => 'Módulos y submódulos',
        'access_hint' => 'Marca lo que este rol puede ver en el menú y usar. Al marcar un módulo se marcan todos sus submódulos. Lo que está en "Próximamente" se habilitará solo cuando el módulo esté listo.',
    ],

    'fields' => [
        'name' => 'Nombre',
        'description' => 'Descripción',
        'is_active' => 'Rol activo',
        'is_active_hint' => 'Los usuarios de un rol inactivo no pueden iniciar sesión.',
    ],

    'actions' => [
        'edit' => 'Editar y asignar módulos',
        'activate' => 'Activar',
        'deactivate' => 'Desactivar',
        'delete' => 'Eliminar',
        'save' => 'Guardar rol',
        'cancel' => 'Cancelar',
    ],

    'messages' => [
        'saved' => 'Rol guardado. Los cambios ya aplican al menú de sus usuarios.',
        'activated' => 'Rol activado.',
        'deactivated' => 'Rol desactivado. Sus usuarios ya no pueden iniciar sesión.',
        'deleted' => 'Rol eliminado.',
    ],

    'confirm_delete' => [
        'title' => '¿Eliminar el rol :name?',
        'body' => 'El rol no tiene usuarios. Se eliminará junto con su asignación de módulos.',
        'confirm' => 'Eliminar rol',
    ],

];
