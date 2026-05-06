# ADR-001: Por qué Symfony sobre Laravel para ElectivoIA

| Campo | Valor |
|-------|-------|
| **Estado** | Aceptado |
| **Fecha** | 2026-01 |
| **Decisores** | Arquitecto líder |
| **Contexto** | Inicio del proyecto |

## Contexto

ElectivoIA es una plataforma SaaS multitenente para gestión de electivos académicos con un motor de recomendaciones IA. Necesitamos un framework PHP robusto que soporte:

- Reglas de negocio complejas (inscripciones con consentimiento, listas de espera, simuladores)
- Multi-tenencia con aislamiento de datos
- Un ORM flexible para modelos de datos con relaciones complejas (usuarios con múltiples roles, inscripciones con estados, perfiles de interés con tags)
- Panel administrativo potente para gestionar cursos, alumnos, profesores e inscripciones
- Integración con servicios de IA y caché
- API potencial para futura app móvil

## Decisión

Elegimos **Symfony 7.3** como framework backend.

## Razones

1. **Doctrine ORM**: El modelo de datos de ElectivoIA es complejo (multi-tenencia con `TenantFilter`, entidades con JSONB para tags, relaciones many-to-many con estados). Doctrine ofrece flexibilidad superior para queries complejas, lifecycle callbacks, y filtros a nivel de repositorio — esenciales para el aislamiento automático por `institution_id`.

2. **Dependency Injection Container**: El motor de IA tiene múltiples estrategias (content-based, colaborativo) que se benefician de un contenedor de DI maduro con auto-wiring. Symfony lo tiene como ciudadano de primera clase, no como add-on.

3. **EasyAdmin**: Para el panel administrativo (CRUD de usuarios, cursos, inscripciones, reportes), EasyAdmin 4 ofrece customización profunda sin牺牲 simplicidad. El 80% de las operaciones del Administrativo se resuelven con configuración, no con código.

4. **Security Component**: Los 5 roles del sistema (SuperAdmin, Administrativo, Profesor, Alumno, Apoderado) con Voters personalizados y autenticación por RUT encuentran en el Security Bundle una solución madura y extensible.

5. **Flex y maker**: Symfony Flex permite instalar solo lo necesario. El proyecto usa `symfony/framework-bundle` + los bundles específicos sin overhead de funcionalidad no utilizada.

6. **Convención sobre configuración**: Symfony 7 prioriza convenios claros que reducen decisiones triviales. En un proyecto con esta complejidad de dominio, menos decisiones estructurales = más foco en lógica de negocio.

7. **Ecosistema maduro**: Bundles como `symfony/mercure` (notificaciones en tiempo real para listas de espera), `doctrine/cache` (Redis para recomendaciones IA), y la integración nativa con PHPUnit aportan estabilidad sin reinventar la rueda.

## Consecuencias

### Positivas
- Separación de concerns clara (Controllers → Services → Repositories → Entities)
- Multi-tenencia implementada elegantemente con Doctrine Filters
- EasyAdmin reduce drásticamente el tiempo de desarrollo del panel admin
- Sistema de seguridad robusto para los 5 roles
- Escalabilidad probada en proyectos SaaS de mayor envergadura

### Negativas
- Curva de aprendizaje más pronunciada que Laravel para desarrolladores juniors
- Más boilerplate inicial comparado con Laravel (aunque Flex mitiga esto)
- Comunidad más chica que Laravel en Argentina, aunque más orientada a proyectos enterprise

### Compromisos
- Aceptamos la complejidad inicial a cambio de mantenibilidad a largo plazo
- Priorizamos solidez del dominio sobre velocidad de prototipado
- EasyAdmin como trade-off: acelera el admin pero requiere conocimiento de sus hooks para customización profunda