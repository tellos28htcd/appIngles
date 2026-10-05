# Análisis de sistemas

Leer este archivo al iniciar un módulo o sistema nuevo. Objetivo: acordar el qué antes del cómo, con el mínimo de documentos útiles.

## Entregables (proporcionales al tamaño del pedido)

1. **Contexto y objetivo**: qué problema resuelve y para quién (2-4 líneas).
2. **Actores y roles**: quién usa el sistema y qué puede hacer cada uno (matriz rol × permiso).
3. **Historias de usuario**: formato "Como [rol] quiero [acción] para [beneficio]" con criterios de aceptación verificables.
4. **Reglas de negocio**: numeradas (RN-01, RN-02…) para poder referenciarlas en código y pruebas.
5. **Flujos principales**: diagrama Mermaid (`flowchart` o `sequenceDiagram`) de los procesos críticos.
6. **Modelo de datos**: diagrama `erDiagram` en Mermaid, tipos de columna, relaciones, índices y restricciones únicas.
7. **Requerimientos no funcionales** relevantes: volumen esperado, concurrencia, auditoría, respaldos, privacidad de datos.
8. **Alcance**: qué entra en la primera versión y qué queda para después.

## Preguntas clave si falta información

- ¿Quiénes son los usuarios y cuántos roles hay?
- ¿Qué datos se manejan y cuáles son sensibles?
- ¿Qué reglas de negocio, cálculos o estados existen (por ejemplo, ciclo de vida de un registro)?
- ¿Hay integraciones (WhatsApp, pagos, facturación, correo)?
- ¿Se necesita multiusuario/multiempresa (multitenancy)?

## Modelado de datos: criterios

- Normalizar hasta tercera forma normal salvo razón de rendimiento documentada.
- Estados como enums (columna string con enum PHP), no números mágicos.
- Dinero en `decimal(12,2)` o enteros en centavos; fechas en UTC con zona de presentación configurable.
- Auditoría (quién/cuándo) en entidades críticas; `softDeletes` donde se requiera recuperar datos.
- Cada tabla pivote con llave compuesta única.
