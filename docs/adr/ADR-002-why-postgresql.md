# ADR-002: Por qué PostgreSQL sobre MySQL para ElectivoIA

| Campo | Valor |
|-------|-------|
| **Estado** | Aceptado |
| **Fecha** | 2026-01 |
| **Decisores** | Arquitecto líder |
| **Contexto** | Inicio del proyecto |

## Contexto

ElectivoIA almacena datos académicos con relaciones complejas, metadatos flexibles para el motor de IA y necesita garantizar aislamiento de datos entre instituciones. Requerimos una base de datos relacional que soporte:

- Almacenamiento de tags y scores del motor de IA en formato flexible (JSONB)
- Consultas complejas con múltiples JOINs (alumno → inscripción → curso → institución)
- Aislamiento de datos por institución (multi-tenencia)
- Búsqueda full-text en nombres y descripciones de cursos
- Integridad referencial estricta (inscripciones con estados, consentimientos con timestamps)
- Operaciones concurrentes con optimistic locking (último cupo)

## Decisión

Elegimos **PostgreSQL 16** como motor de base de datos.

## Razones

1. **JSONB nativo**: Los `InterestProfile` almacenan tags, scores y preferencias en formato JSONB. PostgreSQL permite indexar y consultar dentro de JSONB (`JSON_CONTAINS` vía Doctrine, `CAST(roles AS TEXT) LIKE`), algo que MySQL hace de forma menos eficiente. Los `competency_tags` de cursos también se benefician de este tipo de almacenamiento.

2. **Optimizador de consultas**: Las consultas de reportes de demanda (HU-AD-02) involucran JOINs de 4+ tablas con agregaciones. El optimizador de PostgreSQL es superior para estos casos, con mejor manejo de subqueries y CTEs.

3. **Row Level Security**: Aunque actualmente usamos `TenantFilter` de Doctrine para multi-tenencia, PostgreSQL ofrece RLS nativo como segunda capa de defensa. Si el filtro PHP falla, RLS impide que una institución acceda a datos de otra — un respaldo de seguridad crítico para un SaaS educativo con menores de edad.

4. **Búsqueda full-text**: El buscador de electivos (HU-A-03) necesita busqueda por nombre, descripción y tags. PostgreSQL tiene FTS nativo con diccionarios en español, evitando dependencias externas como Elasticsearch para el volumen del MVP.

5. **Cumplimiento SQL estándar**: PostgreSQL es más fiel al estándar SQL. Esto facilita migraciones, queries complejas y evita sorpresas como el comportamiento de `LIKE` en columnas `JSON` de MySQL.

6. **Campos de tipo ARRAY**: Los roles de usuario (`roles`) se almacenan como JSON, y PostgreSQL maneja tipos ARRAY nativos. La transición a un tipo nativo si se desea es directa.

7. **Extensibilidad**: Extensiones como `pg_trgm` (búsqueda fuzzy para RUTs), `uuid-ossp` (PKs universales) y `hstore` son nativos del ecosistema PostgreSQL.

## Consecuencias

### Positivas
- JSONB indexado para tags y perfiles de interés sin tablas intermedias
- RLS como capa de seguridad adicional para multi-tenencia
- FTS nativo en español sin dependencias externas
- Optimizador superior para reportes complejos (HU-AD-02, HU-AD-03)
- Mayor integridad referencial y consistencia de datos

### Negativas
- Setup ligeramente más complejo que MySQL (especialmente en entornos compartidos)
- Menos hosting compartido económico que MySQL (irrelevante con Docker)
- Curva de aprendizaje para desarrolladores acostumbrados a MySQL

### Compromisos
- Multipamos la robustez a largo plazo sobre la simplicidad inicial
- JSONB como alternativa a tablas de relación para datos semi-estructurados del motor de IA
- FTS nativo en vez de Elasticsearch para el volumen del MVP (revisable si escala)