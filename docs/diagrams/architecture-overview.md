# Arquitectura General — ElectivoIA

Diagrama de arquitectura del sistema mostrando las capas principales y sus interacciones.

```mermaid
graph TB
    subgraph CLIENT["🖥️ Capa de Presentación"]
        direction TB
        TWIG["Twig Templates<br/>(SSR)"]
        STIMULUS["Stimulus.js<br/>(Interactividad)"]
        TURBO["Turbo Drive<br/>(Navegación SPA)"]
        TAILWIND["Tailwind CSS 4<br/>(Diseño responsive)"]
    end

    subgraph ADMIN["🔧 Panel Administrativo"]
        EA["EasyAdmin 4.26<br/>(CRUD Admin)"]
        BULK["AdminBulkEnroll<br/>(Inscripción masiva)"]
        REPORTS["ReportsController<br/>(Reportes de demanda)"]
        EXPORT["ExportService<br/>(PDF/Excel)"]
    end

    subgraph BACKEND["⚙️ Capa de Aplicación — Symfony 7.3"]
        direction TB
        CONTROLLERS["Controllers<br/>(HTTP Handlers)"]
        SERVICES["Services<br/>(Lógica de negocio)"]
        REPOSITORIES["Repositories<br/>(Consultas Doctrine)"]
        SECURITY["Security Bundle<br/>(Auth, Roles, Voter)"]
        TENANT["TenantFilter<br/>(Multi-tenant scope)"]
    end

    subgraph DOMAIN["📦 Capa de Dominio"]
        direction TB
        USER["User<br/>(id, rut, roles,<br/>institution_id)"]
        COURSE["Course<br/>(id, name, max_capacity,<br/>institution_id, competency_tags)"]
        ENROLLMENT["Enrollment<br/>(id, student, course,<br/>status, version)"]
        INTEREST["InterestProfile<br/>(tags, scores)"]
        INSTITUTION["Institution<br/>(id, name, subdomain,<br/>logo, settings_json)"]
    end

    subgraph INFRA["🗄️ Capa de Infraestructura"]
        direction TB
        PG["PostgreSQL 16<br/>(Datos + JSONB)"]
        REDIS["Redis<br/>(Caché de recomendaciones<br/>y cupos en tiempo real)"]
        DOCKER["Docker<br/>(Contenerización)"]
    end

    subgraph EXT["🌐 Servicios Externos"]
        IA_API["Motor de Recomendación<br/>(Híbrido: Tags + Colaborativo)"]
        EMAIL["Email Service<br/>(Notificaciones)"]
    end

    TWIG --> CONTROLLERS
    STIMULUS --> CONTROLLERS
    TURBO --> CONTROLLERS
    EA --> CONTROLLERS
    BULK --> CONTROLLERS
    REPORTS --> CONTROLLERS

    CONTROLLERS --> SERVICES
    SERVICES --> REPOSITORIES
    SERVICES --> SECURITY
    SERVICES --> TENANT
    EXPORT --> SERVICES

    REPOSITORIES --> PG
    SERVICES --> REDIS
    SERVICES --> IA_API
    SERVICES --> EMAIL

    REPOSITORIES --> USER
    REPOSITORIES --> COURSE
    REPOSITORIES --> ENROLLMENT
    REPOSITORIES --> INTEREST
    REPOSITORIES --> INSTITUTION

    TENANT -.->|"Filtro automático<br/>por institution_id"| REPOSITORIES

    style CLIENT fill:#e3f2fd,stroke:#1565c0
    style ADMIN fill:#f3e5f5,stroke:#7b1fa2
    style BACKEND fill:#fff3e0,stroke:#e65100
    style DOMAIN fill:#e8f5e9,stroke:#2e7d32
    style INFRA fill:#fce4ec,stroke:#c62828
    style EXT fill:#f5f5f5,stroke:#616161
```

## Stack Tecnológico

| Componente | Tecnología | Versión |
|-----------|-----------|---------|
| Backend | PHP + Symfony | 8.2 + 7.3 |
| Base de datos | PostgreSQL | 16 |
| ORM | Doctrine | 2.x |
| Panel Admin | EasyAdmin | 4.26 |
| Frontend CSS | Tailwind CSS | 4 |
| Frontend JS | Stimulus + Turbo | ES modules |
| Caché | Redis | 7.x |
| Contenedores | Docker | - |
| IA Motor | Híbrido (Tags + Colaborativo) | Interno |
| Exportación | Dompdf + PhpSpreadsheet | 3.1.5 / x |

## Modelo de Multi-tenencia

El sistema utiliza **base de datos compartida** con `institution_id` como columna discriminadora. El `TenantFilter` de Doctrine aplica automáticamente el filtro en cada consulta, garantizando aislamiento de datos entre instituciones sin necesidad de schemas separados.

```mermaid
graph LR
    subgraph TENANTS["Multi-tenencia — Shared Database"]
        INST_A["Institución A<br/>(institutions_id = 1)"]
        INST_B["Institución B<br/>(institutions_id = 2)"]
        DB[("PostgreSQL<br/>Base compartida")]
    end

    INST_A -->|"TenantFilter<br/>institution_id = 1"| DB
    INST_B -->|"TenantFilter<br/>institution_id = 2"| DB

    DB --> INSTA_DATA["Datos独 isolation<br/>por institution_id"]
    DB --> INSTB_DATA["Datos独 isolation<br/>por institution_id"]

    style TENANTS fill:#e8f5e9,stroke:#2e7d32
```

## Referencias a ADRs

- [ADR-001: Por qué Symfony](../adr/ADR-001-why-symfony.md)
- [ADR-002: Por qué PostgreSQL](../adr/ADR-002-why-postgresql.md)
- [ADR-003: Multi-tenencia con base compartida](../adr/ADR-003-why-multitenancy.md)