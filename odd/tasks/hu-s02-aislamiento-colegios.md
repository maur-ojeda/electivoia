# HU-S02 · Aislamiento entre colegios

**Objetivo:** que ningún usuario pueda ver ni modificar datos de otro colegio, en todos los CRUD del panel.
**Rama:** `hu-s02-aislamiento-colegios` · **Entrega:** commits por unidad de trabajo, sin push hasta confirmar deploy libre · **Runner:** `php bin/phpunit` (Unit + Integration por carpeta)
**Tablero:** Kaneo → proyecto `ElectivoIA` (`dmhw78oxo69l5nb0dgbol5c8`) · tarea `fm2f8vbt329ecdog46fram86` (`done`, asignada)

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

## Inventario T1 — rutas con ID y veredicto

Mecanismo real (corrige L3): los CRUD declaran `->setEntityPermission('SAME_SCHOOL')`. EasyAdmin lo consume en `EntityFactory::doCreate()` vía `Permission::EA_ACCESS_ENTITY` → `SecurityVoter::voteOnViewEntityPermission()` → `isGranted('SAME_SCHOOL', $entityInstance)`. El atributo `#[IsGranted('SAME_SCHOOL', subject: 'course')]` de las rutas propias compila al mismo `isGranted`. Por eso `SameSchoolVoter` **sí** se ejecuta; el grep de L3 solo buscó la forma explícita.

| Ruta | Parámetro | Veredicto | Evidencia |
| --- | --- | --- | --- |
| `/admin/{course,course2,user,user2,enrollment,interest-profile}/{entityId}` (`detail`,`edit`,`delete`) | entityId | **cubierta** | `setEntityPermission('SAME_SCHOOL')` en los 6 CRUD + `createIndexQueryBuilder()` filtra `school` |
| `/admin/{...}/batch-delete` | ids en el body | **AGUJERO (corregido en T3)** | ver L10: `EntityDto::newWithInstance()` resetea `isAccessible`, y el `entityFqcn` lo controla el cliente |
| `/admin/{...}/autocomplete` | query | **cubierta** | `AbstractCrudController::autocomplete()` usa `createIndexQueryBuilder()` del controlador destino (línea 472 del vendor) |
| `/admin/course-category/*`, `/admin/school/*` | entityId | **global por diseño** | `ROLE_ADMIN` solo categorías; `SchoolCrudController` es `ROLE_SUPER_ADMIN` (S5) |
| `/student/enroll/{id}`, `/student/unenroll/{id}` | id | **cubierta** | `SAME_SCHOOL` + chequeo de colegio explícito |
| `/enrollment/{id}/enroll` | id | **cubierta** | `SAME_SCHOOL` |
| `/teacher/attendance/{id}`, `/report/{id}`, `/report/{id}/export`, `/courses/{id}/edit`, `/courses/{id}/toggle-active`, `/courses/{id}/students`, `/export/course/{id}/students.xlsx` | id | **cubierta** | `SAME_SCHOOL` + `$course->getTeacher() !== $user` |
| `/admin/export/course/{id}/students.{_format}` | id | **cubierta** | rol + comparación `course->school` vs tenant (línea 98-103) |
| `/super-admin/school/{id}/edit`, `/{id}/toggle` | id | **global por diseño** | `ROLE_SUPER_ADMIN` (S5) |
| `POST /api/ai/signal` | `course_id` en el body | **AGUJERO (corregido en T3)** | era `ApiSignalController.php:54` `find($courseId)` sin comparar colegio; T3 agrega `isGranted('SAME_SCHOOL', $course)` y devuelve 404 |

Fuera de alcance de ID, anotado como advisory: `AdminImportController` valida RUT/email duplicados contra toda la base (oráculo de existencia entre colegios); `TeacherController::saveAttendance` no revalida propiedad del curso (mismo colegio, no fuga entre colegios).

## Tasks

| ID | Tarea | Specs | Ruta | Estado |
| --- | --- | --- | --- | --- |
| T1 | Inventario de superficie: cada ruta del panel que recibe un ID, con su veredicto (pasa por repositorio tenant-aware o no). Entregable: listado de rutas revisadas. | S3, S1 | inline | hecho (ver sección Inventario T1) |
| T2 | Un test de ID cruzado por CRUD, en **RED** antes de tocar código productivo: cursos, usuarios, inscripciones, asistencia, perfiles IA, señales IA, apoderado-pupilo y acciones por lote. | S1, S3, S4, S6 | inline | hecho (33 tests, ver L10) |
| T3 | Corrección del agujero más chica por caso confirmado por T2, incluidos selectores y autocompletado. | S1, S2, S4 | inline | hecho (ver L11) |
| T4 | Decisión explícita sobre `SameSchoolVoter`: conectarlo a las entidades o eliminarlo. No dejarlo como está. | S1 | inline | hecho: se conserva y se extiende a batch (ver L12) |
| T5 | Cierre: suite completa (Unit + Integration) y verificación de que el paso corre en CI antes del deploy. | S6 | inline | hecho (ver L13) |

**Estado del plan:** T1–T5 cerrados, rama publicada, PR #21 abierto. Merge a `main` pendiente de decisión del usuario (dispara deploy).

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

**L7 (2026-11-19) · Retoma. Corrección de L3 con evidencia.**

El usuario pidió retomar. Corrección importante: **L3 estaba mal**. El voter no es código muerto. `setEntityPermission('SAME_SCHOOL')` es el punto de enganche: `EntityFactory::doCreate()` consulta `EA_ACCESS_ENTITY` → `SecurityVoter::voteOnViewEntityPermission()` → `isGranted('SAME_SCHOOL', $instance)`; las rutas propias usan el atributo `#[IsGranted('SAME_SCHOOL', subject: 'course')]`, que es la misma llamada. El grep de L3 solo miró `is_granted('SAME_SCHOOL'` literal y se perdió el atributo y el `setEntityPermission`.

Falso también el "nadie consulta el atributo": 6 CRUD lo declaran y 8 acciones de `TeacherController` más `StudentController`/`EnrollmentController` lo usan como atributo.

**L8 (2026-11-19) · Entorno de test local.**

Faltaba `.env.test.local` (gitignored), así que la suite de integración no arrancaba (`EnvNotFoundException: DATABASE_URL`). Se creó apuntando a la base `electivoia_test` ya sembrada (colegios `liceo-ficticio-electivoia` y `colegio-aislamiento`), con `serverVersion=14`. Ojo: `doctrine.yaml` agrega `_test` al nombre, así que la URL lleva `electivoia`, no `electivoia_test`. Línea base: `php bin/phpunit tests/Integration/IsolationTest.php` → **17 tests, 62 assertions, OK**.

**L9 (2026-11-19) · T1 cerrado.**

Inventario completo de rutas con ID en la sección "Inventario T1". Resultado: todo el panel está cubierto salvo `POST /api/ai/signal`, que acepta `course_id` del body y hace `find()` sin comparar colegio. Ese es el agujero que T2 debe poner en RED. Siguiente: T2.

**L10 (2026-11-19) · T2 en RED. Dos agujeros nuevos, uno de pérdida de datos.**

Suite `tests/Integration/IsolationTest.php`: **33 tests, 142 assertions, OK** después de T3. Antes del fix, los fallos observados (no inferidos) fueron:

1. **Batch-delete borra entidades de otro colegio.** Evidencia con control positivo, mismo admin (`ROLE_ADMIN` de `colegio-aislamiento`, no super admin):

   | Caso | Status | Entidad después |
   | --- | --- | --- |
   | Curso propio | 302 → `/admin/course?page=1` | borrado |
   | Curso ajeno | 409 (FK) | sobrevive solo por el FK |
   | Enrollment ajeno | 302 → `/admin/enrollment?page=1` | **borrado** |

   Causa raíz: `EntityDto::newWithInstance()` hace `new self($fqcn, $metadata, $permission, $instance)`, y el constructor deja `isAccessible = true`. `batchDelete()` chequea `$entityDto->isAccessible()` sobre ese DTO reconstruido, así que el chequeo nunca dispara. El permiso del action `DELETE` es `null`, entonces `EA_EXECUTE_ACTION` también pasa. Medido: `isGranted('SAME_SCHOOL', null)` devuelve `false`, pero ese valor nunca llega a la decisión.

2. **`entityFqcn` del batch lo controla el cliente.** `BatchActionDtoResolver` toma `EA::ENTITY_FQCN` del body y `batchDelete()` hace `getManagerForClass($batchActionDto->getEntityFqcn())`. POST a `/admin/course/batch-delete` con `entityFqcn=App\Entity\User` y el id de un usuario ajeno: 302 y **usuario borrado**. Permite borrar cualquier clase de entidad desde cualquier endpoint de batch.

3. **`POST /api/ai/signal`**: señal apuntando a curso ajeno devolvía 201.

4. Falsos positivos corregidos en el camino: los tests aceptaban 302 como "denegado", y en EasyAdmin el 302 es exactamente lo que devuelve un CSRF inválido o un redirect al login. El token de batch se lee ahora del atributo `data-action-csrf-token` de la página índice (como lo hace el navegador), no de un helper que no participaba de la sesión.

Autocompletado de profesor y de curso: **no fugan**. `AbstractCrudController::autocomplete()` usa el `createIndexQueryBuilder()` del CRUD destino, que filtra `entity.school`. Los tests ahora llevan control positivo (el profesor/curso propio sí aparece) para que el negativo no pase por resultado vacío.

**L11 (2026-11-19) · T3 aplicado, solo sobre lo confirmado por el RED.**

- Nuevo `src/EventSubscriber/BatchActionIsolationSubscriber.php` sobre `BeforeCrudActionEvent`: (a) exige que el `entityFqcn` posteado coincida con el del CRUD actual; (b) valida **todos** los ids contra `SAME_SCHOOL` antes de que EasyAdmin toque nada, así un lote mixto no borra medio bote. Un guard central cubre los 6 CRUD y cualquier CRUD futuro, sin repetir un trait en cada controlador. Control positivo en la suite: `testAdminCanBatchDeleteOwnSchoolEnrollment` verifica que el borrado legítimo sigue funcionando.
- `ApiSignalController`: `isGranted('SAME_SCHOOL', $course)` y 404 con el mismo mensaje que "no existe", para no filtrar existencia entre colegios.

**L12 (2026-11-19) · T4: el voter se conserva y se extiende.**

No se elimina. Ya estaba conectado a las rutas de entidad única vía `setEntityPermission('SAME_SCHOOL')` (6 CRUD) y a las rutas propias vía `#[IsGranted]`; lo que faltaba era el camino de acciones por lote, que ahora lo consulta explícitamente. `supports()` sigue exigiendo sujeto no nulo: un `null` no representa una entidad y debe seguir denegando, no aprobar.

**L13 (2026-11-19) · T5: suite y CI.**

- `php bin/phpunit` → **135 tests, 482 assertions, OK**.
- `composer analyse` (PHPStan, no advisory en CI) → sin errores.
- S6 verificado en `.github/workflows/ci.yml`: el job `tests` corre `php bin/phpunit tests/Integration` (que incluye `IsolationTest`) después de crear el esquema y sembrar con `app:usability:seed`, y el job `deploy` tiene `needs: tests` y solo corre en push a `main`. El aislamiento bloquea el deploy.

Conteo final tras limpiar los probes temporales: `php bin/phpunit` → **136 tests, 487 assertions, OK**.

**L14 (2026-11-19) · Ramificado, pusheado y PR abierto.**

Deploy verificado como libre antes de pushear (regla 5 del backlog) y confirmado por el usuario. Evidencia: el job `deploy` de `ci.yml` exige `github.ref == 'refs/heads/main'` y tiene `needs: tests`; la rama remota solo corre CI.

- Rama `hu-s02-aislamiento-colegios` pusheada con dos commits: `1a27d36` (el fix) y `7e8d43b` (cierre del documento).
- CI en la rama: `success` (run `44620886867`).
- PR abierto: <https://github.com/maur-ojeda/electivoia/pull/21> → `fix(security): enforce school isolation across admin CRUD and AI signals`.
- Tarea Kaneo `HU-S02 · Aislamiento entre colegios` (`fm2f8vbt329ecdog46fram86`) movida a **Done** (columna `iz5vcfpm1yx00hw3et2s2myy`).

El PR no enlaza issue de GitHub: el tracking de esta HU vive en Kaneo, y el repo no tiene issues para S02.

Pendiente y deliberadamente no ejecutado: el **merge a `main`**. Ese merge dispara el deploy a producción y es decisión del usuario, no una consecuencia automática de esta autorización.


