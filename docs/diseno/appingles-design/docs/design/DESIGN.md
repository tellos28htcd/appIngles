# AppIngles · Sistema de diseño (handoff para Claude Code)

SaaS multiempresa (white-label) para academias de inglés. Stack: Laravel + Livewire + Tailwind CSS v4 + Alpine.
Usuarios: administración y recepción (escritorio), teachers y alumnos (**mobile-first**).

Personalidad: moderna, cálida y confiable; educativa sin ser infantil. Luminosa, mucho espacio en blanco,
esquinas redondeadas y un acento vivo que solo se usa para destacar o celebrar.

## Contenido de este paquete

| Ruta | Qué es |
|---|---|
| `resources/css/app.css` | Tokens completos (`@theme`) para Tailwind v4. **Fuente de verdad.** |
| `resources/views/layouts/partials/brand.blade.php` | Inyecta colores de la escuela y fuentes en `<head>`. |
| `app/Support/BrandColor.php` | Calcula texto legible sobre colores de marca + validación de contraste. |
| `resources/views/components/ui/*.blade.php` | `x-ui.button`, `x-ui.status`, `x-ui.semaforo`. |
| `docs/design/prototipo/*.dc.html` | Fuente del prototipo (referencia visual; no es código de producción). |

## 1. White-label (regla principal)

- La escuela (tenant) define solo: **logo**, **color primario**, **color de acento**.
  Guardar en la tabla de escuelas: `logo_path`, `brand_primary`, `brand_accent` (hex).
- Las escalas `primary-50…900` y `accent-50…900` se derivan con `color-mix` en `app.css`. No recompilar por escuela.
- Texto sobre marca: siempre `text-on-primary` / `text-on-accent` (lo calcula `BrandColor`).
- **Nunca** escribir hex en las vistas; solo clases de token.
- Primario y acento **nunca** comunican estado. Los semánticos (`success`, `warning`, `danger`, `info`) son fijos.
- Al guardar colores en Configuración: si `BrandColor::contrastOnWhite($primary) < 4.5`, mostrar advertencia.
- Logo: alto 32 px en barra lateral/superior, 56–64 px en login. Sin logo → monograma (inicial) sobre `bg-primary-600`.

Uso por paso de escala: botón = 600, hover = 700, fondo suave = 50, anillo de foco = 100/200, enlaces = 700.

## 2. Tipografía

- Títulos: **Plus Jakarta Sans** 600–800 (`font-display`). Texto: **Figtree** 400–700 (`font-sans`). Folios: **JetBrains Mono**.
- Escala: `text-display` 48/56 · `text-h1` 36/44 · `text-h2` 28/36 · `text-h3` 22/30 · `text-h4` 18/26 · `text-lg` 18/28 · `text-base` 16/24 · `text-sm` 14/20 · `text-xs` 12/16 · `text-kpi` 40/44.
- En móvil bajar un paso los títulos (h1 → 28, display → 36).
- Números en tablas y KPIs: `tabular-nums`, alineados a la derecha.

## 3. Forma y espaciado

- Radios: `rounded-md` (12) botones/inputs · `rounded-xl` (20) tarjetas · `rounded-2xl` (24) modales · `rounded-full` badges.
- Sombras: preferir `border border-line` + `shadow-xs`. `shadow-pop` solo en modales, menús y toasts.
- Base 4 px. Móvil: márgenes 16 px. Escritorio: 24–40 px.
- **Objetivos táctiles ≥ 44 px** (botones md = `h-11`; en móvil CTA `lg` = `h-13`).

## 4. Componentes

**Botones** (`x-ui.button`): `primary`, `secondary`, `outline`, `ghost`, `accent` (uso puntual), `danger`. Tamaños `sm` (solo escritorio), `md`, `lg`. Estados: hover, foco (`ring-4 ring-primary-200`), cargando (spinner con `wire:loading`), deshabilitado.

**Inputs**: etiqueta siempre visible arriba; `h-11 rounded-md border-[1.5px] border-line-strong px-3.5`;
foco `border-primary-500 ring-4 ring-primary-100`; error `border-danger-600 ring-4 ring-danger-50` + mensaje `text-danger-700 text-[13px]` con icono. Búsqueda con icono a la izquierda y `wire:model.live.debounce.300ms`.

**Selects**: nativos en móvil; en escritorio, select con búsqueda (Alpine) cuando hay más de 7 opciones.

**Tablas con filtros**: barra con búsqueda + filtro segmentado por estado (chips) + selects + "Limpiar filtros".
Encabezado `bg-surface text-xs uppercase tracking-wide text-ink-500`. Paginación abajo.
En pantallas < 768 px la tabla se convierte en **lista de tarjetas**; filtros en hoja inferior.

**Tarjetas KPI**: etiqueta (`text-sm text-ink-700`), valor (`font-display text-kpi font-extrabold tabular-nums`), contexto o tendencia. Se permite una tarjeta destacada con `bg-primary-600 text-on-primary`. Las tarjetas son clicables y llevan al detalle.

**Badges de estado** (`x-ui.status`): activo (verde), pausado (naranja), baja (rojo), egresado (violeta). Siempre con texto.

**Semáforo** (`x-ui.semaforo`): verde = círculo, naranja = triángulo, rojo = cuadrado, **siempre con texto**.
- Asistencia: ≥ 90 % verde · 75–89 % naranja · < 75 % rojo.
- Pagos: al corriente verde · vence en ≤ 5 días naranja · vencido rojo.
- Calificación: ≥ 8.0 verde · 6.0–7.9 naranja · < 6.0 rojo.
- **Cupo de grupo (máx. 5)**: 0–3 verde "Con lugares" · 4/5 naranja "Último lugar" · 5/5 rojo "Lleno".
  (Umbrales configurables por escuela.)

**Modales**: 480/640/880 px, `rounded-2xl shadow-pop`, fondo `bg-ink-900/45`. En móvil → hoja inferior a pantalla completa.
Foco atrapado, Esc cierra, acción destructiva a la derecha y nunca con foco inicial.

**Toasts**: escritorio abajo a la derecha, móvil arriba/abajo ancho completo; 5 s (los errores persisten). Variantes éxito / alerta / error / neutro con "Deshacer".

**Calendario semanal**: columnas Lun–Sáb, filas de 1 h (52 px), sesiones como bloques con fondo del semáforo de cupo (`bg-success-50` etc.), contador `n/5`, hora y aula. Hoy: encabezado con número en `bg-primary-600` y columna `bg-primary-50`; línea de hora actual en `accent-500`. Al tocar una sesión → panel de detalle (cupo, lista de alumnos, "Pase de lista", "Inscribir" / "Lista de espera" si está lleno). En móvil: selector de día + lista de sesiones.

**Estados de pantalla**: vacío (ilustración-icono + título + CTA), cargando (skeleton si tarda > 300 ms con `wire:loading.delay`; spinner solo en botones), error (mensaje humano + "Reintentar").

## 5. Pantallas del prototipo (flujo)

Navegación: escritorio = barra lateral 256 px (logo escuela, menú, usuario, cerrar sesión); móvil = barra superior + navegación inferior de 5 ítems.

1. **Login** con identidad de la escuela: panel de marca (`bg-primary-600`, logo, lema) + formulario (correo, contraseña con "Mostrar", recordarme, olvidé contraseña, Entrar). En móvil el panel de marca va arriba.
2. **Dashboard admin**: KPIs (alumnos activos, ingresos del mes con % de meta, morosos, alumnos en riesgo, ocupación de horarios) + mapa de ocupación por horario (días × horas con n/5) + lista de alumnos en riesgo + lista de morosos con botón "Enviar recordatorios por WhatsApp".
3. **Agenda**: calendario semanal con semáforo de cupo y panel de detalle de sesión.
4. **Expediente del alumno**: encabezado (avatar, nombre, matrícula, estado, grupo, teacher, acciones) + resumen con semáforos (nivel/unit, asistencia, pagos, promedio) + pestañas: Datos · Pagos · Asistencia · Evaluaciones · Documentos · Historial.
   Evaluaciones por **código del catálogo de la escuela**: `1P, 2P, M, WS, SP, WP, PR` (tarjeta por código con calificación, fecha y teacher; pendientes con borde punteado). *El significado de cada código lo define el catálogo.*
5. **Pase de lista (teacher, celular)**: tarjeta de sesión con contadores (presentes / ausentes / sin marcar), selector de **código de evaluación** de la sesión (Ninguno + códigos), lista de 5 alumnos con **Presente / Ausente** (botones de 48 px) y campo de calificación 0–10 cuando hay código y el alumno está presente; "Marcar todos presentes"; "Guardar" valida que todos estén marcados.
6. **Estado de cuenta del alumno**: alertas de cobranza (vencido en rojo con "Pagar ahora", próximo vencimiento en naranja), resumen (saldo, próximo pago, último pago, semáforo), acciones (pagar en línea, subir comprobante, descargar PDF), movimientos (tabla en escritorio / tarjetas en móvil) y línea de tiempo de recordatorios de cobranza (WhatsApp, llamadas, programados) con interruptor de recordatorios automáticos.

## 6. Accesibilidad

- Contraste de texto ≥ 4.5:1. El naranja de semáforo nunca se usa como color de texto (usar `warning-700`).
- Elementos interactivos reales (`<button>`, `<a>`, `<input>` con `<label>`); `aria-label` en botones de solo icono.
- Pestañas con `role="tablist"`/`aria-selected`; filtros con `aria-pressed`; menú activo con `aria-current="page"`.

## 7. Cómo usar con Claude Code

1. Copiar `app.css`, `BrandColor.php` y los componentes `ui/` al proyecto (respetando rutas).
2. Incluir `@include('layouts.partials.brand', ['school' => tenant()])` en el `<head>` del layout antes de `@vite`.
3. Pedir a Claude Code: *"Lee docs/design/DESIGN.md y construye las vistas Blade/Livewire de cada módulo usando solo estos tokens y componentes. Usa los archivos de docs/design/prototipo como referencia visual."*
