# HU-S02 · Aislamiento entre colegios

**Objetivo:** que ningún usuario pueda ver ni modificar datos de otro colegio, en todos los CRUD del panel.
**Rama:** `main` · **Entrega:** commits por unidad de trabajo, sin push hasta confirmar deploy libre · **Runner:** `php bin/phpunit` (Unit + Integration por carpeta)
**Tablero:** Kaneo → proyecto `ElectivoIA` (`dmhw78oxo69l5nb0dgbol5c8`) · tarea `fm2f8vbt329ecdog46fram86` (`in-progress`, asignada)

---

## Specs

Fuente: `docs/historias-usuario.md:71-79`. Los textos entre comillas son literales.

**S1.** "Un usuario del colegio A recibe 403 o 404 al ver, editar, borrar o usar acciones por lote sobre entidades del colegio B en todos los CRUD del panel: cursos, usuarios, inscripciones, asistencia, perfiles IA, señales IA y apoderado-pupilo."

**S2.** "Los selectores y el autocompletado de los formularios solo ofrecen entidades del propio colegio."

**S3.** "Todas las rutas propias que reciben un ID (estudiante, profesor, apoderado, administrador y exportaciones) responden 403 o 404 con entidades de otro colegio. Entregar el listado de rutas revisadas."

**S4.** "La URL con formato antiguo de EasyAdmin responde 404, no 500, y no carga la entidad."

**S5.** "El super admin conserva acceso a todo."

**S6.** "Los tests funcionales de aislamiento corren antes de cada deploy."

Alcance declarado en la historia: "Como colegio, **quiero** que nadie de otro colegio pueda ver ni modificar mis datos, **para** proteger la información de mis estudiantes. Resuelve P48."

---

## Tasks

| ID | Tarea | Specs | Ruta | Estado |
| --- | --- | --- | --- | --- |
| T1 | Inventario de superficie: cada ruta del panel que recibe un ID, con su veredicto (pasa por repositorio tenant-aware o no). Entregable: listado de rutas revisadas. | S3, S1 | inline | pendiente |
| T2 | Un test de ID cruzado por CRUD, en **RED** antes de tocar código productivo: cursos, usuarios, inscripciones, asistencia, perfiles IA, señales IA, apoderado-pupilo y acciones por lote. | S1, S3, S4, S6 | inline | pendiente |
| T3 | Corrección del agujero más chica por caso confirmado por T2, incluidos selectores y autocompletado. | S1, S2, S4 | inline | pendiente |
| T4 | Decisión explícita sobre `SameSchoolVoter`: conectarlo a las entidades o eliminarlo. No dejarlo como está. | S1 | inline | pendiente |
| T5 | Cierre: suite completa (Unit + Integration) y verificación de que el paso corre en CI antes del deploy. | S6 | inline | pendiente |

**Estado del plan:** propuesto, **pendiente de aprobación del usuario**. No se escribió código todavía.

Riesgo del conjunto: **item 2 del listado de riesgo (control de acceso)** → verificación independiente al cierre, después de las pruebas propias de T5.

---

## Log

**L1 (2026-11-18).** Pedido que abre la etapa: "ok entonces cerramos esta implementacion, limpiamos contexto y iniciamos las historias de usuario restante?" Se recomendó HU-S02 por ser la única del paso 2 que es riesgo de datos y no de UX: si el aislamiento falla, todo lo que se construya después en R1/R2/R3 hereda el agujero.

**L2 (2026-11-18).** El usuario eligió Kaneo como tablero: "usemos kaneo es mas visible para mi". La tarea se movió a `in-progress` y se asignó a Mauricio Ojeda. Convención adoptada: el estado vive en Kaneo, el detalle técnico en este documento.

**L3 (2026-11-18) · Hallazgo que reordena la historia: el voter no protege nada.**

`src/Security/SameSchoolVoter.php:16` define `ATTRIBUTE = 'SAME_SCHOOL'` y su lógica de `voteOnAttribute` es correcta (super admin pasa, usuario sin colegio pasa, entidad sin colegio pasa, si no compara IDs). Pero **nadie consulta ese atributo**: en `src/` y `templates/` hay 7 llamadas a `is_granted()` y las 7 son de rol (`ROLE_ADMIN` ×3, `ROLE_TEACHER`, `ROLE_SUPER_ADMIN`, `ROLE_STUDENT`, `ROLE_GUARDIAN`). Cero llamadas a `is_granted('SAME_SCHOOL', ...)`.

Conclusión: el voter se registra por `autoconfigure: true` (`config/services.yaml:16`) y nunca se ejecuta. La descripción de la tarjeta dice "SameSchoolVoter desplegado (commit e4b2030)", lo que sugiere protección existente; es código muerto. Se corrige esa expectativa en Kaneo.

**L4 (2026-11-18) · Cómo se sostiene el aislamiento realmente.**

- `src/Service/TenantContext.php` guarda el colegio del request; lo setea `TenantSubscriber`. Es nullable a propósito ("Sprint 5: nullable — Sprint 6: enforce non-null once all queries filter by school", dice el propio docblock).
- `src/Repository/TenantAwareRepository.php:44` es el único mecanismo real: `withTenant($qb, $alias)` agrega `AND alias.school = :_tenant_school`. Dos propiedades que definen el riesgo: es **opt-in por query** y es **fail-open** (`if (!$this->tenantContext->hasSchool()) return $qb;` → sin colegio activo, no filtra).

Consecuencia: el aislamiento es una convención, no una política. Cada método de repositorio nuevo puede filtrar de menos sin que nada lo detecte. Los usos explícitos de contexto son pocos: `ExportController.php:100,106`, `Admin/CourseCrudController.php:62`, `Admin/UserCrudController.php:62`, `AdminBulkEnrollController.php:53`.

**L5 (2026-11-18) · Cobertura de tests actual (el mapa de lo que falta).**

`tests/Integration/IsolationTest.php` cubre: curso ver / editar / borrar y usuario ver (`testAdminCannotViewCourseFromOtherSchool` línea 173, `...EditCourse...` 193, `...DeleteCourse...` 208, `...ViewUser...` 223) y el formato de URL antiguo devuelve 404 (línea 152). Es decir: cursos y usuarios, lectura y escritura.

Sin test de ID cruzado: inscripciones, asistencia, perfiles IA, señales IA, apoderado-pupilo, acciones por lote y asistentes de exportación. Eso es exactamente lo que T2 tiene que poner en RED. Superficie total a inventariar: 17 controladores en `src/Controller/` (8 de ellos en `Admin/`).

El test depende de datos sembrados: busca el colegio por slug y falla con "run app:usability:seed first" si no existen (`IsolationTest.php:93`). Los dos colegios de trabajo son `Liceo Ficticio ElectivoIA` (id 60) y `Colegio Aislamiento` (id 61).

**L6 (2026-11-18) · Handoff.**

Se escribe este documento porque el usuario pidió cerrar contexto antes de implementar. Estado del repo al momento: `main` limpio, sin commits locales pendientes. El documento se commitea localmente y **no se pushea**, para no disparar un deploy mientras se limpia contexto (regla 5 del backlog). Nada de código productivo fue tocado.

Para retomar: leer hasta acá, luego T1 y T2 en ese orden. No implementar T3 sin un test en RED que lo justifique.
