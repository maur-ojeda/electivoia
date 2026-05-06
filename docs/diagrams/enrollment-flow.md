# Flujo de Inscripción — Diagrama de Secuencia

Diagrama de secuencia del flujo completo de inscripción de un Alumno a un electivo, incluyendo validaciones, motor de IA y consentimiento del Apoderado.

```mermaid
sequenceDiagram
    participant A as 🎒 Alumno
    participant SYS as Sistema
    participant RULES as Reglas de Inscripción
    participant IA as 🤖 Motor de IA
    participant BIAS as Detección de Sesgos
    participant AP as 👨‍👩‍👧 Apoderado
    participant AD as 🏫 Administrativo

    A->>SYS: Selecciona electivo del explorador
    SYS->>RULES: Validar reglas de inscripción (HU-AD-01)
    RULES-->>SYS: ¿Período activo? ¿Cupos disponibles? ¿Prerrequisitos?
    
    alt Reglas no cumplidas
        SYS-->>A: Mostrar motivo de rechazo (cupos, período, reglas)
    else Reglas cumplidas
        SYS->>IA: Solicitar recomendación para contexto
        IA->>BIAS: Verificar sesgos en recomendación (HU-IA-06)
        BIAS-->>IA: Sin sesgos detectados / Alerta de sesgo
        IA-->>SYS: Recomendación con score y explicación (HU-IA-01, HU-IA-02)

        alt Electivo requiere consentimiento del apoderado
            SYS-->>A: Inscripción en estado "Pendiente consentimiento"
            SYS->>AP: Notificación de solicitud de firma (HU-AP-02)
            AP->>SYS: Firma digital de consentimiento (aceptar/rechazar)
            
            alt Apoderado acepta
                SYS->>SYS: Confirmar inscripción
                SYS-->>A: Notificación de confirmación + comprobante PDF (HU-A-05)
                SYS-->>AD: Registro en auditoría (HU-AD-03)
            else Apoderado rechaza
                SYS-->>A: Notificación de rechazo con comentario
            end
        else Electivo no requiere consentimiento
            SYS->>SYS: Confirmar inscripción directamente
            SYS-->>A: Confirmación inmediata + comprobante PDF (HU-A-05)
            SYS-->>AD: Registro en auditoría (HU-AD-03)
        end
    end

    alt Electivo sin cupos disponibles
        SYS-->>A: Ofrecer lista de espera inteligente (HU-A-06)
        A->>SYS: Unirse a lista de espera
        SYS-->>A: Posición en lista + notificación automática si se libera cupo
    end
```

## Referencias

| Código | Historia de Usuario |
|--------|-------------------|
| HU-A-03 | Explorador de Electivos con Filtros |
| HU-A-04 | Simulador de Inscripción |
| HU-A-05 | Confirmación de Inscripción con Consentimiento |
| HU-A-06 | Lista de Espera Inteligente |
| HU-AD-01 | Configuración de Reglas de Inscripción |
| HU-AD-03 | Auditoría de Procesos de Inscripción |
| HU-AP-02 | Firma Digital de Consentimiento |
| HU-IA-01 | Recomendación por Perfil de Intereses |
| HU-IA-02 | Recomendación por Similitud |
| HU-IA-06 | Detección y Reporte de Sesgos |