# Motor de Recomendación IA — Diagrama de Flujo

Diagrama de flujo del proceso interno del Motor de IA, desde la entrada de datos hasta la generación de recomendaciones con explicabilidad y detección de sesgos.

```mermaid
flowchart TD
    subgraph INPUT["📥 Datos de Entrada"]
        PROFILE["Perfil de Intereses<br/>del Alumno<br/><i>(HU-A-01: Onboarding)</i>"]
        HISTORY["Historial de<br/>Inscripciones<br/><i>(períodos anteriores)</i>"]
        SIGNALS["Señales de<br/>Comportamiento<br/><i>(HU-IA-03: clicks, favoritos)</i>"]
        FEEDBACK["Feedback Vocacional<br/>del Profesor<br/><i>(HU-PR-03: tags de aptitudes)</i>"]
        COURSES["Oferta de Electivos<br/><i>(tags, cupos, horarios)</i>"]
    end

    subgraph ENGINE["⚙️ Motor de IA"]
        CB["Filtrado por Contenido<br/><i>Match de tags del perfil<br/>vs tags del electivo</i>"]
        CF["Filtrado Colaborativo<br/><i>Alumnos similares<br/>eligieron otros electivos</i>"]
        MERGE["Fusión Híbrida<br/><i>Peso: contenido 60%<br/>colaborativo 40%</i>"]
        BIAS_CHECK{"¿Sesgos detectados?<br/><i>(HU-IA-06)</i>"}
        EXPLAIN["Capa de Explicabilidad<br/><i>Genera razón en<br/>lenguaje natural</i>"]
    end

    subgraph OUTPUT["📤 Salida"]
        REC["Recomendaciones<br/>ordenadas por score"]
        EXPLAINER["Razón principal +<br/>razón secundaria<br/>en español"]
        DEMAND["Predicción de demanda<br/><i>(HU-IA-04: para Administrativo)</i>"]
        BIAS_REPORT["Reporte de Sesgos<br/><i>( accesible por Admin<br/>y SuperAdmin)</i>"]
    end

    PROFILE --> CB
    HISTORY --> CF
    SIGNALS --> CF
    FEEDBACK --> CF
    COURSES --> CB
    COURSES --> CF

    CB --> MERGE
    CF --> MERGE

    MERGE --> BIAS_CHECK

    BIAS_CHECK -- "Sin sesgos" --> EXPLAIN
    BIAS_CHECK -- "Sesgo detectado" --> BIAS_REPORT
    BIAS_CHECK -- "Sin sesgos" --> REC

    EXPLAIN --> REC
    EXPLAIN --> EXPLAINER

    MERGE --> DEMAND

    REC --> DASHBOARD["🎓 Dashboard del Alumno<br/><i>(HU-A-02)</i>"]
    EXPLAINER --> DASHBOARD
    DEMAND --> ADMIN["🏫 Panel del Administrativo<br/><i>(HU-AD-02)</i>"]
    BIAS_REPORT --> SUPERADMIN["👨‍💼 SuperAdmin<br/><i>(Revisión periódica)</i>"]

    style INPUT fill:#e8f4f8,stroke:#2196f3
    style ENGINE fill:#fff3e0,stroke:#ff9800
    style OUTPUT fill:#e8f5e9,stroke:#4caf50
    style BIAS_CHECK fill:#ffebee,stroke:#f44336
```

## Detalle de las Señales

| Tipo de Señal | Acción | Peso |
|--------------|--------|------|
| Positiva fuerte | Inscripción confirmada | +3 |
| Positiva media | Marcar como favorito | +2 |
| Positiva leve | Ver detalles > 1 vez | +1 |
| Negativa fuerte | Cancelar inscripción | -3 |
| Negativa media | Marcar "No me interesa" | -2 |
| Negativa leve | Abandonar lista de espera | -1 |
| Especial | Feedback del Profesor (HU-PR-03) | +4 (peso diferenciado) |

## Modo Cold Start

Cuando una institución tiene menos de 50 alumnos con historial, el filtrado colaborativo se desactiva y el motor opera solo con filtrado por contenido, basándose exclusivamente en los tags del perfil del alumno y los tags de los electivos.

```mermaid
flowchart LR
    subgraph COLD["🥶 Modo Cold Start"]
        C_PROFILE["Perfil del Alumno<br/>(tags del onboarding)"]
        C_COURSES["Tags de Electivos"]
        C_MATCH["Match por Tags<br/>(100% contenido)"]
        C_RECS["Recomendaciones<br/>con menor certeza"]
    end

    C_PROFILE --> C_MATCH
    C_COURSES --> C_MATCH
    C_MATCH --> C_RECS

    style COLD fill:#fce4ec,stroke:#c62828
```

## Referencias

| Código | Historia de Usuario |
|--------|-------------------|
| HU-A-01 | Onboarding de Perfil de Intereses |
| HU-A-02 | Dashboard de Recomendaciones Explicadas |
| HU-IA-01 | Recomendación por Perfil (Content-Based) |
| HU-IA-02 | Recomendación por Similitud (Colaborativo) |
| HU-IA-03 | Aprendizaje de Señales |
| HU-IA-04 | Predicción de Demanda |
| HU-IA-05 | Panel de Supervisión del Motor |
| HU-IA-06 | Detección y Reporte de Sesgos |
| HU-PR-03 | Feedback Vocacional del Alumno |
| HU-AD-02 | Reportes de Demanda Predictiva |