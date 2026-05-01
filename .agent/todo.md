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

### [ ] Issue: course list should show capacity status
- **Contexto**: TC-AD-10 — Baja de curso
- **Problema**: Al eliminar una inscripción, el registro se borra correctamente pero no hay forma visual de verificar que el cupo se liberó. No se muestran cupos usados/disponibles en el listado de cursos.
- **Solución**: Agregar visualización de cupos en la lista de cursos (ej: "8/20 alumnos" con badge de estado)
- **Prioridad**: Media (visibilidad)
- **Archivos afectados**: `templates/teacher/courses.html.twig`

### [ ] Issue: enrollment list add RUT column
- **Contexto**: TC-AD-09 — Inscripciones
- **Problema**: En el listado de inscripciones, agregar columna RUT del alumno.
- **Solución**: Agregar campo RUT en `EnrollmentCrudController::configureFields()`
- **Prioridad**: Alta (identificación)
- **Archivos afectados**: `src/Controller/Admin/EnrollmentCrudController.php`

### [ ] Issue: enrollment form should show student name + RUT
- **Contexto**: TC-AD-09 — Inscripciones
- **Problema**: En el formulario de edición de inscripción, el campo alumno debería mostrar nombre + RUT para facilitar la identificación.
- **Solución**: Configurar `AssociationField` de student para mostrar ambos datos
- **Prioridad**: Media (usabilidad)
- **Archivos afectados**: `src/Controller/Admin/EnrollmentCrudController.php`

### [ ] Issue: enrollment list should show student name and course name
- **Contexto**: TC-AD-08 — Inscripciones
- **Problema**: En el listado de inscripciones, además del RUT debería mostrar el nombre completo del alumno y el nombre del curso.
- **Solución**: Configurar campos en `EnrollmentCrudController::configureFields()` para mostrar nombre completo de student y course
- **Prioridad**: Media (usabilidad)
- **Archivos afectados**: `src/Controller/Admin/EnrollmentCrudController.php`

### [ ] Issue: button "Ver Inscritos" not working when deleting course
- **Contexto**: TC-AD-06 — Eliminar curso SIN inscripciones
- **Problema**: Al intentar eliminar un curso CON inscripciones, se muestra un error técnico de `ForeignKeyConstraintViolationException` en vez de un mensaje amigable.
- **Solución**: Agregar validación antes de eliminar con mensaje amigable: "No puedes eliminar este curso porque tiene inscripciones activas."
- **Prioridad**: Alta (UX crítica)
- **Archivos afectados**: `src/Controller/Admin/CourseCrudController.php`

### [ ] Issues UX: horario libre y nombre profesor en curso
- **Contexto**: TC-AD-04 — Crear curso
- **Problemas**:
  1. Campo horario es texto libre, debería restringirse a horarios disponibles — posible módulo de gestión de horarios.
  2. Campo Profesor muestra identificador pero debería mostrar nombre completo.
- **Solución**:
  1. Configurar `AssociationField` de teacher para mostrar nombre completo.
  2. (Opcional) Validar formato de horario o usar selector de horarios.
- **Prioridad**: Media (usabilidad)
- **Archivos afectados**: `src/Controller/Admin/CourseCrudController.php`

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
