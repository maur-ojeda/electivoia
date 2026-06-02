# TODO - Electivoia

## Pendientes Activos

### [x] Revisar idioma y contenido de mensajes y botones ✅
- **Contexto**: TC-AD-06 — Eliminar curso SIN inscripciones (completado)
- **Ejemplo encontrado**: Mensaje en inglés en confirmación de eliminación
  ```
  "Do you really want to delete this item?
  There is no undo for this operation."
  ```
- **RAÍZ DEL PROBLEMA**: `config/packages/translation.yaml` tenía `default_locale: en`
- **SOLUCIÓN APLICADA**: Cambiado `default_locale: es` en `config/packages/translation.yaml`
- **Resultado**: EasyAdminBundle usa automáticamente sus traducciones en español para TODO el panel de administración
- **Fecha**: 2026-04-21
- **Observación**: Confirmado trabajando: al eliminar un curso sin inscripciones, ahora muestra "¿Realmente quieres borrar este elemento?" en español

---

## Issues Pendientes (descubiertos en testing)

### [x] Issue: course list should show capacity status ✅
- **Contexto**: TC-AD-10 — Baja de curso
- **Problema**: Al eliminar una inscripción, el registro se borra correctamente pero no hay forma visual de verificar que el cupo se liberó. No se muestran cupos usados/disponibles en el listado de cursos.
- **SOLUCIÓN APLICADA**: Agregado campo virtual `currentEnrollment` en `CourseCrudController::configureFields()` con badge de estado verde/naranja
- **Resultado**: Badge muestra "8/20 alumnos" con color verde si hay cupo, naranja si está lleno
- **Commit**: `072a045 fix: TC-AD-10 capacity badge with real enrollment counts`
- **Fecha**: 2026-05-10
- **Prioridad**: Media (visibilidad)
- **Archivos afectados**: `src/Controller/Admin/CourseCrudController.php`

### [x] Issue: enrollment list add RUT column ✅
- **Contexto**: TC-AD-08 — Inscripciones
- **Problema**: En el listado de inscripciones, agregar columna RUT del alumno.
- **SOLUCIÓN APLICADA**: Agregado campo `student.rut` en `EnrollmentCrudController::configureFields()`
- **Resultado**: Columna RUT ahora visible en listado de inscripciones
- **Commit**: `54f4aca fix: resolve 3 EasyAdmin display bugs (TC-AD-04, TC-AD-08, TC-AD-09)`
- **Fecha**: 2026-05-01
- **Prioridad**: Alta (identificación)
- **Archivos afectados**: `src/Controller/Admin/EnrollmentCrudController.php`

### [x] Issue: enrollment form should show student name + RUT ✅
- **Contexto**: TC-AD-09 — Inscripciones
- **Problema**: En el formulario de edición de inscripción, el campo alumno debería mostrar nombre + RUT para facilitar la identificación.
- **SOLUCIÓN APLICADA**: `User::__toString()` ahora devuelve `fullName ?? rut ?? 'Sin nombre'`, y EasyAdmin usa este método globalmente para asociaciones
- **Resultado**: Dropdown de estudiantes muestra nombre completo + RUT
- **Commit**: `54f4aca fix: resolve 3 EasyAdmin display bugs (TC-AD-04, TC-AD-08, TC-AD-09)`
- **Fecha**: 2026-05-01
- **Prioridad**: Media (usabilidad)
- **Archivos afectados**: `src/Entity/User.php`

### [x] Issue: enrollment list should show student name and course name ✅
- **Contexto**: TC-AD-08 — Inscripciones
- **Problema**: En el listado de inscripciones, además del RUT debería mostrar el nombre completo del alumno y el nombre del curso.
- **SOLUCIÓN APLICADA**: Configurado campos en `EnrollmentCrudController::configureFields()` para mostrar `student.fullName` y `course.name`
- **Resultado**: Columnas ahora muestran nombre completo del alumno y nombre del curso
- **Commit**: `54f4aca fix: resolve 3 EasyAdmin display bugs (TC-AD-04, TC-AD-08, TC-AD-09)`
- **Fecha**: 2026-05-01
- **Prioridad**: Media (usabilidad)
- **Archivos afectados**: `src/Controller/Admin/EnrollmentCrudController.php`

### [x] Issue: button "Ver Inscritos" not working when deleting course ✅
- **Contexto**: TC-AD-06 — Eliminar curso SIN inscripciones
- **Problema**: Al intentar eliminar un curso CON inscripciones, se muestra un error técnico de `ForeignKeyConstraintViolationException` en vez de un mensaje amigable.
- **SOLUCIÓN APLICADA**: El método `delete()` en `CourseCrudController` (líneas 80-110) ya cuenta inscripciones y asistencias, y muestra mensaje amigable con redirección
- **Resultado**: Mensaje "No puedes eliminar este curso porque tiene X inscripción(es) activa(s) y Y registro(s) de asistencia"
- **Fecha**: 2026-05-15 (verificación)
- **Prioridad**: Alta (UX crítica)
- **Archivos afectados**: `src/Controller/Admin/CourseCrudController.php`

### [x] Issue: campo horario - mantener texto libre ✅
- **Contexto**: TC-AD-04 — Crear curso
- **Problema**: Campo horario es texto libre, podría ser más estricto
- **DECISIÓN**: Mantener texto libre con help text "Ej: Lunes y Miércoles 15:30-17:00"
- **Por qué**: Horarios recurrentes (Lunes/Miércoles) requieren tabla CourseSchedule → work considerable para poco valor en contexto chileno
- **Resultado**: Campo `TextField::new('schedule')` mantiene help text con ejemplo
- **Fecha**: 2026-05-15
- **Prioridad**: N/A (decisión de diseño)

### [x] Issue: password flash message shows "rut_temp" literal ✅
- **Contexto**: TC-AD-01 — Crear usuario
- **Problema**: Al crear usuario, el mensaje de éxito dice "Contraseña generada: rut_temp" mostrando el literal en vez de los primeros 6 dígitos del RUT real.
- **SOLUCIÓN APLICADA**:
  1. Cambiar fallback en `createEntity()` de `'rut_temp'` a `''` (string vacío)
  2. Agregar flash message en `persistEntity()` con la contraseña real calculada desde el RUT
- **Resultado**: Ahora el admin ve "Usuario creado exitosamente. Contraseña generada: <code>123456</code>" en lugar de "rut_temp"
- **Prioridad**: 🔴 Alta (crítica - funcionalidad rota)
- **Archivos afectados**: `src/Controller/Admin/UserCrudController.php` (líneas 95-120, 57-78)
- **Fecha**: 2026-04-24

---
