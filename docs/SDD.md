# ElectivoIA — Spec-Driven Development (SDD)

Documento índice que vincula toda la documentación de especificaciones del proyecto ElectivoIA.

---

## ¿Qué es Spec-Driven Development?

Spec-Driven Development (SDD) es una disciplina donde **las especificaciones guían el desarrollo**, no al revés. Cada feature se define a través de:

1. **Actores** — Quiénes interactúan con el sistema y qué necesitan
2. **Épicas** — Agrupaciones de funcionalidad por Actor
3. **Historias de Usuario** — Requerimientos en lenguaje de negocio con criterios de aceptación verificables
4. **Requerimientos Funcionales y No Funcionales** — Reglas de negocio y atributos de calidad
5. **Casos de Prueba** — Verificación explícita de cada HU
6. **Decisiones de Arquitectura** — Justificación técnica de cada elección importante
7. **Diagramas** — Visualización de flujos y arquitectura

Este enfoque garantiza trazabilidad completa: cada línea de código se vincula a un requerimiento, cada requerimiento a un caso de prueba, y cada caso de prueba a un resultado observable.

---

## Cómo se aplicó SDD en ElectivoIA

ElectivoIA nació como un proyecto documentado desde el primer día. El proceso fue:

1. **Definición de Actores**: Se identificaron 6 actores con necesidades distintas (Alumno, Profesor, Administrativo, SuperAdmin, Apoderado, Motor de IA)
2. **Desglose por Épicas**: Cada actor tiene épicas que agrupan sus historias de usuario
3. **Historias de Usuario con Criterios de Aceptación**: Cada HU incluye criterios verificables, no aspiracionales
4. **Requerimientos No Funcionales**: Performance, seguridad, accesibilidad y privacidad están explícitos por actor
5. **Plan de Pruebas**: 73 casos de prueba organizados en 4 fases (Smoke, Funcional, Cross-Actor, Edge Cases)
6. **Bug Tracking**: Los bugs se rastrean con vínculo directo a los tests que los descubrieron
7. **ADRs**: Las decisiones técnicas se documentan con contexto, decisión y consecuencias

---

## Actores del Sistema

| Actor | Rol | Épicas |
|-------|-----|--------|
| 🎒 Alumno | Core del producto — explora, se inscribe, cursa | A-1, A-2, A-3 |
| 👨‍🏫 Profesor | Gestiona aula, publica contenido, alimenta IA | PR-1, PR-2, PR-3 |
| 🏫 Administrativo | Gestiona oferta, reglas, reportes | AD-1, AD-2, AD-3 |
| 👨‍💼 SuperAdmin | Visibilidad global, gestión de instituciones | SA-1, SA-2 |
| 👨‍👩‍👧 Apoderado | Seguimiento y consentimiento | AP-1, AP-2 |
| 🤖 Motor de IA | Genera recomendaciones, aprende, detecta sesgos | IA-1, IA-2, IA-3 |

---

## Épicas e Historias de Usuario

| Épica | Actor | Historias de Usuario |
|-------|-------|---------------------|
| A-1: Descubrimiento y Recomendación | 🎒 Alumno | HU-A-01, HU-A-02, HU-A-03 |
| A-2: Inscripción | 🎒 Alumno | HU-A-04, HU-A-05, HU-A-06 |
| A-3: Experiencia Cursando | 🎒 Alumno | HU-A-07, HU-A-08 |
| PR-1: Gestión del Aula | 👨‍🏫 Profesor | HU-PR-01 |
| PR-2: Comunicación con el Curso | 👨‍🏫 Profesor | HU-PR-02 |
| PR-3: Alimentación del Motor de IA | 👨‍🏫 Profesor | HU-PR-03 |
| AD-1: Configuración Institucional | 🏫 Administrativo | HU-AD-01 |
| AD-2: Inteligencia Operativa | 🏫 Administrativo | HU-AD-02, HU-AD-03 |
| AD-3: Gestión de la Oferta Académica | 🏫 Administrativo | HU-AD-04 |
| SA-1: Visibilidad del Negocio | 👨‍💼 SuperAdmin | HU-SA-01 |
| SA-2: Gestión de Instituciones | 👨‍💼 SuperAdmin | HU-SA-02, HU-SA-03 |
| AP-1: Visibilidad y Seguimiento | 👨‍👩‍👧 Apoderado | HU-AP-01 |
| AP-2: Participación en el Proceso | 👨‍👩‍👧 Apoderado | HU-AP-02, HU-AP-03 |
| IA-1: Generación de Recomendaciones | 🤖 Motor de IA | HU-IA-01, HU-IA-02 |
| IA-2: Aprendizaje de Señales | 🤖 Motor de IA | HU-IA-03, HU-IA-04 |
| IA-3: Gobernanza y Supervisión | 🤖 Motor de IA | HU-IA-05, HU-IA-06 |

**Total**: 14 épicas, 23 historias de usuario

---

## Artefactos de Documentación

### Diagramas

| Documento | Descripción |
|-----------|-------------|
| [Flujo de Inscripción](diagrams/enrollment-flow.md) | Secuencia completa desde selección hasta confirmación |
| [Motor de Recomendación IA](diagrams/ia-recommendation-engine.md) | Flujo interno del motor de IA |
| [Arquitectura General](diagrams/architecture-overview.md) | Capas del sistema e infraestructura |
| [Flujo de Autenticación](diagrams/auth-flow.md) | Login, roles, autorización y logout |

### Decisiones de Arquitectura (ADRs)

| Documento | Decisión |
|-----------|---------|
| [ADR-001](adr/ADR-001-why-symfony.md) | Symfony 7.3 sobre Laravel |
| [ADR-002](adr/ADR-002-why-postgresql.md) | PostgreSQL 16 sobre MySQL |
| [ADR-003](adr/ADR-003-why-multitenancy.md) | Base compartida con institution_id |

### Trazabilidad

| Documento | Descripción |
|-----------|-------------|
| [Matriz de Trazabilidad](traceability/matrix.md) | HU → Casos de prueba → Estado |

---

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
| IA Motor | Híbrido (Tags + Colaborativo) | Interno |
| Exportación | Dompdf + PhpSpreadsheet | 3.1.5 |
| Contenedores | Docker | - |
| Testing | PHPUnit | Configurado |

---

## Métricas de Cobertura

| Métrica | Valor |
|---------|-------|
| Actores | 6 |
| Épicas | 14 |
| Historias de Usuario | 23 |
| Requerimientos Funcionales | 17 |
| Requerimientos No Funcionales | 18 |
| Casos de Prueba totales | 73 |
| Casos de Prueba Fase 1 (Smoke) | 11 ✅ |
| Casos de Prueba Fase 2 (Funcional) | 45 (12 ✅, 6 🔲 AD, 27 ⏭️) |
| Casos de Prueba Fase 3 (Cross-Actor) | 6 🔲 |
| Casos de Prueba Fase 4 (Edge Cases) | 11 🔲 |
| ADRs | 3 |
| Diagramas | 4 |
| Cobertura de HU con tests | 100% (23/23) |
| Bugs conocidos | 3 (N+1 queries, asset 404, capacity badge) |

---

## Estado del Proyecto

**Fase actual**: MVP en producción — Fase 2 de testing en progreso (Administrativo)

**Próximos pasos**:
1. Fix bugs pendientes (TC-AD-10: capacity badge)
2. Completar TC-AD-11 a TC-AD-18
3. Crear credenciales de profesor, alumno y apoderado
4. Ejecutar Fase 2 completa (PR, A, AP, IA)
5. Ejecutar Fase 3 (cross-actor)
6. Ejecutar Fase 4 (edge cases y seguridad)
7. Fix bugs conocidos: N+1 queries, asset 404