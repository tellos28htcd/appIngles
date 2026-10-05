<?php

return [
    'title' => 'Menú',
    'subtitle' => 'Módulos y submódulos del sistema: agrega, renombra, ordena o desactiva',
    'help' => 'Desactivar un módulo lo oculta para todos los usuarios (también para ti) y bloquea sus pantallas. Un módulo nuevo aparece como "Próximamente" hasta que su pantalla esté programada. Qué rol ve cada opción se define en Roles y permisos.',
    'new_module' => 'Nuevo módulo',
    'new_submodule' => 'Agregar submódulo',
    'create_module_title' => 'Nuevo módulo',
    'create_submodule_title' => 'Nuevo submódulo en :parent',
    'save' => 'Guardar nombres y orden',
    'custom' => 'Agregado',
    'always_on' => 'Siempre activo',
    'protected' => 'Inicio, Plataforma y Menú no se pueden desactivar para no perder el acceso.',

    'status' => [
        'disabled' => 'Desactivado',
    ],

    'fields' => [
        'label' => 'Nombre en el menú',
        'icon' => 'Ícono',
        'route' => 'Pantalla',
        'route_none' => 'Ninguna todavía (Próximamente)',
        'route_hint' => 'Solo aparecen pantallas ya programadas que aún no están en el menú.',
        'roles' => '¿Qué roles lo ven?',
        'roles_hint' => 'El Administrador plataforma siempre ve todo. Puedes cambiarlo después en Roles y permisos.',
    ],

    'actions' => [
        'create' => 'Agregar',
        'cancel' => 'Cancelar',
        'enable' => 'Activar :name',
        'disable' => 'Desactivar :name',
        'up' => 'Subir :name',
        'down' => 'Bajar :name',
        'delete' => 'Eliminar :name',
    ],

    'messages' => [
        'saved' => 'Nombres y orden del menú guardados.',
        'created' => 'Opción agregada al menú.',
        'deleted' => 'Opción eliminada del menú.',
        'enabled' => ':name activado.',
        'disabled' => ':name desactivado para todos los usuarios.',
    ],

    'confirm_delete' => [
        'title' => '¿Eliminar :name del menú?',
        'body' => 'Se quitará del menú y de los permisos de todos los roles. Esta acción no se puede deshacer.',
        'confirm' => 'Eliminar',
    ],

    'icons' => [
        'home' => 'Inicio',
        'calendar' => 'Calendario',
        'student' => 'Alumno',
        'users' => 'Personas',
        'clipboard' => 'Lista',
        'receipt' => 'Recibo',
        'chart' => 'Gráfica',
        'book' => 'Libro',
        'chat' => 'Mensajes',
        'mail' => 'Correo',
        'heart' => 'Favorito',
        'building' => 'Edificio',
        'settings' => 'Ajustes',
        'sparkles' => 'Destacado',
    ],
];
