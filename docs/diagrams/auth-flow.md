# Flujo de Autenticación y Autorización — Diagrama de Secuencia

Diagrama del flujo de login, generación de sesión, verificación de roles y acceso a rutas según el actor del sistema.

```mermaid
sequenceDiagram
    participant USR as Usuario<br/>(Alumno/Profesor/<br/>Apoderado/Admin/SuperAdmin)
    participant AUTH as Security<br/>Controller
    participant DB as PostgreSQL<br/>(tabla User)
    participant SESSION as Sesión<br/>Symfony
    participant VOTER as Security Voter<br/>(RoleVoter)
    participant ROUTE as Controller<br/>de Destino

    USR->>AUTH: POST /login<br/>(RUT + contraseña)
    AUTH->>DB: Buscar usuario por RUT
    DB-->>AUTH: Usuario encontrado<br/>con roles e institution_id

    alt RUT no encontrado
        AUTH-->>USR: "Credenciales inválidas"<br/>(no revela si el RUT existe)
    else Contraseña incorrecta
        AUTH-->>USR: "Credenciales inválidas"
    else Autenticación exitosa
        AUTH->>SESSION: Crear sesión con roles + institution_id
        SESSION-->>AUTH: Sesión iniciada

        alt ROLE_SUPER_ADMIN
            AUTH-->>USR: Redirect → /super-admin/dashboard
        else ROLE_ADMIN
            AUTH-->>USR: Redirect → /admin
        else ROLE_TEACHER
            AUTH-->>USR: Redirect → /teacher/dashboard
        else ROLE_STUDENT
            AUTH-->>USR: Redirect → /student/dashboard
        else ROLE_GUARDIAN
            AUTH-->>USR: Redirect → /guardian/dashboard
        end
    end

    Note over USR,ROUTE: Verificación de autorización en cada request

    USR->>ROUTE: GET /admin/course
    ROUTE->>VOTER: ¿El usuario tiene ROLE_ADMIN?
    VOTER->>SESSION: Verificar roles del usuario
    SESSION-->>VOTER: Roles = [ROLE_ADMIN]

    alt Autorización concedida
        VOTER-->>ROUTE: ACCESS_GRANTED
        ROUTE-->>USR: 200 OK + contenido
    else Autorización denegada
        VOTER-->>ROUTE: ACCESS_DENIED
        ROUTE-->>USR: 403 Forbidden
    end

    Note over USR,AUTH: Logout

    USR->>AUTH: GET /logout
    AUTH->>SESSION: Invalidar sesión
    AUTH-->>USR: Redirect → /login
```

## Roles del Sistema

| Rol | Código | Dashboard | Permisos |
|-----|--------|-----------|----------|
| SuperAdmin | `ROLE_SUPER_ADMIN` | `/super-admin/dashboard` | Gestión global, instituciones, suscripciones |
| Administrativo | `ROLE_ADMIN` | `/admin` | Gestión de cursos, inscripciones, usuarios, reportes |
| Profesor | `ROLE_TEACHER` | `/teacher/dashboard` | Asistencia, muro de curso, feedback vocacional |
| Alumno | `ROLE_STUDENT` | `/student/dashboard` | Explorador, recomendaciones IA, inscripción |
| Apoderado | `ROLE_GUARDIAN` | `/guardian/dashboard` | Seguimiento pupilo, firma de consentimiento |

## Reglas de Seguridad

- Un usuario puede tener múltiples roles (ej: `ROLE_TEACHER` + `ROLE_ADMIN` + `ROLE_GUARDIAN`)
- Autenticación por RUT chileno (formato: `12345678-9`)
- Contraseña inicial: primeros 6 dígitos del RUT (sin guión ni DV)
- `TenantFilter` aplica automáticamente `institution_id` en todas las consultas
- Los `ROLE_STUDENT` no pueden acceder a `/admin` (403 Forbidden)
- Sesiones no autenticadas son redirigidas a `/login`
- El logout invalida la sesión completamente y redirige a `/login`

## Rutas Protegidas

| Ruta | Roles Permitidos |
|------|-----------------|
| `/admin/*` | `ROLE_ADMIN`, `ROLE_SUPER_ADMIN` |
| `/super-admin/*` | `ROLE_SUPER_ADMIN` |
| `/teacher/*` | `ROLE_TEACHER` |
| `/student/*` | `ROLE_STUDENT` |
| `/guardian/*` | `ROLE_GUARDIAN` |
| `/login`, `/logout` | Público |