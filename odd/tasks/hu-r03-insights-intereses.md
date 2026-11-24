# HU-R03 · Insights de IA basados en perfiles de intereses (#13)

Rama: `fix/issue-13-insights-intereses` · Runner: `php bin/phpunit` · Issue: https://github.com/maur-ojeda/electivoia/issues/13

## Specs

**S1.** `GET /admin/reports/insights` muestra, por grado, qué porcentaje de los estudiantes prefiere cada categoría, calculado desde `InterestProfile.interests`.
- Aceptación: con 7 de 10 estudiantes de `4M` en nivel ≥ 3 para "Artes" y 3 en "Matemática", la página muestra `4M · 10 perfiles · Artes 70%, Matemática 30%`.
- El prompt enviado al proveedor de IA incluye esas preferencias.

**S2.** El denominador por grado son los estudiantes que **respondieron** (al menos un interés ≥ 1), no todas las filas de `interest_profile`.
- Motivo: `StudentController::profile()` persiste siempre las 7 categorías, con 0 cuando no se marcó nada. Contar esas filas hundiría el porcentaje.

**S3.** "Prefiere" significa nivel ≥ 3, el umbral ya usado por `InterestProfileRepository::countStudentsInterestedInCategory()` y `DemandPredictionService::INTEREST_THRESHOLD`. No se introduce un umbral nuevo.

**S4.** Los perfiles de otro colegio no se leen, y un estudiante sin grado no entra al agregado.

**S5.** Sin clave de IA el reporte igual dice la preferencia principal de cada grado; si no hay perfiles respondidos, lo dice explícitamente. Con datos el prompt agrega la sección de preferencias; sin datos queda idéntico a antes.

## Tasks

| ID | Spec | Ruta | Estado | Evidencia |
|----|------|------|--------|-----------|
| T1 | S1-S4 | inline | done | `tests/Integration/InsightsInterestProfileTest.php` — 4 tests |
| T2 | S1, S5 | inline | done | `tests/Unit/Service/GeminiInsightsServiceTest.php` — 4 tests |
| T3 | S1 | inline | done | `InterestProfileRepository::countPreferencesByGradeInSchool()`, `AdminReportController::buildInterestStats()`, panel en `insights.html.twig` |
| T4 | — | inline | done | 165 tests / 625 assertions · PHPStan limpio · php-cs-fixer 0/131 |

## Log

- **L1** (2026-11-24, usuario): "ataca #13 pero limpiemos contexto"
- **L2** (issue #13, verbatim): "Como administrador, quiero ver insights como 'El 70% de los estudiantes de 8° prefiere tecnología' basados en los perfiles de intereses."
- **L3**: la auditoría de HU-R02 (PR #24) confirmó que `insights()` construía `$stats` desde inscripciones por categoría y nunca consultaba `InterestProfile`. La infraestructura correcta ya existía (`countStudentsInterestedInCategory`, `countProfilesInSchool`) y solo la usaban `AiDashboardController` y `DemandPredictionService`.
- **L4**: decisiones — reusar el umbral ≥ 3 existente; denominador = perfiles respondidos; agregación por grado en el repositorio (una sola query, sin N+1) y formateo de porcentajes en el controlador.
- **L5**: en `test` el proveedor de IA está mockeado (`tests/Double/GeminiMockHttpClient.php`), así que `fallbackInsights()` no se ejerce por HTTP. Por eso el panel determinista se testea en integración y el prompt/fallback en unit.
- **L6**: cambio de comportamiento conocido — sin perfiles respondidos el fallback agrega un bullet ("Todavía no hay perfiles…"). El prompt queda idéntico, cubierto por `testPromptOmitsTheInterestSectionWhenNoProfileWasAnswered`.
- **L7**: trade-off aceptado — `countPreferencesByGradeInSchool()` hidrata entidades por colegio. Suficiente para el volumen esperado y evita una query por categoría.
