# Principios UX/UI para sistemas administrativos

## Estructura
- Navegación lateral o superior consistente; migas de pan en módulos profundos.
- Una acción primaria por pantalla (botón destacado); acciones secundarias discretas; acciones destructivas con confirmación.
- Listados: búsqueda, filtros, orden, paginación, acciones por fila y estado vacío con llamada a la acción.
- Formularios: etiquetas visibles, agrupados por secciones, validación en línea, mensajes que digan cómo corregir, botón de guardar con estado de carga.

## Estados que siempre se diseñan
Vacío, cargando (skeletons o `wire:loading`), éxito (toast breve), error (mensaje claro con salida) y sin permisos.

## Tailwind
- Definir tokens (colores, radios, sombras) en la configuración de Tailwind y reutilizarlos; evitar valores arbitrarios repetidos.
- Extraer componentes Blade (`<x-button>`, `<x-input>`, `<x-card>`, `<x-modal>`) en lugar de repetir clases.
- Mobile-first: diseñar primero para pantalla pequeña y ampliar con `sm:`, `md:`, `lg:`.
- Modo oscuro solo si el proyecto lo pide (`dark:`).

## Accesibilidad mínima
Contraste suficiente, `label` asociado a cada campo, foco visible, navegación por teclado en modales y menús, `aria-*` en componentes interactivos de Alpine.

## Dashboards
Pocas métricas, las que sirven para decidir; tarjetas de KPI arriba, detalle abajo; evitar gráficas decorativas.
