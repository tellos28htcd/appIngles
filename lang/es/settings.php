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
        'title' => 'Salones',
        'subtitle' => 'Aulas del plantel donde se imparten las sesiones',
        'add' => 'Nuevo salón',
        'edit' => 'Editar salón',
        'empty' => 'Aún no hay salones',
        'empty_hint' => 'Registra las aulas de tu plantel para asignarlas a las sesiones de la agenda.',
        'people' => '{1} 1 persona|[2,*] :count personas',
        'capacity_hint' => 'Cuántas personas caben. El cupo de cada sesión será el menor entre este y el cupo de la escuela.',
        'description_hint' => 'Opcional: piso, ubicación o equipo (proyector, pizarrón…).',
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
