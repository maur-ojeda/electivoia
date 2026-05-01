# Plan de Pruebas — ElectivoIA

**Versión:** 2.0
**Fecha:** 2026-03-15
**Proyecto:** ElectivoIA — Sistema de Gestión de Cursos Electivos
**Stack:** Symfony 7.3 · PHP 8.2 · PostgreSQL · DaisyUI/Tailwind v4

---

## 1. Introducción

Este documento define el plan de pruebas funcionales para el sistema ElectivoIA. Cubre los flujos principales de cada actor del sistema, validaciones de negocio, control de acceso por rol y casos borde.

Las pruebas están orientadas a verificar que las historias de usuario definidas en el PRD se comportan correctamente en el sistema desplegado.

---

## 2. Alcance

### 2.1 En alcance

- Autenticación y control de acceso por rol
- Flujos de estudiante: catálogo, inscripción, baja, recomendaciones, chatbot
- Flujos de profesor: cursos, asistencia, reportes, exportación
- Flujos de apoderado: dashboard de pupilos
- Flujos de administrador: gestión de usuarios, cursos, reportes, importación, alertas
- Flujos de super administrador: gestión multi-tenant de colegios
- **Multitenencia:** aislamiento de datos entre colegios, TenantContext, SchoolVoter, EasyAdmin por tenant
- **Onboarding:** creación de colegio, email de bienvenida, categorías por defecto (Sprint 7)
- **Período de inscripción global** por colegio (HU-16)
- Seguridad: acceso cruzado entre roles, CSRF, tenant isolation

### 2.2 Fuera de alcance

- Pruebas de rendimiento / carga
- Pruebas de exportación PDF (no implementado)
- Pruebas de infraestructura / CI-CD

---

## 3. Roles del sistema

| Rol | Descripción | Rutas principales |
|-----|-------------|-------------------|
| Anónimo | Sin sesión | `/login` |
| `ROLE_STUDENT` | Estudiante de enseñanza media | `/student/*` |
| `ROLE_TEACHER` | Profesor asignado a cursos | `/teacher/*` |
| `ROLE_GUARDIAN` | Apoderado de uno o más estudiantes | `/guardian/*` |
| `ROLE_ADMIN` | Administrador del colegio | `/admin/*`, `/admin/reports/*` |
| `ROLE_SUPER_ADMIN` | Super administrador multi-tenant | `/admin/*` + CRUD de colegios |

> **Jerarquía:** `ROLE_SUPER_ADMIN` hereda `ROLE_ADMIN`, que hereda `ROLE_USER`.

---

## 4. Datos de prueba

### 4.1 Usuarios

| # | Nombre | RUT | Contraseña | Rol | Grado | Promedio |
|---|--------|-----|-----------|-----|-------|----------|
| U1 | Ana Torres | 12345678-9 | 12345678 | ROLE_STUDENT | 3M | 6.2 |
| U2 | Luis Mora | 23456789-0 | 23456789 | ROLE_STUDENT | 3M | 4.0 |
| U3 | Pedro Soto | 34567890-1 | 34567890 | ROLE_STUDENT | 4M | — |
| U4 | Prof. María García | 45678901-2 | 45678901 | ROLE_TEACHER | — | — |
| U5 | Apoderada Marta | 56789012-3 | 56789012 | ROLE_GUARDIAN | — | — |
| U6 | Admin Colegio | 67890123-4 | 67890123 | ROLE_ADMIN | — | — |
| U7 | Super Admin | 78901234-5 | 78901234 | ROLE_SUPER_ADMIN | — | — |

> Contraseña = dígitos del RUT antes del guión (política del sistema).

### 4.2 Cursos de prueba

| # | Nombre | Grados | Cupo | Categoría | Horario | Estado |
|---|--------|--------|------|-----------|---------|--------|
| C1 | Estética | 3M, 4M | 20 | Filosofía | Lunes 15:00-17:00 | Activo |
| C2 | Taller de Música | 3M | 15 | Artes | Martes 14:00-15:30 | Activo |
| C3 | Biología Celular | 4M | 10 | Ciencias | — | Activo |
| C4 | Curso sin cupo | 3M | 2 | Ciencias | — | Activo (lleno) |
| C5 | Curso vencido | 3M | 30 | Matemática | — | Activo (deadline pasado) |
| C6 | Curso inactivo | 3M, 4M | 25 | Artes | — | Inactivo |

### 4.3 Relaciones

- U5 (Apoderada Marta) es apoderada de U1 (Ana Torres)
- U4 (Prof. García) es docente de C1, C2, C3
- C4 tiene 2 inscripciones (U1 y U2) — cupo lleno
- C5 tiene `enrollmentDeadline` en el pasado

### 4.4 Colegios para pruebas multi-tenant

| # | Nombre | Slug | Plan | Estado | Período inscripción |
|---|--------|------|------|--------|---------------------|
| SA | Colegio Alpha | `alpha` | basic | Activo | Abierto (sin restricción) |
| SB | Colegio Beta | `beta` | free | Activo | Cerrado (enrollmentEnd en pasado) |

### 4.5 Usuarios multi-tenant

| # | Nombre | RUT | Rol | Colegio | Nota |
|---|--------|-----|-----|---------|------|
| UA1 | Estudiante Alpha | 11111111-1 | ROLE_STUDENT | Colegio Alpha | Grado 3M |
| UA2 | Admin Alpha | 22222222-2 | ROLE_ADMIN | Colegio Alpha | — |
| UB1 | Estudiante Beta | 33333333-3 | ROLE_STUDENT | Colegio Beta | Grado 3M |
| UB2 | Admin Beta | 44444444-4 | ROLE_ADMIN | Colegio Beta | — |
| CA1 | Curso Alpha-1 | — | — | Colegio Alpha | 3M, activo, con cupo |
| CB1 | Curso Beta-1 | — | — | Colegio Beta | 3M, activo, con cupo |

### 4.6 Archivo CSV de prueba

```
rut,nombre_completo,grado,promedio,email
11111111-1,Carlos Pérez,3M,5.8,cperez@test.cl
22222222-2,Sandra López,4M,6.1,
12345678-9,Ana Torres,3M,6.2,        <- duplicado esperado
99999999-X,Inválido Grado,5M,,      <- grado inválido
88888888,SinGuion,3M,,              <- RUT inválido
```

---

## 5. Casos de prueba

### TC-AUTH — Autenticación

| ID | Descripción | Precondición | Pasos | Resultado esperado | Tipo |
|----|-------------|-------------|-------|--------------------|------|
| TC-AUTH-01 | Login exitoso como estudiante | U1 existe y activo | 1. GET `/login` 2. Ingresar RUT `12345678-9` + contraseña `12345678` 3. Submit | Redirige a `/student/courses` | Happy path |
| TC-AUTH-02 | Login exitoso como profesor | U4 existe | 1. Login con RUT de U4 | Redirige a `/teacher/courses` | Happy path |
| TC-AUTH-03 | Login exitoso como admin | U6 existe | 1. Login con RUT de U6 | Redirige a `/admin` (EasyAdmin dashboard) | Happy path |
| TC-AUTH-04 | Login con contraseña incorrecta | U1 existe | 1. Login con contraseña `00000000` | Permanece en `/login` con mensaje de error | Error path |
| TC-AUTH-05 | Login con RUT inexistente | — | 1. Login con RUT `99999999-9` | Permanece en `/login` con error de credenciales | Error path |
| TC-AUTH-06 | Acceso sin autenticar a ruta protegida | Sin sesión activa | 1. GET `/student/courses` sin login | Redirige a `/login` | Seguridad |
| TC-AUTH-07 | Acceso con rol insuficiente | Sesión de U1 (ROLE_STUDENT) | 1. GET `/teacher/courses` | HTTP 403 Forbidden | Seguridad |
| TC-AUTH-08 | Estudiante no puede acceder a admin | Sesión de U1 | 1. GET `/admin` | HTTP 403 | Seguridad |
| TC-AUTH-09 | Logout correcto | Sesión activa de U1 | 1. Click "Cerrar sesión" | Redirige a `/login`, sesión destruida | Happy path |
| TC-AUTH-10 | Token CSRF en login | Sin sesión | 1. Manipular `_csrf_token` en el formulario | Request rechazado | Seguridad |

---

### TC-STU — Estudiante

#### Catálogo de cursos

| ID | HU | Descripción | Precondición | Pasos | Resultado esperado | Tipo |
|----|----|-------------|-------------|-------|--------------------|------|
| TC-STU-01 | HU-1 | Ver catálogo filtrado por grado | U1 (3M) autenticado, C1 (3M), C3 (4M) | GET `/student/courses` | Solo C1 visible, C3 no aparece | Happy path |
| TC-STU-02 | HU-1 | Sin cursos para el grado del estudiante | U3 (4M), solo hay cursos 3M activos | GET `/student/courses` | Mensaje "No se encontraron cursos" | Edge case |
| TC-STU-03 | HU-2 | Filtrar por nombre | U1 autenticado, C1 y C2 activos | GET `/student/courses?q=estética` | Solo C1 visible | Happy path |
| TC-STU-04 | HU-2 | Filtrar por horario | C1 con schedule "Lunes 15:00" | GET `/student/courses?q=lunes` | C1 aparece en resultados | Happy path |
| TC-STU-05 | HU-2 | Filtrar por categoría | Múltiples cursos de categorías distintas | GET `/student/courses?category={id_artes}` | Solo cursos de Artes | Happy path |
| TC-STU-06 | HU-2 | Filtrar solo con cupos disponibles | C4 lleno (2/2), C1 con cupo | GET `/student/courses?available=1` | Solo C1 visible, C4 excluido | Happy path |
| TC-STU-07 | HU-2 | Combinar filtros: categoría + cupos | C2 (Artes, con cupo), C6 (Artes, inactivo) | GET `/student/courses?category={artes}&available=1` | Solo C2 | Happy path |
| TC-STU-08 | HU-2 | Búsqueda sin resultados | Sin cursos que coincidan | GET `/student/courses?q=zzz` | Mensaje de resultado vacío con opción "Limpiar filtros" | Edge case |

#### Inscripción

| ID | HU | Descripción | Precondición | Pasos | Resultado esperado | Tipo |
|----|----|-------------|-------------|-------|--------------------|------|
| TC-STU-10 | HU-3 | Inscripción exitosa con cupo libre | C1 con 5/20 inscritos, deadline futuro, U1 no inscrito | POST `/student/enroll/{C1.id}` | Flash "¡Inscripción exitosa!", enrollment en BD, contador aumenta | Happy path |
| TC-STU-11 | HU-3 | Inscripción duplicada bloqueada | U1 ya inscrito en C1 | POST `/student/enroll/{C1.id}` segunda vez | Flash "Ya estás inscrito en este curso" | Error path |
| TC-STU-12 | HU-3 | Inscripción con deadline pasado | C5 con deadline en el pasado | POST `/student/enroll/{C5.id}` | Flash "La inscripción para este curso ya está cerrada" | Error path |
| TC-STU-13 | HU-3 | Inscripción en curso inactivo | C6 con `isActive = false` | POST `/student/enroll/{C6.id}` | Flash de error, no se crea enrollment | Error path |
| TC-STU-14 | HU-3 | Desplazamiento por promedio superior | C4 lleno (U2 6.2 + U3 sin promedio), U1 (6.2) intenta inscribirse donde hay alguien con menor nota | POST `/student/enroll/{C4.id}` con U1 | U1 queda inscrita, alumno con menor promedio desplazado, flash con nombre del desplazado | Happy path |
| TC-STU-15 | HU-3 | No desplazar cuando promedio es inferior o igual | C4 lleno (U1 6.2 inscrita), U2 (4.0) intenta entrar | POST `/student/enroll/{C4.id}` con U2 | Flash "No hay cupo y tu promedio no es suficiente" | Error path |
| TC-STU-16 | HU-3 | Bloquear inscripción sin promedio en curso lleno | C4 lleno, U3 sin `averageGrade` | POST `/student/enroll/{C4.id}` con U3 | Flash "No puedes inscribirte sin promedio registrado" | Error path |

#### Baja de curso

| ID | HU | Descripción | Precondición | Pasos | Resultado esperado | Tipo |
|----|----|-------------|-------------|-------|--------------------|------|
| TC-STU-20 | HU-4 | Baja exitosa antes del deadline | U1 inscrita en C1, deadline futuro | POST `/student/unenroll/{C1.id}` | Flash "Te has dado de baja del curso", enrollment eliminado de BD | Happy path |
| TC-STU-21 | HU-4 | Baja bloqueada después del deadline | U1 inscrita en C5 (deadline pasado) | POST `/student/unenroll/{C5.id}` | Flash "La fecha límite ya pasó. No es posible darse de baja" | Error path |
| TC-STU-22 | HU-4 | Baja de curso sin fecha límite | U1 inscrita en C3 (sin `enrollmentDeadline`) | POST `/student/unenroll/{C3.id}` | Baja exitosa (no hay restricción de fecha) | Happy path |
| TC-STU-23 | HU-4 | Baja de curso en el que no está inscrito | U1 no está en C3 | POST `/student/unenroll/{C3.id}` | Flash "No estás inscrito en este curso" | Error path |

#### Mis inscripciones y asistencia

| ID | HU | Descripción | Precondición | Pasos | Resultado esperado | Tipo |
|----|----|-------------|-------------|-------|--------------------|------|
| TC-STU-30 | HU-5 | Ver mis inscripciones | U1 inscrita en C1 y C2 | GET `/student/enrollments` | Lista con C1 y C2, nombre del curso, fecha de inscripción | Happy path |
| TC-STU-31 | HU-5 | Lista de inscripciones vacía | U3 sin inscripciones | GET `/student/enrollments` | Página limpia sin tarjetas de cursos | Edge case |
| TC-STU-32 | — | Ver resumen de asistencia | U1 con registros de asistencia en C1 | GET `/student/attendance` | Porcentaje de asistencia por curso, detalle por sesión en `<details>` | Happy path |
| TC-STU-33 | — | Dashboard con estadísticas | U1 inscrita con asistencias | GET `/student/dashboard` | Stats: total cursos, % asistencia promedio, estado por curso | Happy path |

#### Perfil de intereses y recomendaciones

| ID | HU | Descripción | Precondición | Pasos | Resultado esperado | Tipo |
|----|----|-------------|-------------|-------|--------------------|------|
| TC-STU-40 | HU-6 | Crear perfil de intereses por primera vez | U1 sin perfil previo | 1. Abrir modal "Mis intereses" 2. Subir slider de "Ciencias" a 5 3. Submit POST `/student/profile` | Flash "Perfil actualizado", perfil guardado en BD | Happy path |
| TC-STU-41 | HU-6 | Actualizar perfil existente | U1 con perfil previo (Ciencias: 3) | 1. Abrir modal 2. Cambiar Ciencias a 1 3. Submit | Perfil actualizado, valor nuevo reflejado al reabrir el modal | Happy path |
| TC-STU-42 | HU-7 | Ver recomendaciones con perfil activo | U1 con interés alto en "Artes" (≥4), C2 categoría Artes activo para 3M | GET `/student/courses` | Sección "Cursos recomendados para ti" muestra C2 | Happy path |
| TC-STU-43 | HU-7 | Sin recomendaciones con perfil vacío | U1 con todos los intereses en 0 | GET `/student/courses` | Sección de recomendados no aparece | Edge case |
| TC-STU-44 | HU-7 | Recomendación excluye cursos de otro grado | U1 (3M), C3 (solo 4M) con categoría de interés alto | GET `/student/courses` | C3 no aparece en recomendados | Seguridad lógica |
| TC-STU-45 | HU-8 | Botón "Más creativo" ajusta sliders | Modal abierto, sliders en valores bajos | Click botón "Más creativo" | Artes y Educación Física suben +2, Matemática y Filosofía bajan -2 (mín. 0, máx. 5) | Happy path |
| TC-STU-46 | HU-8 | Botón "Más científico" ajusta sliders | Modal abierto | Click "Más científico" | Ciencias y Matemática suben +2, Artes baja -2 | Happy path |
| TC-STU-47 | HU-8 | Botón "Limpiar todo" resetea sliders | Modal con valores altos | Click "Limpiar todo" | Todos los sliders a 0, outputs muestran "0/5" | Happy path |
| TC-STU-48 | HU-8 | Sliders no superan mínimo/máximo | Slider en 0, click "Más científico" 5 veces | Múltiples clicks | Slider no baja de 0 ni sube de 5 | Edge case |
| TC-STU-49 | HU-10 | Ver razón de recomendación | U1 con interés alto en "Artes", C2 en Artes visible | GET `/student/courses` | Tarjeta de C2 muestra "★ Artes" bajo el nombre del profesor | Happy path |

#### Chatbot

| ID | HU | Descripción | Precondición | Pasos | Resultado esperado | Tipo |
|----|----|-------------|-------------|-------|--------------------|------|
| TC-STU-50 | HU-9 | Abrir widget del chatbot | U1 en `/student/courses` | Click en botón flotante azul | Widget aparece con mensaje de bienvenida | Happy path |
| TC-STU-51 | HU-9 | Cerrar widget del chatbot | Widget abierto | Click en "✕" | Widget se oculta | Happy path |
| TC-STU-52 | HU-9 | Enviar mensaje y recibir respuesta | Widget abierto, `GEMINI_API_KEY` configurada | Escribir "¿Qué cursos de música hay?" · Submit | Respuesta en lenguaje natural mencionando C2 (Taller de Música) | Happy path |
| TC-STU-53 | HU-9 | El chatbot conoce el horario del curso | C1 con schedule "Lunes 15:00-17:00" | Preguntar "¿Cuál es el horario de Estética?" | Respuesta incluye "Lunes 15:00-17:00" | Happy path |
| TC-STU-54 | HU-9 | Limpiar historial del chat | Chat con 3 turnos de conversación | Click "↺" | Mensajes eliminados, historial de sesión borrado, vuelve solo al saludo inicial | Happy path |
| TC-STU-55 | HU-9 | No enviar mensaje vacío | Widget abierto | Click Submit sin texto | No se realiza la llamada a la API | Edge case |
| TC-STU-56 | HU-9 | Manejo de error de API | `GEMINI_API_KEY` inválida | Enviar cualquier mensaje | Mensaje de error amable, sin crash de la página | Error path |

---

### TC-TCH — Profesor

| ID | HU | Descripción | Precondición | Pasos | Resultado esperado | Tipo |
|----|----|-------------|-------------|-------|--------------------|------|
| TC-TCH-01 | HU-11 | Ver solo mis cursos | U4 con C1 y C2; existe C3 asignado a otro profesor | GET `/teacher/courses` | Solo C1 y C2 visibles | Happy path |
| TC-TCH-02 | HU-11 | Ver horario en tarjeta de curso | C1 con schedule "Lunes 15:00-17:00" | GET `/teacher/courses` | Se muestra "🕐 Lunes 15:00-17:00" en la tarjeta de C1 | Happy path |
| TC-TCH-03 | HU-11 | Filtrar mis cursos por nombre | U4 tiene C1 "Estética" y C2 "Taller de Música" | GET `/teacher/courses?q=música` | Solo C2 visible | Happy path |
| TC-TCH-04 | HU-12 | Ver lista de inscritos de mi curso | C1 con U1 y U2 inscritos | GET `/teacher/courses/{C1.id}/students` | Tabla con U1 y U2, nombre, RUT, grado y promedio | Happy path |
| TC-TCH-05 | HU-12 | Bloquear ver inscritos de curso ajeno | U4 intenta ver inscritos de un curso de otro profesor | GET `/teacher/courses/{id}/students` | HTTP 403 AccessDeniedException | Seguridad |
| TC-TCH-06 | HU-13 | Ver cupo total en tarjeta | C1 con 8/20 inscritos | GET `/teacher/courses` | Muestra "8 / 20 alumnos" | Happy path |
| TC-TCH-07 | HU-14 | Crear curso nuevo exitoso | U4 autenticado | POST `/teacher/courses/new` con nombre "Nuevo Curso", cupo 20, grado 3M, horario "Viernes 14:00" | Flash "Curso creado", course en BD con `school` del tenant | Happy path |
| TC-TCH-08 | HU-14 | Crear curso sin nombre | Formulario nuevo curso | POST con `name` vacío | Error "El nombre del curso es obligatorio" | Error path |
| TC-TCH-09 | HU-14 | Crear curso con cupo cero | — | POST con `maxCapacity = 0` | Error "La capacidad máxima debe ser al menos 1" | Error path |
| TC-TCH-10 | HU-14 | Crear curso sin grado objetivo | — | POST sin ningún checkbox de grado | Error "Debes seleccionar al menos un grado objetivo" | Error path |
| TC-TCH-11 | HU-15 | Crear curso con horario | POST con `schedule = "Martes y Jueves 15:30-17:00"` | POST | Horario guardado, visible en tarjeta y en formulario de edición | Happy path |
| TC-TCH-12 | HU-14 | Editar curso propio | C1 de U4 | POST `/teacher/courses/{C1.id}/edit` cambiando nombre | Flash "Curso actualizado", cambio reflejado | Happy path |
| TC-TCH-13 | HU-14 | Editar curso ajeno bloqueado | U4 intenta editar curso de otro profesor | POST edit | HTTP 403 | Seguridad |
| TC-TCH-14 | HU-14 | No reducir cupo por debajo de inscritos | C1 con 10 inscritos | POST edit con `maxCapacity = 5` | Error "No puedes reducir la capacidad por debajo de los alumnos ya inscritos (10)" | Error path |
| TC-TCH-15 | HU-16 | Desactivar curso activo | C1 activo | POST `/teacher/courses/{C1.id}/toggle-active` | `isActive = false`, C1 ya no aparece en catálogo del estudiante | Happy path |
| TC-TCH-16 | HU-16 | Activar curso inactivo | C6 inactivo | POST toggle-active | `isActive = true`, C6 vuelve a aparecer en catálogo | Happy path |
| TC-TCH-17 | — | Registrar asistencia primera vez | C1 con U1 y U2, sin asistencias hoy | POST `/teacher/attendance/{C1.id}/save` con U1=present, U2=absent | 2 registros de asistencia creados para hoy | Happy path |
| TC-TCH-18 | — | Sobreescribir asistencia del mismo día | Asistencia ya registrada hoy para C1 | POST attendance/save nuevamente con estados distintos | Registros anteriores del día eliminados y reemplazados con los nuevos | Happy path |
| TC-TCH-19 | — | Bloquear registro de asistencia en curso ajeno | U4 intenta registrar asistencia en curso de otro profesor | POST attendance/save | HTTP 403 | Seguridad |
| TC-TCH-20 | HU-17 | Ver reporte de asistencia de mi curso | C1 con 3 sesiones registradas para U1 | GET `/teacher/report/{C1.id}` | Tabla con U1: 3 sesiones, % de asistencia, desglose presente/ausente | Happy path |
| TC-TCH-21 | HU-17 | Bloquear ver reporte de curso ajeno | U4 intenta ver reporte de curso ajeno | GET `/teacher/report/{id}` | HTTP 403 | Seguridad |
| TC-TCH-22 | HU-18 | Exportar lista de inscritos a Excel | C1 con U1 y U2 | GET `/teacher/export/course/{C1.id}/students.xlsx` | Descarga `alumnos_Estética.xlsx` con columnas Alumno, RUT, Grado, Promedio | Happy path |
| TC-TCH-23 | HU-18 | Exportar reporte de asistencia a Excel | C1 con asistencias registradas | GET `/teacher/report/{C1.id}/export` | Descarga `.xlsx` con columnas RUT, Nombre, Grado, Promedio, Sesiones, %, etc. | Happy path |
| TC-TCH-24 | HU-18 | Bloquear exportación de curso ajeno | U4 exporta curso de otro profesor | GET export students | HTTP 403 | Seguridad |

---

### TC-GRD — Apoderado

| ID | HU | Descripción | Precondición | Pasos | Resultado esperado | Tipo |
|----|----|-------------|-------------|-------|--------------------|------|
| TC-GRD-01 | HU-30 | Ver cursos del pupilo | U5 apoderada de U1, U1 inscrita en C1 y C2 | GET `/guardian/dashboard` | Se muestran los 2 cursos de U1 con nombre, profesor y horario | Happy path |
| TC-GRD-02 | HU-30 | Apoderado sin pupilos asignados | U5 sin relación con ningún estudiante | GET `/guardian/dashboard` | Mensaje "No tienes pupilos asignados" | Edge case |
| TC-GRD-03 | HU-31 | Ver horario del curso del pupilo | C1 con schedule "Lunes 15:00-17:00" | GET `/guardian/dashboard` | Se muestra "🕐 Lunes 15:00-17:00" bajo el nombre del curso | Happy path |
| TC-GRD-04 | HU-31 | Curso del pupilo sin horario definido | C2 sin `schedule` | GET `/guardian/dashboard` | El campo horario no aparece para C2 | Edge case |
| TC-GRD-05 | — | Ver porcentaje de asistencia del pupilo | U1 con 8 presentes de 10 sesiones en C1 | GET `/guardian/dashboard` | Se muestra "80%" en verde | Happy path |
| TC-GRD-06 | — | Semáforo de asistencia: rojo | U1 con 30% de asistencia | GET `/guardian/dashboard` | Porcentaje en rojo, borde rojo en la tarjeta | Happy path |
| TC-GRD-07 | — | Detalle de sesiones del pupilo | U1 con 3 registros de asistencia en C1 | GET `/guardian/dashboard` → expandir `<details>` | Tabla con fecha y estado (Presente/Ausente/Justificado) de cada sesión | Happy path |
| TC-GRD-08 | — | Apoderado no accede a rutas de estudiante | U5 con sesión activa | GET `/student/courses` | HTTP 403 | Seguridad |
| TC-GRD-09 | — | Apoderado no puede inscribir al pupilo | U5 con sesión activa | POST `/student/enroll/{id}` | HTTP 403 | Seguridad |

---

### TC-ADM — Administrador

#### Gestión de usuarios

| ID | HU | Descripción | Precondición | Pasos | Resultado esperado | Tipo |
|----|----|-------------|-------------|-------|--------------------|------|
| TC-ADM-01 | HU-21 | Crear usuario estudiante desde EasyAdmin | U6 autenticado en `/admin` | GET `/admin/user/new` → completar nombre, RUT, grado, rol ROLE_STUDENT → submit | Usuario creado; contraseña automática = primeros 6 dígitos del RUT | Happy path |
| TC-ADM-02 | HU-21 | Crear usuario con RUT inválido | EasyAdmin nuevo usuario | Ingresar RUT `12345` (sin guión) → submit | Error de validación: "El RUT debe tener el formato 12345678-9" | Error path |
| TC-ADM-03 | HU-25 | Desactivar usuario | U2 activo en BD | EasyAdmin edit U2 → `active = false` → guardar | `active = false`; U2 no puede iniciar sesión | Happy path |
| TC-ADM-04 | HU-26 | Vincular apoderado con estudiante | U5 (Guardian), U1 (Student) | EasyAdmin edit U5 → campo "Pupilos" → seleccionar U1 → guardar | Relación persistida; U5 ve los cursos de U1 en su dashboard | Happy path |
| TC-ADM-05 | HU-26 | El campo Pupilos solo muestra estudiantes | EasyAdmin edit de apoderado | Abrir dropdown "Pupilos" | Solo usuarios con ROLE_STUDENT aparecen; profesores y admins excluidos | Happy path |
| TC-ADM-06 | HU-23 | Crear profesor | — | EasyAdmin nuevo usuario con ROLE_TEACHER | Usuario con ROLE_TEACHER creado correctamente | Happy path |
| TC-ADM-07 | HU-24 | Crear apoderado | — | EasyAdmin nuevo usuario con ROLE_GUARDIAN | Usuario con ROLE_GUARDIAN creado | Happy path |
| TC-ADM-08 | — | Lista de usuarios filtrada por escuela | Admin del Colegio A, existen usuarios del Colegio B | GET `/admin/user` | Solo usuarios cuyo `school = Colegio A` aparecen | Seguridad |

#### Importación CSV

| ID | HU | Descripción | Precondición | Pasos | Resultado esperado | Tipo |
|----|----|-------------|-------------|-------|--------------------|------|
| TC-ADM-10 | HU-22 | Importar CSV válido | Archivo CSV con 2 registros nuevos | POST `/admin/users/import` con archivo CSV | Flash/resultado: `created: 2, duplicates: 0, errors: 0` | Happy path |
| TC-ADM-11 | HU-22 | Importar CSV con RUT duplicado | U1 (12345678-9) ya existe | Archivo CSV con RUT de U1 + 1 nuevo | `created: 1, duplicates: 1, errors: 0` | Edge case |
| TC-ADM-12 | HU-22 | Importar CSV con RUT de formato inválido | — | Archivo con RUT `88888888` (sin guión) | `errors: 1` con mensaje "Línea X: RUT inválido" | Error path |
| TC-ADM-13 | HU-22 | Importar CSV con grado inválido | — | Archivo con grado `5M` | `errors: 1` con mensaje "grado inválido '5M'" | Error path |
| TC-ADM-14 | HU-22 | Importar CSV con separador punto y coma | Archivo con `;` como separador | POST con archivo semicolon-delimited | Procesado correctamente, mismos resultados que coma | Happy path |
| TC-ADM-15 | HU-22 | Importar asigna school al usuario | Admin del Colegio A, TenantContext activo | POST import con 2 nuevos usuarios | Ambos usuarios creados con `school = Colegio A` | Happy path |
| TC-ADM-16 | HU-22 | Subir archivo no CSV | — | POST con archivo `.txt` | Flash "Debes subir un archivo con extensión .csv" | Error path |
| TC-ADM-17 | HU-22 | Contraseña generada = dígitos del RUT | Importar RUT `12345678-9` | POST import | Contraseña del usuario = `12345678` | Happy path |

#### Gestión de cursos (EasyAdmin)

| ID | HU | Descripción | Precondición | Pasos | Resultado esperado | Tipo |
|----|----|-------------|-------------|-------|--------------------|------|
| TC-ADM-20 | HU-14 | Crear curso desde EasyAdmin | U6 en `/admin/course/new` | Completar nombre, cupo, grados, profesor → submit | Curso creado con `school = Colegio A` (tenant del admin) | Happy path |
| TC-ADM-21 | HU-15 | Definir cupo y fecha límite | EasyAdmin nuevo curso | `maxCapacity = 25`, `enrollmentDeadline = 2026-06-30` | Valores guardados correctamente | Happy path |
| TC-ADM-22 | HU-15 | Definir horario desde EasyAdmin | EasyAdmin nuevo curso | `schedule = "Lunes y Miércoles 15:30-17:00"` | Horario guardado, visible en catálogo de alumnos | Happy path |
| TC-ADM-23 | HU-27 | Asignar profesor a curso al crear | EasyAdmin nuevo curso | Seleccionar U4 en campo "Profesor" | Relación `course.teacher = U4` guardada | Happy path |
| TC-ADM-24 | HU-28 | Cambiar profesor asignado a un curso | C1 con U4 asignado | EasyAdmin edit C1 → cambiar a otro profesor | `course.teacher` actualizado | Happy path |
| TC-ADM-25 | — | Lista de cursos filtrada por escuela | Admin Colegio A, cursos de 2 colegios | GET `/admin/course` | Solo cursos con `school = Colegio A` | Seguridad |

#### Inscripción masiva

| ID | HU | Descripción | Precondición | Pasos | Resultado esperado | Tipo |
|----|----|-------------|-------------|-------|--------------------|------|
| TC-ADM-30 | — | Inscripción masiva exitosa | C1 con 15/20 cupos, 3 estudiantes 3M activos no inscritos | POST `/admin/enrollments/bulk` con `course_id={C1}` + `grade=3M` | `enrolled: 3, skipped: 0, rejected: 0` | Happy path |
| TC-ADM-31 | — | Inscripción masiva con deadline pasado | C5 con deadline pasado | POST bulk con course_id de C5 | Flash "El período de inscripción para este curso ha cerrado" | Error path |
| TC-ADM-32 | — | Inscripción masiva omite ya inscritos | U1 ya inscrito en C1, otros 2 no | POST bulk mismo curso y grado | `enrolled: 2, skipped: 1 (U1), rejected: 0` | Edge case |
| TC-ADM-33 | — | Inscripción masiva respeta capacidad máxima | C1 con 19/20, 3 estudiantes del grado | POST bulk | `enrolled: 1, rejected: 2` | Edge case |
| TC-ADM-34 | — | Inscripción masiva filtrada por escuela | Admin Colegio A | POST bulk con grade=3M | Solo estudiantes con `school = Colegio A` son candidatos | Seguridad |

#### Reportes

| ID | HU | Descripción | Precondición | Pasos | Resultado esperado | Tipo |
|----|----|-------------|-------------|-------|--------------------|------|
| TC-ADM-40 | HU-17 | Dashboard de reportes | Cursos con inscripciones | GET `/admin/reports` | Gráficos de ocupación por curso, distribución por categoría, carga docente, insights | Happy path |
| TC-ADM-41 | HU-17 | Reporte comparativo | Inscripciones de U1 (3M) y U3 (4M) en distintas categorías | GET `/admin/reports/comparative` | Tabla cruzada Grado × Categoría con totales correctos | Happy path |
| TC-ADM-42 | HU-18 | Exportar reporte comparativo a Excel | Datos en BD | GET `/admin/reports/comparative/export` | Descarga `reporte_comparativo.xlsx` con la matriz correcta | Happy path |
| TC-ADM-43 | HU-29 | Cursos por profesor | U4 tiene C1 y C2; otro profesor tiene C3 | GET `/admin/reports/by-teacher` | Card de U4 muestra C1 y C2 con cupo y horario; card del otro con C3 | Happy path |
| TC-ADM-44 | HU-29 | Horario incluido en reporte por profesor | C1 con schedule | GET `/admin/reports/by-teacher` | Columna Horario muestra "Lunes 15:00-17:00" para C1 | Happy path |
| TC-ADM-45 | HU-20 | Alertas de baja inscripción | C4 con 2/20 (10%), C1 con 15/20 (75%) | GET `/admin/reports/low-enrollment` | Solo C4 aparece en la lista (bajo 30%); C1 no | Happy path |
| TC-ADM-46 | HU-20 | Sin alertas cuando todo OK | Todos los cursos ≥ 30% | GET `/admin/reports/low-enrollment` | Mensaje de éxito "Todos los cursos superan el 30% de ocupación" | Edge case |
| TC-ADM-47 | HU-20 | Comando de alertas en consola | Cursos con baja inscripción | `php bin/console app:alert-low-enrollment` | Tabla con cursos bajo el 30% (umbral default) | Happy path |
| TC-ADM-48 | HU-20 | Comando con umbral personalizado | Cursos con diversa ocupación | `php bin/console app:alert-low-enrollment --threshold=60` | Tabla con cursos bajo el 60% | Happy path |
| TC-ADM-49 | HU-20 | Comando sin cursos bajo el umbral | Todos ≥ 30% | `php bin/console app:alert-low-enrollment` | Mensaje "Ningún curso por debajo del 30% de ocupación" | Edge case |
| TC-ADM-50 | HU-33 | Insights generados por IA | `GEMINI_API_KEY` válida, cursos con datos | GET `/admin/reports/insights` | 5 bullets en español analizando la situación real, estadísticas de contexto | Happy path |
| TC-ADM-51 | HU-33 | Fallback de insights sin API key | `GEMINI_API_KEY` vacía o inválida | GET `/admin/reports/insights` | Se muestran insights de fallback; no ocurre error 500 | Error path |
| TC-ADM-52 | HU-33 | Regenerar insights | Insights ya generados | Click "Regenerar análisis" | Nueva llamada a Gemini, página recargada con análisis fresco | Happy path |
| TC-ADM-53 | — | Protección de acceso a reportes | Sesión de U1 (estudiante) | GET `/admin/reports` | HTTP 403 | Seguridad |

---

### TC-SUA — Super Administrador

#### Acceso y permisos

| ID | Descripción | Precondición | Pasos | Resultado esperado | Tipo |
|----|-------------|-------------|-------|--------------------|------|
| TC-SUA-01 | Ver sección "Colegios" en el menú | U7 (ROLE_SUPER_ADMIN) autenticado | GET `/admin` | Menú lateral muestra "Super Admin → 🏫 Colegios" | Happy path |
| TC-SUA-02 | ROLE_ADMIN no ve sección de Colegios | U6 (ROLE_ADMIN, no super) | GET `/admin` | Sección "Super Admin" no aparece | Seguridad |
| TC-SUA-03 | Admin no puede acceder al CRUD de colegios | U6 (ROLE_ADMIN) | GET `/admin/school` | HTTP 403 (IsGranted ROLE_SUPER_ADMIN) | Seguridad |
| TC-SUA-04 | Admin no puede acceder al panel super admin | U6 (ROLE_ADMIN) | GET `/super-admin` | HTTP 403 | Seguridad |
| TC-SUA-05 | Super admin ve todos los cursos sin filtro de tenant | Cursos de SA y SB | GET `/admin/course` con sesión de U7 | Cursos de ambos colegios visibles | Happy path |
| TC-SUA-06 | Super admin ve todos los usuarios | Usuarios de SA y SB | GET `/admin/user` con sesión de U7 | Usuarios de ambos colegios visibles | Happy path |

#### Dashboard super admin

| ID | Descripción | Precondición | Pasos | Resultado esperado | Tipo |
|----|-------------|-------------|-------|--------------------|------|
| TC-SUA-10 | Ver dashboard super admin | U7 autenticado | GET `/super-admin` | Tabla con SA y SB, conteo de usuarios por colegio, stats globales | Happy path |
| TC-SUA-11 | Estadísticas globales correctas | SA con 5 users, SB con 3 users | GET `/super-admin` | `Total colegios: 2`, `Total usuarios: 8` | Happy path |
| TC-SUA-12 | Estado período de inscripción en dashboard | SA abierto, SB cerrado | GET `/super-admin` | Columna "Período" muestra "Abierto" (SA) y "Cerrado" (SB) | Happy path |
| TC-SUA-13 | Dashboard sin colegios registrados | BD vacía | GET `/super-admin` | Mensaje "No hay colegios registrados" + botón "Registrar primer colegio" | Edge case |

#### Onboarding de colegio (HDU-S7-01)

| ID | Descripción | Precondición | Pasos | Resultado esperado | Tipo |
|----|-------------|-------------|-------|--------------------|------|
| TC-SUA-20 | Formulario de onboarding visible | U7 autenticado | GET `/super-admin/onboard` | Formulario con campos: nombre, slug, RBD, plan, nombre admin, RUT admin, email admin | Happy path |
| TC-SUA-21 | Slug se autocompletadesde el nombre | Formulario abierto en browser | Escribir "Colegio San Ignacio" en campo nombre | Campo slug se rellena automáticamente con `colegio-san-ignacio` vía JS | Happy path |
| TC-SUA-22 | Crear colegio + admin exitoso | Sin colegio "Gamma" ni RUT `55555555-5` | POST `/super-admin/onboard` con nombre "Colegio Gamma", RUT admin `55555555-5`, email `admin@gamma.cl` | Flash "Colegio creado. Email enviado a admin@gamma.cl"; School en BD; User ROLE_ADMIN vinculado; contraseña generada aleatoriamente | Happy path |
| TC-SUA-23 | Admin inicial queda vinculado al colegio | POST exitoso (TC-SUA-22) | Consultar BD: `SELECT school_id FROM user WHERE rut = '55555555-5'` | `school_id = Colegio Gamma.id` | Happy path |
| TC-SUA-24 | Slug único: duplicado bloqueado | Colegio con slug "alpha" ya existe | POST con slug "alpha" | Error de unique constraint o validación; no se crea el colegio | Error path |
| TC-SUA-25 | Campos obligatorios vacíos | — | POST con nombre vacío | HTML5 `required` impide submit; o error de validación server-side | Error path |
| TC-SUA-26 | Plan asignado correctamente | POST con plan "premium" | Verificar BD | `school.plan = 'premium'` | Happy path |
| TC-SUA-27 | Contraseña admin es aleatoria (no RUT) | POST exitoso | Intentar login con admin@gamma.cl usando su propio RUT como contraseña | Login fallido (contraseña generada aleatoriamente, no es el RUT) | Happy path |

#### Email de bienvenida (HDU-S7-02)

| ID | Descripción | Precondición | Pasos | Resultado esperado | Tipo |
|----|-------------|-------------|-------|--------------------|------|
| TC-SUA-30 | Email de bienvenida enviado al crear colegio | SMTP configurado, TC-SUA-22 exitoso | Revisar bandeja de admin@gamma.cl | Email recibido con asunto "Bienvenido a ElectivoIA — Colegio Gamma" | Happy path |
| TC-SUA-31 | Email incluye credenciales | Email recibido | Abrir email | Contiene RUT del admin, contraseña temporal y URL de acceso | Happy path |
| TC-SUA-32 | Email advierte cambio de contraseña | Email recibido | Revisar contenido | Mensaje "Cambia tu contraseña al ingresar por primera vez" visible | Happy path |
| TC-SUA-33 | Sin email no bloquea creación del colegio | SMTP no configurado (`.env.local` sin mailer) | POST onboard | Flash de advertencia sobre el email pero colegio y admin creados igualmente | Error path |

#### Categorías por defecto (HDU-S7-03)

| ID | Descripción | Precondición | Pasos | Resultado esperado | Tipo |
|----|-------------|-------------|-------|--------------------|------|
| TC-SUA-40 | Categorías sembradas si no existen | BD sin ninguna `CourseCategory` | POST onboard primer colegio | 7 categorías chilenas creadas: Filosofía, Matemática, Ed. Física, Historia, Ciencias, Lengua y Literatura, Artes | Happy path |
| TC-SUA-41 | No duplicar categorías en segundo colegio | Categorías ya existen (TC-SUA-40) | POST onboard segundo colegio | Conteo de `CourseCategory` no aumenta; mismo total | Happy path |
| TC-SUA-42 | Áreas asignadas correctamente | TC-SUA-40 ejecutado | Consultar BD | Matemática tiene `area = 'Cien'`, Artes tiene `area = 'Arte'`, Filosofía tiene `area = 'Hum'` | Happy path |

#### Gestión de colegios (EasyAdmin)

| ID | Descripción | Precondición | Pasos | Resultado esperado | Tipo |
|----|-------------|-------------|-------|--------------------|------|
| TC-SUA-50 | Crear colegio básico desde EasyAdmin | U7 en EasyAdmin | GET `/admin/school/new` → nombre "Colegio Delta", slug "delta" → submit | Colegio creado con `active = true, plan = free` | Happy path |
| TC-SUA-51 | Definir período de inscripción desde EasyAdmin | Colegio SA en EasyAdmin | Editar SA → `enrollmentStart = hoy`, `enrollmentEnd = 2026-07-01` → guardar | Campos guardados; `isEnrollmentOpen()` devuelve `true` | Happy path |
| TC-SUA-52 | Cambiar plan de un colegio | Colegio en plan "free" | EasyAdmin edit → plan "premium" | `school.plan = 'premium'` | Happy path |
| TC-SUA-53 | Desactivar un colegio | Colegio activo | EasyAdmin edit → `active = false` | `active = false` guardado | Happy path |

---

### TC-MT — Multitenencia

> Todos estos casos requieren tener activos los dos colegios: **SA (Alpha)** y **SB (Beta)** con sus usuarios correspondientes (ver sección 4.4 y 4.5).

#### TC-MT-CTX — TenantContext: resolución del tenant

| ID | Descripción | Precondición | Pasos | Resultado esperado | Tipo |
|----|-------------|-------------|-------|--------------------|------|
| TC-MT-CTX-01 | TenantContext se inicializa al hacer login | UA1 (Colegio Alpha) autenticado | Iniciar sesión como UA1 | `TenantContext::getCurrentSchool()` devuelve Colegio Alpha para la sesión | Happy path |
| TC-MT-CTX-02 | TenantContext cambia al cambiar usuario | Sesión de UA1 activa; después login como UB1 | Logout + login como UB1 | `TenantContext::getCurrentSchool()` devuelve Colegio Beta | Happy path |
| TC-MT-CTX-03 | Usuario sin colegio asignado no rompe la app | User sin `school_id` (legacy) en BD | Login con ese usuario | La app funciona sin filtro de tenant (compatibilidad retroactiva) | Edge case |
| TC-MT-CTX-04 | Super admin sin school_id | U7 (ROLE_SUPER_ADMIN) sin colegio asignado | Login U7 + GET `/admin` | App funciona; `tenantContext->hasSchool()` = false; sin filtro tenant | Happy path |

#### TC-MT-ISO — Aislamiento de datos entre colegios

| ID | Descripción | Precondición | Pasos | Resultado esperado | Tipo |
|----|-------------|-------------|-------|--------------------|------|
| TC-MT-ISO-01 | Estudiante solo ve cursos de su colegio | UA1 (Alpha), CA1 (Alpha), CB1 (Beta) activos | Login UA1 + GET `/student/courses` | Solo CA1 visible; CB1 no aparece en ninguna sección | Seguridad |
| TC-MT-ISO-02 | Estudiante Beta solo ve cursos de Beta | UB1 (Beta), CA1 (Alpha), CB1 (Beta) | Login UB1 + GET `/student/courses` | Solo CB1 visible; CA1 no aparece | Seguridad |
| TC-MT-ISO-03 | Recomendaciones solo del colegio activo | UA1 con perfil de intereses configurado; CA1 y CB1 en categoría de interés alto | Login UA1 + GET `/student/courses` | Recomendaciones muestran solo CA1 | Seguridad |
| TC-MT-ISO-04 | Chatbot solo informa cursos del colegio | UA1 en `/student/courses` | Abrir chatbot + preguntar "¿Qué cursos hay?" | Respuesta menciona solo cursos de Alpha; no menciona cursos de Beta | Seguridad |
| TC-MT-ISO-05 | Admin Alpha solo ve usuarios de Alpha | UA2 (Admin Alpha) autenticado | GET `/admin/user` | Lista solo incluye usuarios de Colegio Alpha | Seguridad |
| TC-MT-ISO-06 | Admin Alpha solo ve cursos de Alpha | UA2 autenticado | GET `/admin/course` | Lista solo incluye cursos de Colegio Alpha | Seguridad |
| TC-MT-ISO-07 | Admin Alpha solo ve inscripciones de Alpha | UA2 autenticado, inscripciones en ambos colegios | GET `/admin/enrollment` | Solo inscripciones donde el curso pertenece a Alpha | Seguridad |
| TC-MT-ISO-08 | Reportes filtrados por colegio del admin | UA2 (Admin Alpha) | GET `/admin/reports` | Gráficos y tablas solo con datos de Alpha | Seguridad |
| TC-MT-ISO-09 | Alertas de baja inscripción filtradas | UA2, cursos de Alpha y Beta con baja inscripción | GET `/admin/reports/low-enrollment` | Solo cursos de Alpha en la tabla de alertas | Seguridad |
| TC-MT-ISO-10 | Reporte por profesor filtrado por colegio | UA2, profesores de Alpha y Beta | GET `/admin/reports/by-teacher` | Solo profesores y cursos de Alpha | Seguridad |
| TC-MT-ISO-11 | Import CSV asigna school del admin importador | UA2 autenticado | POST `/admin/users/import` con 2 nuevos RUTs | Ambos usuarios creados con `school = Colegio Alpha` | Seguridad |
| TC-MT-ISO-12 | Inscripción masiva solo sobre alumnos del colegio | UA2, CA1, alumnos de Alpha y Beta en grado 3M | POST `/admin/enrollments/bulk` con CA1 + grado 3M | Solo alumnos de Alpha son candidatos; UB1 (Beta) excluido | Seguridad |

#### TC-MT-VOTER — SchoolVoter: acceso cruzado a recursos

| ID | Descripción | Precondición | Pasos | Resultado esperado | Tipo |
|----|-------------|-------------|-------|--------------------|------|
| TC-MT-VOTER-01 | Estudiante Alpha no puede inscribirse en curso de Beta | UA1 autenticado, CB1 existe | POST `/student/enroll/{CB1.id}` | HTTP 403 o flash de error de permisos | Seguridad |
| TC-MT-VOTER-02 | Estudiante Alpha no puede darse de baja de curso de Beta | UA1 inscrito en CB1 por admin manualmente | POST `/student/unenroll/{CB1.id}` como UA1 | HTTP 403 o flash de error | Seguridad |
| TC-MT-VOTER-03 | Profesor Alpha no puede ver inscritos de curso Beta | Profesor de Alpha autenticado, CB1 asignado a prof. Beta | GET `/teacher/courses/{CB1.id}/students` | HTTP 403 | Seguridad |
| TC-MT-VOTER-04 | Profesor Alpha no puede editar curso de Beta | Profesor de Alpha autenticado | POST `/teacher/courses/{CB1.id}/edit` | HTTP 403 | Seguridad |
| TC-MT-VOTER-05 | Admin Alpha no puede editar usuario de Beta | UA2 autenticado | POST `/admin/user/{UB1.id}/edit` con datos modificados | HTTP 403 o redirección con error | Seguridad |
| TC-MT-VOTER-06 | Manipulación directa de ID de curso ajeno | UA1 autenticado, conoce el ID de CB1 | POST `/student/enroll/{CB1.id}` | Bloqueado por SchoolVoter; no se crea enrollment | Seguridad |

#### TC-MT-PER — Período global de inscripción (HU-16)

| ID | Descripción | Precondición | Pasos | Resultado esperado | Tipo |
|----|-------------|-------------|-------|--------------------|------|
| TC-MT-PER-01 | Banner informativo cuando período está abierto con fecha límite | Colegio SA con `enrollmentEnd = 2026-07-01` | Login UA1 + GET `/student/courses` | Banner info "Inscripciones abiertas hasta el 01/07/2026" | Happy path |
| TC-MT-PER-02 | Banner de advertencia cuando período cerrado (end pasado) | Colegio SB con `enrollmentEnd` en el pasado | Login UB1 + GET `/student/courses` | Banner naranja "El período de inscripción está cerrado. El plazo cerró el…" | Happy path |
| TC-MT-PER-03 | Banner muestra fecha de apertura futura | Colegio con `enrollmentStart = 2026-08-01` | Login estudiante del colegio + GET `/student/courses` | Banner "Las inscripciones abren el 01/08/2026 a las HH:MM" | Happy path |
| TC-MT-PER-04 | Inscripción bloqueada cuando período cerrado | SB con `enrollmentEnd` en el pasado, UB1 autenticado | POST `/student/enroll/{CB1.id}` como UB1 | Flash "El período de inscripción del colegio está cerrado." | Seguridad |
| TC-MT-PER-05 | Baja bloqueada cuando período cerrado | SB cerrado, UB1 inscrito en CB1 | POST `/student/unenroll/{CB1.id}` como UB1 | Flash "El período de inscripción del colegio está cerrado." | Seguridad |
| TC-MT-PER-06 | Sin período configurado = siempre abierto | Colegio SA sin `enrollmentStart` ni `enrollmentEnd` | Login UA1 + POST enroll | Inscripción procesada normalmente; sin banner de advertencia | Edge case |
| TC-MT-PER-07 | Administrador configura período de inscripción | UA2 autenticado | GET `/admin/enrollment-period` | Formulario con campos `enrollmentStart` y `enrollmentEnd` | Happy path |
| TC-MT-PER-08 | Guardar período de inscripción | UA2 en `/admin/enrollment-period` | POST con `enrollmentStart = 2026-06-01T08:00` y `enrollmentEnd = 2026-07-15T23:59` | Flash "Período actualizado"; valores persistidos en BD | Happy path |
| TC-MT-PER-09 | Limpiar período de inscripción | UA2 con período ya configurado | Click "Limpiar fechas" + POST | `enrollmentStart = null` y `enrollmentEnd = null`; estado vuelve a "siempre abierto" | Happy path |
| TC-MT-PER-10 | Admin no puede configurar período de otro colegio | UA2 (Admin Alpha) | GET `/admin/enrollment-period` | Formulario muestra datos de Colegio Alpha; no puede ver/editar SA o SB de otro admin | Seguridad |
| TC-MT-PER-11 | Check de período precede al check de deadline de curso | SB con período cerrado, CB1 con `enrollmentDeadline` en el futuro | POST `/student/enroll/{CB1.id}` como UB1 | Error de "período del colegio cerrado" (no de deadline de curso) | Seguridad |
| TC-MT-PER-12 | Período abierto entre start y end | SA con `enrollmentStart` = ayer, `enrollmentEnd` = mañana | Login UA1 + POST enroll | Inscripción exitosa | Happy path |
| TC-MT-PER-13 | Período cerrado: start en el futuro | SA con `enrollmentStart` = mañana | Login UA1 + POST enroll | Flash "El período de inscripción del colegio está cerrado." | Edge case |

#### TC-MT-EASY — EasyAdmin filtrado por tenant

| ID | Descripción | Precondición | Pasos | Resultado esperado | Tipo |
|----|-------------|-------------|-------|--------------------|------|
| TC-MT-EASY-01 | Nuevo curso en EasyAdmin hereda school del admin | UA2 (Admin Alpha) en `/admin/course/new` | Completar formulario + submit | `course.school = Colegio Alpha` | Happy path |
| TC-MT-EASY-02 | Nuevo usuario en EasyAdmin hereda school del admin | UA2 en `/admin/user/new` | Completar formulario + submit | `user.school = Colegio Alpha` | Happy path |
| TC-MT-EASY-03 | Super admin puede crear curso sin school filter | U7 en `/admin/course/new` | Completar formulario + submit | Curso creado (sin restricción de tenant) | Happy path |
| TC-MT-EASY-04 | Búsqueda en EasyAdmin respeta tenant | UA2 busca usuario de Colegio Beta por nombre | Barra de búsqueda en `/admin/user?query=Beta` | 0 resultados (usuario de Beta no visible para Admin Alpha) | Seguridad |
| TC-MT-EASY-05 | Filtros de EasyAdmin respetan tenant | UA2 filtra inscripciones por curso de Colegio Beta | GET `/admin/enrollment?filters[course]={CB1.id}` | 0 resultados | Seguridad |

---

### TC-SEC — Seguridad transversal

| ID | Descripción | Actor | Pasos | Resultado esperado |
|----|-------------|-------|-------|--------------------|
| TC-SEC-01 | Estudiante no accede a rutas de profesor | ROLE_STUDENT | GET `/teacher/courses` | HTTP 403 |
| TC-SEC-02 | Estudiante no accede a rutas de admin | ROLE_STUDENT | GET `/admin/reports` | HTTP 403 |
| TC-SEC-03 | Profesor no accede a rutas de admin | ROLE_TEACHER | GET `/admin` | HTTP 403 |
| TC-SEC-04 | Apoderado no accede a rutas de estudiante | ROLE_GUARDIAN | GET `/student/courses` | HTTP 403 |
| TC-SEC-05 | Apoderado no puede inscribir a su pupilo | ROLE_GUARDIAN | POST `/student/enroll/{id}` | HTTP 403 |
| TC-SEC-06 | Profesor no ve alumnos de curso ajeno | ROLE_TEACHER | GET `/teacher/courses/{id}/students` (curso ajeno) | HTTP 403 |
| TC-SEC-07 | Profesor no edita curso ajeno | ROLE_TEACHER | POST `/teacher/courses/{id}/edit` (curso ajeno) | HTTP 403 |
| TC-SEC-08 | Estudiante no puede darse de baja por otro | ROLE_STUDENT | POST `/student/unenroll/{id}` donde no está inscrito | Flash "No estás inscrito en este curso" |
| TC-SEC-09 | Tenant isolation: estudiante no ve cursos de otro colegio | ROLE_STUDENT (Colegio A) | GET `/student/courses` | Solo cursos con `school = Colegio A` |
| TC-SEC-10 | Tenant isolation: admin no ve usuarios de otro colegio | ROLE_ADMIN (Colegio A) | GET `/admin/user` | Solo usuarios con `school = Colegio A` |
| TC-SEC-11 | Tenant isolation: import asigna escuela del admin | ROLE_ADMIN (Colegio A) | POST import CSV | Nuevos usuarios con `school = Colegio A` |
| TC-SEC-12 | CSRF en formulario de inscripción | ROLE_STUDENT | Manipular `_token` en POST enroll | Request rechazado con error de token |
| TC-SEC-13 | Usuario inactivo no puede iniciar sesión | Usuario con `active = false` | GET `/login` + credenciales | Acceso denegado (cuenta deshabilitada) |
| TC-SEC-14 | Acceso directo a URL de admin sin sesión | Anónimo | GET `/admin` | Redirige a `/login` |
| TC-SEC-15 | Acceso directo a super-admin sin sesión | Anónimo | GET `/super-admin` | Redirige a `/login` |
| TC-SEC-16 | Acceso a super-admin con ROLE_ADMIN | UA2 (ROLE_ADMIN) | GET `/super-admin` | HTTP 403 |
| TC-SEC-17 | Acceso a super-admin con ROLE_STUDENT | UA1 | GET `/super-admin` | HTTP 403 |
| TC-SEC-18 | Acceso a `/admin/enrollment-period` sin autenticar | Anónimo | GET `/admin/enrollment-period` | Redirige a `/login` |
| TC-SEC-19 | Acceso a `/admin/enrollment-period` con ROLE_STUDENT | UA1 | GET `/admin/enrollment-period` | HTTP 403 |
| TC-SEC-20 | Tenant isolation: endpoint de onboarding solo para super admin | UA2 (ROLE_ADMIN) | GET `/super-admin/onboard` | HTTP 403 |
| TC-SEC-21 | Inyección de `school_id` en formulario de inscripción | UA1 autenticado | POST `/student/enroll/{CA1.id}` con campo extra `school_id = SB.id` oculto | Inscripción se crea con `school = Alpha` (del tenant context); el parámetro POST es ignorado | Seguridad |

---

## 6. Matriz de cobertura por Historia de Usuario

| HU | Descripción | Casos de prueba | Estado |
|----|-------------|-----------------|--------|
| HU-1 | Ver lista de cursos disponibles | TC-STU-01, TC-STU-02 | ✅ Cubierta |
| HU-2 | Filtrar cursos | TC-STU-03..08 | ✅ Cubierta |
| HU-3 | Inscripción en curso | TC-STU-10..16 | ✅ Cubierta |
| HU-4 | Darse de baja | TC-STU-20..23 | ✅ Cubierta |
| HU-5 | Ver cursos inscritos | TC-STU-30, TC-STU-31 | ✅ Cubierta |
| HU-6 | Cuestionario de intereses | TC-STU-40, TC-STU-41 | ✅ Cubierta |
| HU-7 | Ver recomendaciones | TC-STU-42..44 | ✅ Cubierta |
| HU-8 | Refinar recomendaciones | TC-STU-45..48 | ✅ Cubierta |
| HU-9 | Chatbot asistente | TC-STU-50..56 | ✅ Cubierta |
| HU-10 | Ver razón de recomendación | TC-STU-49 | ✅ Cubierta |
| HU-11 | Profesor: ver mis cursos | TC-TCH-01..03 | ✅ Cubierta |
| HU-12 | Profesor: ver inscritos | TC-TCH-04, TC-TCH-05 | ✅ Cubierta |
| HU-13 | Profesor: ver cupo total | TC-TCH-06 | ✅ Cubierta |
| HU-14 | Admin: crear/editar/desactivar cursos | TC-TCH-07..15, TC-ADM-20 | ✅ Cubierta |
| HU-15 | Admin: cupos, horarios, fechas | TC-ADM-21, TC-ADM-22 | ✅ Cubierta |
| HU-16 | Período global de inscripción por colegio | TC-MT-PER-01..13 | ✅ Cubierta |
| HU-16b | Admin: activar/desactivar curso individual | TC-TCH-15, TC-TCH-16 | ✅ Cubierta |
| HU-17 | Admin: reportes de ocupación | TC-ADM-40, TC-ADM-41 | ✅ Cubierta |
| HU-18 | Admin: exportar listas Excel | TC-TCH-22, TC-TCH-23, TC-ADM-42 | ✅ Cubierta |
| HU-19 | Admin: insights de IA | TC-ADM-50..52 | ✅ Cubierta |
| HU-20 | Admin: alertas de baja inscripción | TC-ADM-45..49 | ✅ Cubierta |
| HU-21 | Admin: gestionar usuarios | TC-ADM-01..03 | ✅ Cubierta |
| HU-22 | Admin: registrar estudiantes | TC-ADM-10..17 | ✅ Cubierta |
| HU-23 | Admin: registrar profesores | TC-ADM-06 | ✅ Cubierta |
| HU-24 | Admin: registrar apoderados | TC-ADM-07 | ✅ Cubierta |
| HU-25 | Admin: editar/desactivar usuarios | TC-ADM-03 | ✅ Cubierta |
| HU-26 | Admin: vincular apoderado-estudiante | TC-ADM-04, TC-ADM-05 | ✅ Cubierta |
| HU-27 | Admin: asignar profesor a curso | TC-ADM-23 | ✅ Cubierta |
| HU-28 | Admin: cambiar profesor | TC-ADM-24 | ✅ Cubierta |
| HU-29 | Admin: cursos por profesor | TC-ADM-43, TC-ADM-44 | ✅ Cubierta |
| HU-30 | Apoderado: ver cursos del pupilo | TC-GRD-01, TC-GRD-02 | ✅ Cubierta |
| HU-31 | Apoderado: ver horario del pupilo | TC-GRD-03, TC-GRD-04 | ✅ Cubierta |
| HU-32 | Sistema de recomendación < 3s | TC-STU-42 (observacional) | ⚠️ Sin benchmark formal |
| HU-33 | Resúmenes en lenguaje natural | TC-ADM-50..52 | ✅ Cubierta |
| HU-34 | Chatbot preciso | TC-STU-52, TC-STU-53 | ✅ Cubierta |
| **HDU-S5-01** | Entidad School | TC-SUA-50, TC-SUA-52..53 | ✅ Cubierta |
| **HDU-S5-02** | FK school_id en User y Course | TC-MT-ISO-01..12 | ✅ Cubierta |
| **HDU-S5-03** | TenantContext | TC-MT-CTX-01..04 | ✅ Cubierta |
| **HDU-S5-04** | TenantAwareRepository | TC-MT-ISO-01..12 | ✅ Cubierta |
| **HDU-S6-01** | Controllers tenant-aware | TC-MT-ISO-01..12 | ✅ Cubierta |
| **HDU-S6-02** | Servicios tenant-aware | TC-MT-ISO-03, TC-MT-ISO-04 | ✅ Cubierta |
| **HDU-S6-03** | SchoolVoter | TC-MT-VOTER-01..06 | ✅ Cubierta |
| **HDU-S6-04** | EasyAdmin por colegio | TC-MT-EASY-01..05, TC-ADM-08, TC-ADM-25 | ✅ Cubierta |
| **HDU-S6-05** | SuperAdmin dashboard / bypass | TC-SUA-05..06, TC-MT-EASY-03 | ✅ Cubierta |
| **HDU-S7-01** | Crear colegio + admin inicial | TC-SUA-20..27 | ✅ Cubierta |
| **HDU-S7-02** | Email de bienvenida | TC-SUA-30..33 | ✅ Cubierta |
| **HDU-S7-03** | Categorías por defecto | TC-SUA-40..42 | ✅ Cubierta |
| **HDU-S7-04** | Import masivo respeta tenant | TC-MT-ISO-11, TC-ADM-15 | ✅ Cubierta |

---

## 7. Criterios de aceptación

| Criterio | Descripción |
|----------|-------------|
| **Funcional** | El 100% de los casos "Happy path" deben pasar |
| **Seguridad** | El 100% de los casos TC-SEC-* y casos de acceso cruzado deben pasar |
| **Error path** | Al menos el 90% de los casos de error deben mostrar un mensaje descriptivo al usuario |
| **Edge cases** | Al menos el 80% de los casos borde deben ser manejados sin error 500 |
| **Tenant isolation** | TC-SEC-09, TC-SEC-10, TC-SEC-11 y toda la sección TC-MT-ISO son **bloqueantes para producción multi-tenant** |
| **SchoolVoter** | TC-MT-VOTER-01..06 son bloqueantes; ningún estudiante/profesor debe operar sobre recursos de otro colegio |
| **Onboarding** | TC-SUA-22, TC-SUA-23, TC-SUA-40 deben pasar antes de habilitar autoregistro de colegios |
| **Período inscripción** | TC-MT-PER-04 y TC-MT-PER-05 son bloqueantes (sin period check, estudiantes pueden inscribirse fuera de plazo) |

---

## 8. Entorno de pruebas

```bash
# Levantar entorno
symfony server:start

# Crear base de datos de prueba
php bin/console doctrine:database:create --env=test
php bin/console doctrine:migrations:migrate --env=test

# Cargar fixtures (si están disponibles)
php bin/console doctrine:fixtures:load --env=test

# Ejecutar tests automáticos
php bin/phpunit

# Verificar rutas disponibles
php bin/console debug:router

# Validar el contenedor
php bin/console lint:container
```

---

*Documento generado para el proyecto ElectivoIA — Sprint 7 (v2.0, actualizado con multitenencia)*
