<?php

return [
    'platform_redirect' => 'Esta opción es de cada escuela. Como administrador de la plataforma, edita las escuelas desde Plataforma → Escuelas.',

    'fields' => [
        'name' => 'Nombre',
        'capacity' => 'Capacidad',
        'description' => 'Descripción',
        'type' => 'Tipo',
        'concept' => 'Concepto',
        'suggested_amount' => 'Monto sugerido',
        'starts_on' => 'Desde',
        'ends_on' => 'Hasta',
        'actions' => 'Acciones',
    ],

    'actions' => [
        'save' => 'Guardar',
        'cancel' => 'Cancelar',
    ],

    'messages' => [
        'saved' => 'Guardado.',
    ],

    'classrooms' => [
        'title' => 'Aulas',
        'subtitle' => 'Aulas del plantel donde se imparten las sesiones',
        'add' => 'Nueva aula',
        'edit' => 'Editar aula',
        'empty' => 'Aún no hay aulas',
        'empty_hint' => 'Registra las aulas de tu plantel (1, 2, 3…); se asignan en el panel de disponibilidad y en la agenda.',
        'people' => '{1} 1 persona|[2,*] :count personas',
        'capacity_hint' => 'Cuántas personas caben. El cupo de cada sesión será el menor entre este y el cupo de la escuela.',
        'description_hint' => 'Opcional: piso, ubicación o equipo (proyector, pizarrón…).',
        'club_room' => 'Aula audiovisual (para clubs)',
        'club_room_hint' => 'Se usa para programar los clubs. Puede haber más de una: a la misma hora caben tantos clubs como aulas audiovisuales.',
        'club_room_badge' => 'Audiovisual · clubs',
    ],

    'closures' => [
        'open' => 'Periodos sin disponibilidad',
        'title' => 'Periodos sin disponibilidad del aula',
        'hint' => 'En estas fechas y horas no se pueden programar clubs en esta aula.',
        'from' => 'Desde',
        'to' => 'Hasta',
        'all_day' => 'Todo el día',
        'from_hour' => 'De la hora',
        'to_hour' => 'A la hora',
        'reason' => 'Motivo',
        'add' => 'Agregar periodo',
        'empty' => 'Sin periodos registrados.',
        'delete' => 'Eliminar periodo',
        'confirm_delete' => '¿Eliminar este periodo? El aula vuelve a estar disponible para clubs.',
        'close' => 'Cerrar',
        'saved' => 'Periodo agregado.',
        'deleted' => 'Periodo eliminado.',
        'slot_order' => 'La hora final debe ser igual o posterior a la inicial.',
        'affected' => '{1} Periodo agregado. Hay 1 club programado en ese periodo: muévelo en Programación de clubs.|[2,*] Periodo agregado. Hay :count clubs programados en ese periodo: muévelos en Programación de clubs.',
    ],

    'bank_accounts' => [
        'title' => 'Cuentas bancarias',
        'subtitle' => 'Cuentas donde los alumnos depositan sus pagos',
        'add' => 'Nueva cuenta',
        'edit' => 'Editar cuenta bancaria',
        'empty' => 'Aún no hay cuentas bancarias',
        'empty_hint' => 'Agrega las cuentas de tu escuela para elegirlas en la ficha de inscripción.',
        'name' => 'Nombre corto',
        'name_hint' => 'Cómo se verá al elegirla, p. ej. "BBVA principal".',
        'bank' => 'Banco',
        'account_holder' => 'Titular',
        'account_number' => 'Número de cuenta',
        'account_short' => 'Cuenta :number',
        'account_number_invalid' => 'El número de cuenta solo lleva dígitos (4 a 30).',
    ],

    'payment_methods' => [
        'title' => 'Métodos de pago',
        'subtitle' => 'Formas en que tu escuela recibe pagos',
        'add' => 'Nuevo método',
        'edit' => 'Editar método de pago',
        'empty' => 'Aún no hay métodos de pago',
        'empty_hint' => 'Agrega, por ejemplo: Efectivo, Transferencia, Tarjeta de crédito/débito, Cheque.',
        'name_hint' => 'Por ejemplo: Efectivo, Transferencia, Tarjeta de débito.',
        'requires_reference' => 'Pedir referencia al registrar el pago',
        'requires_reference_hint' => 'Folio de transferencia, autorización de tarjeta o número de cheque.',
        'requires_reference_short' => 'Pide referencia',
    ],

    'charge_concepts' => [
        'title' => 'Conceptos de cobro',
        'subtitle' => 'Lo que tu escuela cobra y su monto sugerido',
        'add' => 'Nuevo concepto',
        'edit' => 'Editar concepto',
        'empty' => 'Aún no hay conceptos de cobro',
        'empty_hint' => 'Agrega, por ejemplo: Colegiatura mensual, Inscripción, Libro B1.',
        'name_hint' => 'Por ejemplo: Colegiatura mensual, Inscripción, Libro Beginners.',
        'amount_hint' => 'Se propone al cobrar; se puede cambiar en cada cargo. Déjalo vacío si varía.',
    ],

    'concept_types' => [
        'tuition' => 'Colegiatura',
        'enrollment' => 'Inscripción',
        'material' => 'Libro / material',
        'extra_activity' => 'Actividad extra',
        'extension' => 'Convenio de extensión',
        'late_fee' => 'Recargo',
        'other' => 'Otro',
    ],

    'holidays' => [
        'title' => 'Días festivos',
        'subtitle' => 'Días sin clase: no se agendan sesiones ni cuentan para la asistencia',
        'add' => 'Nuevo día sin clase',
        'edit' => 'Editar día sin clase',
        'empty' => 'No hay días sin clase en este año',
        'previous_year' => 'Año anterior',
        'next_year' => 'Año siguiente',
        'official_count' => '{0} Sin festivos oficiales|{1} 1 festivo oficial|[2,*] :count festivos oficiales',
        'range' => 'Del :from al :to (:days días)',
        'name_hint' => 'Por ejemplo: Vacaciones de verano, Aniversario de la escuela.',
        'ends_hint' => 'Déjalo igual que "Desde" si es un solo día.',
        'max_range' => 'Un periodo puede durar como máximo un año.',
        'legend' => 'Los festivos oficiales (Ley Federal del Trabajo) se agregan solos cada año; puedes desactivar alguno si tu escuela sí da clase ese día.',
    ],

    'holiday_types' => [
        'official' => 'Oficial',
        'school' => 'De la escuela',
    ],

    'official_holidays' => [
        'new_year' => 'Año Nuevo',
        'constitution' => 'Día de la Constitución',
        'benito_juarez' => 'Natalicio de Benito Juárez',
        'labor_day' => 'Día del Trabajo',
        'independence' => 'Día de la Independencia',
        'executive_transition' => 'Transmisión del Poder Ejecutivo Federal',
        'revolution' => 'Día de la Revolución',
        'christmas' => 'Navidad',
    ],
];
