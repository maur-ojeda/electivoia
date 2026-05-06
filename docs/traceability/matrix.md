# Matriz de Trazabilidad — ElectivoIA

Matriz de trazabilidad que vincula Historias de Usuario con Casos de Prueba, mostrando el estado de implementación y verificación de cada requerimiento.

---

## 🎒 Actor: Alumno

| HU | Épica | Descripción | Casos de Prueba | Estado |
|----|-------|-------------|----------------|--------|
| HU-A-01 | A-1: Descubrimiento | Onboarding de Perfil de Intereses | TC-A-02 | 🔲 Pendiente |
| HU-A-02 | A-1: Descubrimiento | Dashboard de Recomendaciones Explicadas | TC-A-03 | 🔲 Pendiente |
| HU-A-03 | A-1: Descubrimiento | Explorador de Electivos con Filtros | TC-A-04 | 🔲 Pendiente |
| HU-A-04 | A-2: Inscripción | Simulador de Inscripción | TC-A-05 | 🔲 Pendiente |
| HU-A-05 | A-2: Inscripción | Confirmación de Inscripción con Consentimiento | TC-A-06, TC-XA-04 | 🔲 Pendiente |
| HU-A-06 | A-2: Inscripción | Lista de Espera Inteligente | TC-A-07, TC-XA-06 | 🔲 Pendiente |
| HU-A-07 | A-3: Cursando | Portafolio de Competencias | TC-A-08 | 🔲 Pendiente |
| HU-A-08 | A-3: Cursando | Acceso a Material del Curso | TC-A-09 | 🔲 Pendiente |

### Requerimientos Funcionales — Alumno

| RF | Descripción | Verificado | Casos Asociados |
|----|-------------|-----------|-----------------|
| RF-AL-01 | Estados diferenciados pre/post inscripción | 🔲 | - |
| RF-AL-02 | Reglas de inscripción comunicadas en lenguaje claro | 🔲 | TC-A-05 |
| RF-AL-03 | Trazabilidad de acciones con timestamp | 🔲 | TC-XA-01 |
| RF-AL-04 | Vinculación alumno-apoderado con control de visibilidad | 🔲 | TC-XA-04 |
| RF-AL-05 | Preferencias de notificación configurables | 🔲 | - |

### Requerimientos No Funcionales — Alumno

| RNF | Descripción | Verificado | Criterio |
|-----|-------------|-----------|----------|
| RNF-AL-01 | Dashboard < 2s (p95) | 🔲 | Performance |
| RNF-AL-02 | Disponibilidad 99.9% en ventanas de inscripción | 🔲 | Disponibilidad |
| RNF-AL-03 | Flujo completo en < 5 pasos desde móvil | 🔲 | Usabilidad |
| RNF-AL-04 | WCAG 2.1 AA (usuarios desde 12 años) | 🔲 | Accesibilidad |
| RNF-AL-05 | Aislamiento de datos entre instituciones | 🔲 | TC-EC-02 |
| RNF-AL-06 | Optimistic locking en últimos cupos | 🔲 | TC-EC-01, TC-XA-06 |
| RNF-AL-07 | Retención de portafolio por 3 años | 🔲 | - |

---

## 👨‍🏫 Actor: Profesor

| HU | Épica | Descripción | Casos de Prueba | Estado |
|----|-------|-------------|----------------|--------|
| HU-PR-01 | PR-1: Gestión del Aula | Libro de Asistencia Digital | TC-PR-02 | 🔲 Pendiente |
| HU-PR-02 | PR-2: Comunicación | Muro de Curso | TC-PR-03, TC-PR-04 | 🔲 Pendiente |
| HU-PR-03 | PR-3: Alimentación IA | Feedback Vocacional del Alumno | TC-PR-05, TC-XA-05 | 🔲 Pendiente |

### Requerimientos Funcionales — Profesor

| RF | Descripción | Verificado | Casos Asociados |
|----|-------------|-----------|-----------------|
| RF-PR-01 | Solo ver electivos asignados en período activo | 🔲 | TC-PR-01 |
| RF-PR-02 | Exportar asistencia en Excel/PDF | 🔲 | TC-PR-02 |
| RF-PR-03 | Feedback vocacional integra con motor de IA | 🔲 | TC-XA-05 |

### Requerimientos No Funcionales — Profesor

| RNF | Descripción | Verificado | Criterio |
|-----|-------------|-----------|----------|
| RNF-PR-01 | Asistencia < 3s en 4G | 🔲 | Performance |
| RNF-PR-02 | Funcionamiento offline con sync | 🔲 | Disponibilidad |
| RNF-PR-03 | Feedback vocacional privado entre profesor, admin e IA | 🔲 | Privacidad |

---

## 🏫 Actor: Administrativo

| HU | Épica | Descripción | Casos de Prueba | Estado |
|----|-------|-------------|----------------|--------|
| HU-AD-01 | AD-1: Configuración | Configuración de Reglas de Inscripción | TC-AD-15 | 🔲 Pendiente |
| HU-AD-02 | AD-2: Inteligencia | Reportes de Demanda Predictiva | TC-AD-13, TC-AD-14 | ✅ Implementado (parcial) |
| HU-AD-03 | AD-2: Inteligencia | Auditoría de Procesos de Inscripción | TC-AD-16 | 🔲 Pendiente |
| HU-AD-04 | AD-3: Gestión | Gestión de Electivos y Cupos | TC-AD-04 a TC-AD-12, TC-XA-01, TC-XA-02 | ✅ Implementado |

### Requerimientos Funcionales — Administrativo

| RF | Descripción | Verificado | Casos Asociados |
|----|-------------|-----------|-----------------|
| RF-AD-01 | Inscripciones manuales con registro en auditoría | 🔲 | TC-AD-08, TC-XA-02 |
| RF-AD-02 | Notificaciones automáticas de alumnos sin inscribir | 🔲 | - |
| RF-AD-03 | Datos limitados a institución propia | 🔲 | TC-EC-02 |

### Requerimientos No Funcionales — Administrativo

| RNF | Descripción | Verificado | Criterio |
|-----|-------------|-----------|----------|
| RNF-AD-01 | Tareas frecuentes en ≤ 3 clics | 🔲 | Usabilidad |
| RNF-AD-02 | Exportación < 10s para 2000 registros | 🔲 | Performance |
| RNF-AD-03 | Reglas aplicadas en todos los endpoints | 🔲 | Integridad |

---

## 👨‍💼 Actor: SuperAdmin

| HU | Épica | Descripción | Casos de Prueba | Estado |
|----|-------|-------------|----------------|--------|
| HU-SA-01 | SA-1: Visibilidad | Dashboard Global de Operaciones | TC-SA-01 | 🔲 No implementado en MVP |
| HU-SA-02 | SA-2: Instituciones | Provisioning Automático de Institución | TC-SA-02 | 🔲 No implementado en MVP |
| HU-SA-03 | SA-2: Instituciones | Gestión de Suscripciones y Acceso | TC-SA-03 | 🔲 No implementado en MVP |

### Requerimientos Funcionales — SuperAdmin

| RF | Descripción | Verificado | Casos Asociados |
|----|-------------|-----------|-----------------|
| RF-SA-01 | Aislamiento total de datos entre instituciones | 🔲 | TC-EC-02 |
| RF-SA-02 | Log de auditoría inmutable (2 años) | 🔲 | - |
| RF-SA-03 | Múltiples SuperAdmins con roles diferenciados | 🔲 | - |

### Requerimientos No Funcionales — SuperAdmin

| RNF | Descripción | Verificado | Criterio |
|-----|-------------|-----------|----------|
| RNF-SA-01 | Autenticación 2FA obligatoria | 🔲 | Seguridad |
| RNF-SA-02 | Log inmutable con retención ≥ 2 años | 🔲 | Trazabilidad |
| RNF-SA-03 | Panel independiente del estado de instituciones | 🔲 | Disponibilidad |

---

## 👨‍👩‍👧 Actor: Apoderado

| HU | Épica | Descripción | Casos de Prueba | Estado |
|----|-------|-------------|----------------|--------|
| HU-AP-01 | AP-1: Visibilidad | Panel de Seguimiento del Pupilo | TC-AP-02 | 🔲 Pendiente |
| HU-AP-02 | AP-2: Participación | Firma Digital de Consentimiento | TC-AP-03, TC-XA-04 | 🔲 Pendiente |
| HU-AP-03 | AP-2: Participación | Notificación de Alumno sin Inscribir | TC-AP-04 | 🔲 Pendiente |

### Requerimientos Funcionales — Apoderado

| RF | Descripción | Verificado | Casos Asociados |
|----|-------------|-----------|-----------------|
| RF-AP-01 | Vinculación múltiple apoderado-alumno | 🔲 | - |
| RF-AP-02 | Registro inmutable de consentimientos | 🔲 | TC-AP-03 |
| RF-AP-03 | Acceso por magic link (sin contraseña) | 🔲 | - |

### Requerimientos No Funcionales — Apoderado

| RNF | Descripción | Verificado | Criterio |
|-----|-------------|-----------|----------|
| RNF-AP-01 | Firma de consentimiento en < 3 pasos | 🔲 | Usabilidad |
| RNF-AP-02 | Funcional en móvil sin app | 🔲 | Accesibilidad |
| RNF-AP-03 | Visibilidad limitada a pupilos vinculados | 🔲 | Privacidad |

---

## 🤖 Actor: Motor de IA

| HU | Épica | Descripción | Casos de Prueba | Estado |
|----|-------|-------------|----------------|--------|
| HU-IA-01 | IA-1: Generación | Recomendación por Perfil (Content-Based) | TC-IA-01, TC-XA-03 | 🔲 Pendiente |
| HU-IA-02 | IA-1: Generación | Recomendación por Similitud (Colaborativo) | TC-IA-02 | 🔲 Pendiente |
| HU-IA-03 | IA-2: Aprendizaje | Aprendizaje de Señales | TC-IA-03, TC-XA-05 | 🔲 Pendiente |
| HU-IA-04 | IA-2: Aprendizaje | Predicción de Demanda | TC-IA-04 | 🔲 Pendiente |
| HU-IA-05 | IA-3: Gobernanza | Panel de Supervisión del Motor | TC-IA-05 | 🔲 Pendiente |
| HU-IA-06 | IA-3: Gobernanza | Detección y Reporte de Sesgos | TC-IA-06 | 🔲 Pendiente |

### Requerimientos Funcionales — Motor de IA

| RF | Descripción | Verificado | Casos Asociados |
|----|-------------|-----------|-----------------|
| RF-IA-01 | Aislamiento completo de datos entre instituciones | 🔲 | TC-EC-02 |
| RF-IA-02 | Explicabilidad de cualquier recomendación individual | 🔲 | TC-IA-01 |
| RF-IA-03 | Modo cold start para instituciones sin historial | 🔲 | TC-IA-01 |
| RF-IA-04 | Auditabilidad de señales utilizadas | 🔲 | TC-IA-03 |

### Requerimientos No Funcionales — Motor de IA

| RNF | Descripción | Verificado | Criterio |
|-----|-------------|-----------|----------|
| RNF-IA-01 | Recomendaciones desde caché < 500ms | 🔲 | Performance |
| RNF-IA-02 | Minimización de datos (sin datos sensibles) | 🔲 | Privacidad |
| RNF-IA-03 | Solo modelos explicables (no caja negra) | 🔲 | Explicabilidad |
| RNF-IA-04 | Re-entrenamiento automático por período | 🔲 | Actualización |
| RNF-IA-05 | No personalización agresiva para menores | 🔲 | Protección |

---

## Pruebas Cross-Actor

| ID | Descripción | Actores | HU Asociadas | Estado |
|----|-------------|---------|-------------|--------|
| TC-XA-01 | Admin crea curso → Alumno lo ve → Se inscribe | AD + A | HU-AD-04, HU-A-03, HU-A-05 | 🔲 |
| TC-XA-02 | Admin inscribe masivamente → Profesor ve alumnos | AD + PR | HU-AD-04, HU-PR-01 | 🔲 |
| TC-XA-03 | Alumno completa perfil → IA genera recomendaciones | A + IA | HU-A-01, HU-IA-01 | 🔲 |
| TC-XA-04 | Alumno solicita inscripción → Apoderado firma → Confirmación | A + AP | HU-A-05, HU-AP-02 | 🔲 |
| TC-XA-05 | Profesor da feedback → IA ajusta recomendaciones | PR + IA | HU-PR-03, HU-IA-03 | 🔲 |
| TC-XA-06 | Concurrencia: último cupo con 2 alumnos | A + A | HU-A-05, HU-A-06 | 🔲 |

---

## Pruebas de Edge Cases y Seguridad

| ID | Categoría | Descripción | Estado |
|----|-----------|-------------|--------|
| TC-EC-01 | Concurrencia | Optimistic locking en inscripciones | 🔲 |
| TC-EC-02 | Seguridad | Aislamiento multi-tenant | 🔲 |
| TC-EC-03 | Validación | Login con RUT formato inválido | 🔲 |
| TC-EC-04 | Seguridad | Login con RUT válido pero inexistente | 🔲 |
| TC-EC-05 | Autenticación | Acceso a /admin sin autenticación | 🔲 |
| TC-EC-06 | Autorización | ROLE_STUDENT intenta acceder a /admin | 🔲 |
| TC-EC-07 | Integridad | Inscripción duplicada | 🔲 |
| TC-EC-08 | Integridad | Inscribir en curso sin cupos | 🔲 |
| TC-EC-09 | Performance | N+1 en /admin/user (interest_profile) | 🔲 |
| TC-EC-10 | Performance | N+1 en /admin/reports (enrollment COUNT) | 🔲 |
| TC-EC-11 | Assets | Asset 404: imagen de curso | 🔲 |

---

## Resumen de Cobertura

| Métrica | Cantidad |
|---------|----------|
| Actores | 6 |
| Épicas | 14 |
| Historias de Usuario | 23 |
| Requerimientos Funcionales | 17 |
| Requerimientos No Funcionales | 18 |
| Casos de Prueba (Fase 1 - Smoke) | 11 (✅ 11/11) |
| Casos de Prueba (Fase 2 - Funcional) | 45 (✅ 12, 🔲 6 pendientes AD, ⏭️ 27 no implementado) |
| Casos de Prueba (Fase 3 - Cross-Actor) | 6 (🔲 6) |
| Casos de Prueba (Fase 4 - Edge Cases) | 11 (🔲 11) |
| **Total Casos de Prueba** | **73** |
| **Cobertura de HUs con tests** | **23/23 (100%)** |