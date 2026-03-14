# Product Requirements Document — ElectivoIA

**Versión:** 2.0
**Fecha:** 14 de marzo de 2026
**Autor:** Mauricio Ojeda
**Repositorio:** https://github.com/maur-ojeda/electivoia
**Servidor de Producción:** 167.71.23.86

---

## 1. Resumen Ejecutivo

**ElectivoIA** es una plataforma web de gestión educativa orientada a colegios chilenos de enseñanza media. Permite a estudiantes explorar y postular a cursos electivos, recibir recomendaciones personalizadas mediante IA, y le entrega a profesores, apoderados y administradores las herramientas necesarias para gestionar el proceso de inscripción, asistencia y reportería académica.

El diferenciador clave del producto es su **motor de priorización basado en promedio de notas**: cuando un curso está lleno, un estudiante con mayor promedio puede desplazar automáticamente al inscrito con menor rendimiento, incentivando la meritocracia académica. A esto se suma un **chatbot conversacional con Gemini AI** que guía a los estudiantes en la selección de electivos.

---

## 2. Problema a Resolver

Los colegios gestionan la inscripción a electivos mediante formularios físicos o correos, lo que genera:

- **Inequidad en el acceso**: quien llega primero se inscribe, sin considerar mérito académico.
- **Falta de visibilidad**: estudiantes no conocen en detalle los cursos disponibles ni reciben recomendaciones personalizadas.
- **Carga administrativa**: profesores y coordinadores gestionan listas manualmente, con errores y duplicaciones.
- **Nula trazabilidad**: sin historial digital de inscripciones ni asistencias accesible en tiempo real para apoderados.

---

## 3. Usuarios Objetivo

| Rol | Descripción | Necesidad Principal |
|---|---|---|
| **Estudiante** (`ROLE_STUDENT`) | Alumno de enseñanza media chilena | Inscribirse en electivos, recibir recomendaciones, ver su asistencia |
| **Profesor** (`ROLE_TEACHER`) | Docente que imparte electivos | Gestionar sus cursos, registrar asistencia, ver reportes |
| **Apoderado** (`ROLE_GUARDIAN`) | Tutor legal del estudiante | Monitorear cursos y asistencia de sus pupilos |
| **Administrador de Colegio** (`ROLE_ADMIN`) | Coordinador académico / TI del colegio | Gestión completa del sistema, usuarios, reportes y exportaciones |
| **Super Administrador** (`ROLE_SUPER_ADMIN`) | Equipo ElectivoIA | Gestión de colegios en la plataforma (multitenencia) |

> **Nota:** `ROLE_SUPER_ADMIN` es un rol futuro — no existe en el MVP actual.

---

## 4. Objetivos del Producto

| # | Objetivo | Métrica de Éxito |
|---|---|---|
| O1 | Digitalizar el proceso de inscripción a electivos | > 80% de estudiantes inscriben vía plataforma |
| O2 | Reducir el tiempo de inscripción por alumno | < 3 minutos por inscripción |
| O3 | Entregar recomendaciones relevantes vía IA | > 70% de aceptación de cursos sugeridos |
| O4 | Garantizar disponibilidad del sistema | > 99% uptime en período de inscripciones |
| O5 | Satisfacción general de usuarios | > 4/5 en encuesta post-uso |

---

## 5. Stack Tecnológico

### Backend
- **PHP 8.2+** — Lenguaje principal
- **Symfony 7.3** — Framework web
- **Doctrine ORM 3.5** — Mapeo objeto-relacional
- **EasyAdmin 4.26** — Panel de administración

### Frontend
- **Twig 3.0** — Motor de plantillas
- **JavaScript (Vanilla)** — Interactividad del cliente
- **Webpack Encore** — Bundler de assets
- **Bootstrap 5** — Framework CSS

### Base de Datos
- **MySQL / MariaDB** — Base de datos relacional (compatible con PostgreSQL)

### Servicios Externos
- **Google Gemini 2.5 Flash Lite** — Chatbot IA
- **Symfony Mailer** — Envío de correos electrónicos *(configurado, aún no usado en producción)*
- **PHPSpreadsheet 5.1** — Exportación Excel

### DevOps
- **Ubuntu Server** — Servidor de producción
- **PHP-FPM 8.3** — FastCGI Process Manager
- **Nginx / Apache** — Servidor web
- **Git / Composer / NPM** — Control de versiones y dependencias

---

## 6. Estado Actual del Sistema (MVP — v1.0)

### 6.1 Lo que está IMPLEMENTADO y FUNCIONA

| Módulo | Funcionalidad | Archivo Principal |
|---|---|---|
| **Auth** | Login con RUT, redirección por rol | `SecurityController`, `LoginSuccessHandler` |
| **Catálogo** | Listado de cursos filtrado por grado del alumno | `StudentController::courses()` |
| **Catálogo** | Filtros: categoría, cupos libres, búsqueda por texto | `StudentController::courses()` |
| **Inscripción** | Inscripción directa con validación de cupo y fecha | `EnrollmentService::enrollStudent()` |
| **Inscripción** | Motor de desplazamiento por promedio (prioridad meritocrática) | `EnrollmentService::enrollStudent()` |
| **Inscripción** | Baja voluntaria del estudiante (unenroll) | `StudentController::unenroll()` |
| **Mis Cursos** | Estudiante ve sus inscripciones activas | `StudentController::enrollments()` |
| **Perfil** | Estudiante completa perfil de intereses por área | `StudentController::profile()` |
| **Recomendaciones** | Sugerencias basadas en `InterestProfile` con razón visible | `RecommendationService` |
| **Chatbot** | Chat con Gemini AI con contexto de cursos | `GeminiChatbotService`, `ChatbotController` |
| **Asistencia** | Profesor registra asistencia (Presente/Ausente/Justificado) | `TeacherController::attendance()` |
| **Asistencia** | Profesor ve historial de asistencia por alumno/fecha | `TeacherController::attendance()` |
| **Apoderado** | Dashboard con inscripciones de sus pupilos | `GuardianController::dashboard()` |
| **Admin CRUD** | Gestión completa de Usuarios, Cursos, Categorías, Inscripciones | EasyAdmin (`/admin`) |
| **Admin CRUD** | Asignación de roles a usuarios | `UserCrudController` |
| **Admin CRUD** | Generación automática de contraseña desde RUT | `UserCrudController::createEntity()` |
| **Admin Reportes** | Dashboard con ocupación por curso, distribución por categoría, carga docente | `AdminReportController` |
| **Exportación** | Excel con lista de estudiantes por curso | `ExportController`, `TeacherController::exportStudents()` |

### 6.2 Lo que está AUSENTE o INCOMPLETO

| US | Funcionalidad Faltante | Impacto | Prioridad |
|---|---|---|---|
| US-007 | Estudiante ve su propia asistencia | Alto — los alumnos no pueden monitorear su participación | Alta |
| US-101 | Profesor crea/edita/elimina sus propios cursos | Medio — hoy solo el admin puede hacerlo via EasyAdmin | Media |
| US-106 | Reporte de asistencia para el profesor | Medio — solo existe exportación de lista de alumnos | Media |
| US-202 | Apoderado ve asistencia de sus pupilos | Alto — el dashboard solo muestra inscripciones | Alta |
| US-305 | Exportación general (usuarios, asistencias, cursos) | Medio — solo exporta estudiantes por curso | Media |
| US-306 | Inscripción masiva por importación de archivo | Bajo — útil para administradores en períodos de matrícula | Baja |
| US-001 | Registro público de estudiantes | Bajo — hoy solo el admin crea cuentas | Baja |
| US-001 | Email de confirmación al crear cuenta | Bajo — Mailer configurado pero sin uso | Baja |

---

## 7. Modelo de Datos Actual

```
┌───────────────────────────────────────────────────────────────┐
│  USER                                                          │
│  id · rut(unique) · email · fullName · roles(JSON)            │
│  grade · averageGrade · gender · active · password            │
└──┬──────────────────────────────────────────┬─────────────────┘
   │ 1:N (teacher)                             │ N:N (guardians)
   ▼                                           ▼
┌──────────────────────┐             ┌─────────────────────────┐
│  COURSE              │             │  USER (auto-rel)        │
│  id · name · desc    │             │  tabla: user_guardian   │
│  maxCapacity         │             │  guardian_id ↔ student_id│
│  currentEnrollment   │             └─────────────────────────┘
│  isActive · deadline │
│  targetGrades(JSON)  │
│  teacher_id → USER   │
│  category_id → CAT   │
└──┬───────────────────┘
   │ 1:N                    1:1
   ├──────────────┐    ┌────────────────────────┐
   ▼              ▼    │  INTEREST_PROFILE      │
┌──────────┐ ┌──────────┐  id · interests(JSON) │
│ENROLLMENT│ │ATTENDANCE│  student_id → USER    │
│student_id│ │student_id│ └────────────────────┘
│course_id │ │course_id │
│enrolledAt│ │date·status│
└──────────┘ └──────────┘

┌──────────────────┐
│  COURSE_CATEGORY │
│  id · name · area│
└──────────────────┘
```

### Campos Clave

| Entidad | Campo | Descripción |
|---|---|---|
| `User` | `rut` | Identificador único chileno (VARCHAR 12, UNIQUE) |
| `User` | `grade` | Grado del alumno (`3M`, `4M`) |
| `User` | `averageGrade` | Promedio de notas (FLOAT, usado en priorización meritocrática) |
| `Course` | `targetGrades` | JSON: grados habilitados para ver/inscribirse al curso |
| `Course` | `maxCapacity` | Cupo máximo del curso |
| `Course` | `currentEnrollment` | Contador sincronizado de inscritos |
| `Course` | `enrollmentDeadline` | Fecha límite de inscripción (DATETIME) |
| `InterestProfile` | `interests` | JSON: área → nivel de interés (0–N) |
| `Attendance` | `status` | Estado: `present`, `absent`, `justified` |

---

## 8. Reglas de Negocio Críticas

### RN-01: Filtrado por Grado
Un estudiante solo ve los cursos que tienen su grado en `targetGrades`. Cursos sin `targetGrades` asignado no son visibles para ningún estudiante.

### RN-02: Prioridad Meritocrática (Desplazamiento)
Si un curso está en capacidad máxima y el estudiante que quiere inscribirse tiene `averageGrade` mayor al inscrito con menor promedio, el sistema:
1. Elimina la inscripción del estudiante con menor promedio.
2. Crea la inscripción del nuevo estudiante.
3. Notifica ambas acciones mediante flash messages.

Si el estudiante no tiene `averageGrade` registrado, no puede desplazar a nadie.

### RN-03: Fecha Límite
No se permite inscribirse en un curso cuya `enrollmentDeadline` ya pasó.

### RN-04: Sin Duplicados
Un estudiante no puede inscribirse dos veces en el mismo curso.

### RN-05: Contexto del Chatbot
El chatbot recibe en cada llamada la lista actualizada de cursos activos (nombre, categoría, descripción, profesor, cupos) para responder con información vigente, sin almacenar historial de conversación entre sesiones.

---

## 9. Flujos Principales

### Flujo de Inscripción

```
Estudiante → Ver catálogo (filtrado por grado)
          → Seleccionar curso
          → POST /student/enroll/{id}
          → ¿Hay cupo?
              Sí → Inscribir directamente → Flash "Inscripción exitosa"
              No → ¿Tiene averageGrade?
                     No  → Flash "Sin promedio registrado"
                     Sí  → ¿Su promedio > mínimo inscrito?
                             Sí → Desplazar al de menor promedio → Inscribir → Flash "Reemplazaste a..."
                             No → Flash "Sin prioridad suficiente"
          → Redirigir a /student/courses
```

### Flujo del Chatbot

```
Estudiante → Escribe mensaje en widget
          → POST /api/chatbot { message: "..." }
          → GeminiChatbotService::chat()
              → buildCoursesContext() — consulta DB de cursos activos
              → buildSystemPrompt(contexto)
              → Llamada a Gemini API
              → Retorna { success: true, message: "..." }
          → Widget renderiza respuesta
```

---

## 10. Restricciones Técnicas

| Categoría | Detalle |
|---|---|
| **Lenguaje** | PHP 8.2+ |
| **Framework** | Symfony 7.3 |
| **ORM** | Doctrine 3.5 |
| **BD** | MySQL / MariaDB (compatible PostgreSQL) |
| **Frontend** | Twig 3 + Bootstrap 5 + Vanilla JS + Webpack Encore |
| **IA** | Google Gemini 2.5 Flash Lite (API externa, requiere clave en `.env.local`) |
| **Servidor** | Ubuntu Server, Nginx/Apache, PHP-FPM 8.3 |
| **Auth** | Symfony Security (sesiones, sin JWT en MVP) |

---

## 11. Consideraciones de Seguridad Actuales

- Autenticación requerida en todas las rutas (Symfony Firewall)
- Control de acceso por rol (`#[IsGranted]`) en cada controlador
- Un profesor solo puede gestionar sus propios cursos (validación manual en controlador)
- Un apoderado solo puede ver datos de sus pupilos asignados
- La clave de Gemini API se almacena en `.env.local` (excluido de Git)
- **GAP:** No existe aislamiento de datos entre colegios (requerido para multitenencia)

---

## 12. Arquitectura de Multitenencia (Diseño — Fase 3)

### 12.1 Modelo

Se adoptará el modelo **single database, tenant discriminator** (columna `school_id` en cada entidad), ya que:
- Es la opción más simple con Doctrine ORM
- Permite consultas eficientes con índices
- No requiere cambios en la infraestructura de BD

### 12.2 Nueva Entidad `School`

```
SCHOOL
  id            INT (PK)
  name          VARCHAR(255)        — "Colegio San Ignacio"
  slug          VARCHAR(100) UNIQUE — "san-ignacio" (usado en URL/subdominio)
  rbd           VARCHAR(20)         — Rol Base de Datos del MINEDUC
  active        BOOLEAN DEFAULT true
  plan          VARCHAR(50)         — "free", "basic", "premium"
  createdAt     DATETIME
```

### 12.3 Cambios en Entidades Existentes

| Entidad | Campo a Agregar | Notas |
|---|---|---|
| `User` | `school_id → SCHOOL` | Todos los roles (estudiante, profesor, admin, apoderado) |
| `Course` | `school_id → SCHOOL` | Cursos son propiedad de un colegio |
| `CourseCategory` | Queda **global** | Las categorías son compartidas entre todos los colegios |
| `Attendance` | Hereda del `Course` | Sin FK directa |
| `Enrollment` | Hereda del `Course` | Sin FK directa |
| `InterestProfile` | Hereda del `User` | Sin FK directa |

### 12.4 Estrategia de Resolución del Tenant

Se usará **autenticación como discriminador**: el colegio del usuario se determina al login a través del campo `school_id` del `User` autenticado. Se crea un servicio `TenantContext` que almacena la escuela activa durante el request.

```
Login → User::school → TenantContext::setCurrentSchool(School)
     → Todos los servicios/controllers inyectan TenantContext
     → Todas las queries filtran por TenantContext::getCurrentSchool()
```

### 12.5 Roles en Contexto Multitenente

| Rol | Alcance |
|---|---|
| `ROLE_SUPER_ADMIN` | Ve y gestiona todos los colegios |
| `ROLE_ADMIN` | Ve y gestiona solo su colegio (`school_id`) |
| `ROLE_TEACHER` | Opera solo dentro de su colegio |
| `ROLE_STUDENT` | Opera solo dentro de su colegio |
| `ROLE_GUARDIAN` | Opera solo dentro de su colegio |

### 12.6 Componentes a Crear

| Componente | Tipo | Descripción |
|---|---|---|
| `School` | Entity | Nueva entidad de colegio |
| `TenantContext` | Service | Almacena y provee el colegio activo en el request |
| `SchoolVoter` | Security Voter | Valida que el recurso solicitado pertenece al colegio activo |
| `TenantAwareRepository` | Repository Base | Clase base que inyecta automáticamente el filtro de tenant |
| `SuperAdminDashboard` | Controller | Panel para gestión de colegios (ROLE_SUPER_ADMIN) |

### 12.7 Archivos Afectados por Multitenencia

```
ENTIDADES        src/Entity/User.php, Course.php
REPOSITORIOS     Todos los 6 repositorios (agregar tenant filter)
SERVICIOS        EnrollmentService, RecommendationService, GeminiChatbotService
CONTROLADORES    StudentController, TeacherController, GuardianController,
                 AdminReportController, ExportController, EnrollmentController
                 + todos los Admin/*CrudController
SEGURIDAD        security.yaml, LoginSuccessHandler
NUEVOS           School.php, TenantContext.php, SchoolVoter.php,
                 TenantAwareRepository.php, SuperAdminDashboard
MIGRACIONES      Schema de school + school_id en User y Course
```

---

## 13. Roadmap por Sprints

> Cada sprint = 2 semanas.
> Convención de prioridad: **P0** = bloqueante, **P1** = alta, **P2** = media, **P3** = nice-to-have.

---

### Sprint 1 — Completar MVP (Funcionalidades Faltantes Críticas)

**Objetivo:** Cerrar las brechas de las HDU del MVP que están documentadas pero no implementadas.

| ID | Historia | Prioridad |
|---|---|---|
| HDU-S1-01 | Como estudiante, quiero ver mi historial de asistencia por curso, para monitorear mi participación | P0 |
| HDU-S1-02 | Como apoderado, quiero ver la asistencia de mis pupilos por curso y fecha, para asegurarme de su participación | P0 |
| HDU-S1-03 | Como profesor, quiero ver un reporte de asistencia de mi curso (porcentaje por alumno, fechas), para evaluar la participación | P1 |
| HDU-S1-04 | Como administrador, quiero exportar el registro completo de asistencias a Excel, para análisis externo | P1 |

**Criterios de Aceptación:**

**HDU-S1-01 — Asistencia del Estudiante**
- Accesible desde `GET /student/attendance` o desde "Mis Cursos"
- Muestra tabla: Curso / Fecha / Estado (Presente/Ausente/Justificado)
- Muestra resumen: % de asistencia por curso
- Solo muestra datos del estudiante autenticado

**HDU-S1-02 — Asistencia del Apoderado**
- En el dashboard del apoderado, expandir cada pupilo para ver su asistencia
- Muestra: Curso / Fecha / Estado
- Filtro por pupilo (si tiene más de uno)
- Solo datos de los pupilos asignados al apoderado

**HDU-S1-03 — Reporte de Asistencia del Profesor**
- Accesible desde `GET /teacher/report/{courseId}`
- Tabla: Alumno / Total sesiones / Presentes / Ausentes / Justificados / % Asistencia
- Descarga del reporte en Excel (`/teacher/report/{courseId}/export`)

**HDU-S1-04 — Exportación de Asistencias (Admin)**
- `GET /admin/export/attendance` — exporta todas las asistencias
- Columnas: RUT, Nombre, Curso, Fecha, Estado, Profesor
- Filtros opcionales: curso, rango de fechas

---

### Sprint 2 — Gestión de Cursos por el Profesor

**Objetivo:** Dar autonomía al profesor para gestionar su oferta académica sin depender del administrador.

| ID | Historia | Prioridad |
|---|---|---|
| HDU-S2-01 | Como profesor, quiero crear nuevos cursos electivos con nombre, descripción, capacidad y grados objetivo, para ofrecer mi asignatura sin necesitar al admin | P1 |
| HDU-S2-02 | Como profesor, quiero editar la información de mis cursos (capacidad, fecha límite, descripción), para mantenerlos actualizados | P1 |
| HDU-S2-03 | Como profesor, quiero marcar un curso como inactivo (sin eliminarlo), para cerrarlo a nuevas inscripciones | P1 |
| HDU-S2-04 | Como profesor, quiero ver la lista detallada de estudiantes inscritos en mi curso (con nombre, RUT, grado y promedio), para conocer mi grupo | P2 |

**Criterios de Aceptación:**

**HDU-S2-01 — Crear Curso**
- Formulario en `GET /teacher/courses/new`
- Campos obligatorios: nombre, capacidad máxima, grados objetivo (3M/4M)
- Campos opcionales: descripción, fecha límite, categoría
- `teacher` se asigna automáticamente al profesor autenticado
- `currentEnrollment` inicia en 0, `isActive` en true

**HDU-S2-02 — Editar Curso**
- Ruta: `GET /teacher/courses/{id}/edit`
- Solo el profesor dueño puede editar (validación `$course->getTeacher() === $teacher`)
- No puede reducir `maxCapacity` por debajo de `currentEnrollment`

**HDU-S2-03 — Desactivar Curso**
- Botón "Cerrar inscripciones" en la vista de cursos del profesor
- Cambia `isActive = false` sin eliminar datos
- El curso desaparece del catálogo de estudiantes

**HDU-S2-04 — Lista de Inscritos**
- Ruta: `GET /teacher/courses/{id}/students`
- Tabla: Nombre / RUT / Grado / Promedio / Fecha de inscripción
- Opción de exportar la lista a Excel (ya existe en `ExportController`)

---

### Sprint 3 — Comunicaciones y Experiencia de Usuario

**Objetivo:** Activar el canal de email y mejorar la experiencia del estudiante.

| ID | Historia | Prioridad |
|---|---|---|
| HDU-S3-01 | Como estudiante, quiero recibir un email de confirmación cuando me inscribo en un curso, para tener registro de mi matrícula | P1 |
| HDU-S3-02 | Como estudiante, quiero recibir un email de notificación si soy desplazado de un curso por otro alumno con mayor promedio, para poder buscar una alternativa | P0 |
| HDU-S3-03 | Como estudiante, quiero ver en mi dashboard un resumen de mi asistencia acumulada y mis cursos activos, para tener visión global de mi situación | P2 |
| HDU-S3-04 | Como estudiante, quiero que el chatbot recuerde el contexto de la conversación durante mi sesión activa, para no tener que repetir información | P2 |
| HDU-S3-05 | Como administrador, quiero enviar un email masivo a todos los estudiantes de un grado anunciando el inicio del período de inscripciones, para asegurar participación | P2 |

**Criterios de Aceptación:**

**HDU-S3-01 / S3-02 — Emails Transaccionales**
- Usar `Symfony Mailer` (ya configurado en `composer.json`)
- Plantillas Twig para emails: `templates/email/enrollment_confirmation.html.twig`, `templates/email/enrollment_displaced.html.twig`
- Email de confirmación incluye: nombre del curso, profesor, fecha de inscripción
- Email de desplazamiento incluye: nombre del curso y mensaje de alternativas
- Envío asíncrono (Messenger o llamada directa) para no bloquear el request

**HDU-S3-03 — Dashboard Enriquecido del Estudiante**
- Nueva vista o sección en `/student/dashboard`
- Widgets: Cursos activos (conteo), % asistencia promedio, próximas clases
- Acceso rápido a "Mis Cursos" e "Inscribirse"

**HDU-S3-04 — Historial de Chat en Sesión**
- El historial se almacena en la sesión PHP (`$session->set('chatHistory', [...])`)
- Se envía como contexto previo a Gemini en cada llamada
- Se limpia al cerrar sesión

---

### Sprint 4 — Administración y Acceso Masivo

**Objetivo:** Facilitar la operación a administradores durante períodos de alta demanda.

| ID | Historia | Prioridad |
|---|---|---|
| HDU-S4-01 | Como administrador, quiero importar estudiantes desde un archivo CSV/Excel para crear cuentas masivamente al inicio del año escolar, para agilizar el onboarding | P1 |
| HDU-S4-02 | Como administrador, quiero inscribir masivamente a un grupo de estudiantes en un curso (por grado o lista), para gestionar asignaciones directas | P2 |
| HDU-S4-03 | Como administrador, quiero ver un reporte comparativo de inscripciones por grado y categoría, para detectar desequilibrios en la oferta | P2 |
| HDU-S4-04 | Como administrador, quiero poder crear una cuenta para un nuevo estudiante desde la interfaz web pública (o enviarle un enlace de activación), para simplificar el alta | P3 |

**Criterios de Aceptación:**

**HDU-S4-01 — Importación CSV**
- Ruta: `POST /admin/users/import`
- Formato CSV: RUT, Nombre Completo, Grado, Promedio, Email (opcional)
- Validación de RUT antes de insertar
- Reporte de resultado: X creados, Y duplicados, Z errores
- Contraseña inicial = RUT (sin puntos ni guión), obligatoria de cambiar

**HDU-S4-02 — Inscripción Masiva**
- Ruta: `POST /admin/enrollments/bulk`
- Seleccionar curso + seleccionar grado → inscribir todos los estudiantes de ese grado
- Respeta la regla de cupo máximo y fecha límite
- Muestra resumen de inscritos y rechazados

**HDU-S4-03 — Reporte Comparativo**
- Ampliar `AdminReportController` con vista de tabla cruzada: Grado vs Categoría vs Inscripciones
- Exportable a Excel

---

### Sprint 5 — Multitenencia: Fundación del Modelo de Datos

**Objetivo:** Crear la infraestructura base de multitenencia sin romper el funcionamiento actual (single-tenant).

| ID | Historia | Prioridad |
|---|---|---|
| HDU-S5-01 | Como arquitecto del sistema, necesito una entidad `School` para representar cada colegio cliente en la plataforma | P0 |
| HDU-S5-02 | Como arquitecto del sistema, necesito que cada `User` y cada `Course` estén asociados a un colegio, para preparar el aislamiento de datos | P0 |
| HDU-S5-03 | Como arquitecto del sistema, necesito un servicio `TenantContext` que resuelva el colegio activo en cada request, para usarlo como filtro global | P0 |
| HDU-S5-04 | Como arquitecto del sistema, necesito un `TenantAwareRepository` base que todas las queries lo apliquen automáticamente, para evitar fugas de datos entre colegios | P0 |

**Criterios de Aceptación:**

**HDU-S5-01 — Entidad School**
- Campos: `id`, `name`, `slug` (único), `rbd`, `active`, `plan`, `createdAt`
- Migración de base de datos
- CRUD de Schools en `SuperAdminDashboard` (solo `ROLE_SUPER_ADMIN`)

**HDU-S5-02 — FK school_id en User y Course**
- Migración que agrega `school_id` NOT NULL a `user` y `course`
- Para la instancia existente: asignar un colegio "default" a todos los registros actuales antes de aplicar NOT NULL
- Relaciones Doctrine: `User::ManyToOne school`, `Course::ManyToOne school`

**HDU-S5-03 — TenantContext**
- Servicio Symfony con scope `request`
- Método `setCurrentSchool(School $school)` llamado desde `LoginSuccessHandler`
- Método `getCurrentSchool(): School`
- Lanza excepción si se usa sin haber inicializado

**HDU-S5-04 — TenantAwareRepository**
- Clase abstracta base para todos los repositorios
- Agrega automáticamente `->andWhere('e.school = :school')->setParameter('school', $this->tenantContext->getCurrentSchool())` a todos los QueryBuilder
- Todos los repositorios existentes extienden esta clase

---

### Sprint 6 — Multitenencia: Controllers, Servicios y Seguridad

**Objetivo:** Actualizar toda la capa de negocio para usar el contexto de tenant.

| ID | Historia | Prioridad |
|---|---|---|
| HDU-S6-01 | Como arquitecto del sistema, necesito que todos los controllers filtren datos por colegio activo, para garantizar el aislamiento | P0 |
| HDU-S6-02 | Como arquitecto del sistema, necesito que `EnrollmentService` y `RecommendationService` operen solo dentro del colegio activo | P0 |
| HDU-S6-03 | Como arquitecto del sistema, necesito un `SchoolVoter` que valide que el recurso solicitado pertenece al colegio del usuario autenticado | P0 |
| HDU-S6-04 | Como administrador de colegio, quiero ver en el panel EasyAdmin solo los datos de mi colegio, sin poder acceder a datos de otros colegios | P0 |
| HDU-S6-05 | Como super administrador, quiero tener un panel separado para gestionar colegios, planes y usuarios globales | P1 |

**Criterios de Aceptación:**

**HDU-S6-01 — Controllers Tenant-Aware**
- `StudentController`, `TeacherController`, `GuardianController` inyectan `TenantContext`
- Todas las queries usan repositorios que heredan `TenantAwareRepository`
- No existe ninguna query global sin filtro de tenant (verificado con tests de integración)

**HDU-S6-02 — Servicios Tenant-Aware**
- `EnrollmentService::enrollStudent()` valida que `$student->getSchool() === $course->getSchool()`
- `RecommendationService` filtra cursos solo del colegio activo
- `GeminiChatbotService::buildCoursesContext()` filtra cursos del colegio activo

**HDU-S6-03 — SchoolVoter**
- Verifica en cada operación sobre entidades que `$entity->getSchool() === $currentSchool`
- Se aplica en: Course, User, Enrollment, Attendance
- Retorna `ACCESS_DENIED` si no coincide el colegio

**HDU-S6-04 — EasyAdmin por Colegio**
- `UserCrudController`, `CourseCrudController`, `EnrollmentCrudController` aplican filtro de tenant
- `ROLE_ADMIN` no ve registros de otros colegios
- `ROLE_SUPER_ADMIN` tiene acceso global

**HDU-S6-05 — SuperAdmin Dashboard**
- Ruta separada: `/super-admin`
- CRUD de `School` (crear, editar, activar/desactivar colegios)
- Vista global de métricas: total de colegios, usuarios por colegio, inscripciones totales

---

### Sprint 7 — Multitenencia: Onboarding de Colegios

**Objetivo:** Que un nuevo colegio pueda incorporarse a la plataforma de forma autónoma.

| ID | Historia | Prioridad |
|---|---|---|
| HDU-S7-01 | Como super administrador, quiero crear un colegio nuevo y asignarle un administrador inicial, para dar acceso al colegio a la plataforma | P0 |
| HDU-S7-02 | Como administrador de colegio nuevo, quiero recibir un email de bienvenida con mis credenciales y el link de acceso, para comenzar a usar el sistema | P1 |
| HDU-S7-03 | Como administrador de colegio, quiero que al crear mi colegio se generen automáticamente las categorías de cursos por defecto (Ciencias, Artes, etc.), para no empezar desde cero | P2 |
| HDU-S7-04 | Como administrador de colegio, quiero importar masivamente los estudiantes de mi colegio al inicio del proceso, para que todos puedan acceder sin configuración individual | P1 |

**Criterios de Aceptación:**

**HDU-S7-01 — Crear Colegio**
- Formulario en super admin: nombre, RBD, slug, plan, email del admin inicial
- Al guardar: crea `School` + crea usuario `ROLE_ADMIN` vinculado al colegio
- Genera contraseña aleatoria segura para el admin inicial

**HDU-S7-02 — Email de Bienvenida**
- Template: `templates/email/school_welcome.html.twig`
- Contenido: nombre del colegio, credenciales del admin, URL de acceso
- Enviado automáticamente al crear el colegio

**HDU-S7-03 — Categorías por Defecto**
- Al crear un colegio, clonar las categorías globales para el nuevo colegio (o marcarlas como compartidas)
- Decisión de arquitectura: mantener categorías como globales o hacerlas por colegio

**HDU-S7-04 — Importación Masiva de Estudiantes por Colegio**
- Reutiliza el mecanismo del Sprint 4 (HDU-S4-01)
- Vincula automáticamente los usuarios al `school_id` del admin que realiza la importación

---

## 14. Dependencias entre Sprints

```
Sprint 1 ──┐
Sprint 2 ──┤── Independientes entre sí (no dependen de multitenencia)
Sprint 3 ──┤
Sprint 4 ──┘

Sprint 5 ──> Sprint 6 ──> Sprint 7
(Fundación)  (Aplicación)  (Onboarding)

Sprint 4 (importación CSV) puede adelantarse a Sprint 7 si se construye con tenant en mente.
```

---

## 15. Dependencias Externas

| Servicio | Uso | Riesgo |
|---|---|---|
| Google Gemini API | Chatbot IA | Cuota limitada (manejo de 429 implementado) |
| Symfony Mailer (SMTP) | Emails transaccionales | Requiere config de proveedor SMTP — pendiente en producción |
| PHPSpreadsheet | Exportación Excel | Dependencia local, sin riesgo externo |

---

## 16. Estructura del Proyecto

```
electivoia/
├── assets/                    # Frontend (JS + CSS)
│   ├── app.js
│   └── theme/css|images|scss
├── config/
│   ├── packages/              # Config de bundles (security, doctrine, mailer)
│   └── routes/
├── migrations/                # 25 migraciones (Oct–Nov 2025)
├── public/build/              # Assets compilados (Webpack Encore)
├── src/
│   ├── Controller/
│   │   ├── Admin/             # EasyAdmin CRUDs
│   │   ├── StudentController.php
│   │   ├── TeacherController.php
│   │   ├── GuardianController.php
│   │   ├── EnrollmentController.php
│   │   ├── ChatbotController.php
│   │   ├── AdminReportController.php
│   │   ├── ExportController.php
│   │   └── SecurityController.php
│   ├── Entity/
│   │   ├── User.php
│   │   ├── Course.php
│   │   ├── Enrollment.php
│   │   ├── Attendance.php
│   │   ├── InterestProfile.php
│   │   └── CourseCategory.php
│   ├── Repository/            # 6 repositorios Doctrine
│   ├── Service/
│   │   ├── EnrollmentService.php
│   │   ├── RecommendationService.php
│   │   └── GeminiChatbotService.php
│   ├── Security/
│   │   └── LoginSuccessHandler.php
│   ├── Filter/
│   │   └── RoleFilter.php
│   └── DataFixtures/          # Seeds de prueba
├── templates/
│   ├── student/               # courses, enrollments, profile
│   ├── teacher/               # courses, attendance
│   ├── guardian/              # dashboard
│   └── admin_report/          # index (charts)
└── tests/
```

---

## 17. Convenciones de Desarrollo

- Contraseña inicial de usuario = RUT sin puntos ni guión (ej: `12345678K`)
- Los campos `currentEnrollment` se mantienen sincronizados en `EnrollmentService` (no via triggers)
- Los grados válidos son `3M` y `4M` (Tercer y Cuarto Medio)
- Las categorías de cursos tienen un campo `area` (10 chars) para agrupar en reportes
- Los roles se almacenan como array JSON en `user.roles` (patrón Symfony Security)

---

*Documento actualizado a partir del análisis del código fuente — 14 de marzo de 2026*
*Versión 2.0: reorganización completa + arquitectura de multitenencia + roadmap por sprints*
