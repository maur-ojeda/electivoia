# HU-R02 · Verificación de Release 2

Auditar los cinco issues abiertos de Release 2 contra el código en `main`, cerrar con evidencia lo ya implementado y documentar el gap real.

Branch: `fix/hu-r02-verificacion-release-2` · Delivery: single-pr · Runner: `php bin/phpunit`

## Specs

**S1.** #11 — el apoderado ve, para cada curso en que está inscrito su pupilo, el nombre del curso, el profesor y el horario. Ruta `GET /guardian/dashboard`, requiere `ROLE_GUARDIAN`.

**S2.** #12 — el admin ve una vista que lista, por profesor, cuántos cursos tiene asignado, ordenada de mayor a menor carga. Ruta `GET /admin/reports/by-teacher`.

**S3.** #13 — el admin ve insights generados sobre los **perfiles de interés** de los estudiantes, del estilo "El 70% de los estudiantes de 8° prefiere tecnología". Ruta `GET /admin/reports/insights`.

**S4.** #17 — el admin recibe alerta si un curso tiene menos del 30% de ocupación **1 semana antes del cierre de inscripciones** (`enrollmentEnd`). Vista `GET /admin/reports/low-enrollment` y comando `app:alert-low-enrollment`.

**S5.** #19 — al inscribirse, el estudiante recibe email de confirmación. El estudiante desplazado recibe aviso.

**S6.** Todos los endpoints anteriores respetan el aislamiento por colegio: un admin del colegio A no ve datos del colegio B.

**S7.** Cada spec anterior queda cubierta por al menos un test de regresión que falle si la funcionalidad desaparece.

## Tasks

| ID | Spec | Route | Status |
|----|------|-------|--------|
| T1 | S2, S6, S7 | inline | done — `AdminReportsTest` |
| T2 | S1, S6, S7 | inline | done — `GuardianDashboardTest` |
| T3 | S5, S7 | inline | done — `EnrollmentNotificationTest` |
| T4 | S3, S4 — documentar gap | inline | done — ver Log L5/L6 |
| T5 | cerrar #11/#12/#19 con evidencia | inline | partial — #11/#12 cerradas, #19 con riesgo abierto |

## Log

**L1** — Pedido del usuario: "Auditar Release 2 y cerrar lo hecho". Elegido sobre implementar #13 o #17 porque los cinco issues ya tienen código en `main` y escribir de nuevo duplicaría lógica existente. Es la tercera ocurrencia del mismo patrón (#8/#9/#10 → HU-S02b).

**L2** — Evidencia de lectura de código (sin ejecutar todavía):
- `templates/guardian/dashboard.html.twig:42-44` renderiza `enrollment.course.schedule`.
- `AdminReportController::byTeacher()` agrupa por profesor, cuenta cursos, ordena desc y filtra por colegio; template muestra `item.courses|length`.
- `AdminReportController::insights()` construye `$stats` desde inscripciones y categorías; `InterestProfile` no aparece en `GeminiInsightsService` ni en el controlador.
- `AdminReportController::lowEnrollment()` y `AlertLowEnrollmentCommand` usan 30% fijo; ninguno consulta `enrollmentEnd`. No hay scheduler en `config/packages`.
- `EnrollmentService` llama `sendEnrollmentConfirmation()` en las líneas 175 y 223.
- Sin tests: no existe ningún integration test para `admin_reports_*`, `guardian_dashboard` ni `NotificationService`.

**L3** — Verificado ejecutando, no leyendo. `AdminReportsTest` (5 tests) prueba #12 con control positivo (dos profesores propios, el de 2 cursos aparece antes que el de 1) y aislamiento por colegio; #17 reporta el curso al 0% y excluye el que está al 100%; #13 renderiza. `GuardianDashboardTest` (2 tests) prueba #11: el horario "Lun 09:00-10:30" aparece y un curso no matriculado no aparece.

**L4** — **Bug real encontrado y corregido.** `EnrollmentRepository::findEnrollmentsByCourseOrderedByGradeAsc()` ordenaba por `COALESCE(s.averageGrade, 0.0)`, que no existe en DQL: `[Syntax Error] ... Expected known function, got 'COALESCE'`. El método explota siempre, y solo se llama cuando el curso está lleno. Consecuencia: inscribirse en un curso lleno devolvía error en vez de aplicar la regla de promedio. Se reemplazó por `CASE WHEN s.averageGrade IS NULL THEN 0.0 ELSE s.averageGrade END`, que expresa la misma intención y es DQL válido. El camino con cupo funcionaba, por eso nadie lo detectó.

**L5** — **#13 sigue parcial.** Confirmado en ejecución: la página `/admin/reports/insights` responde 200 y renderiza, pero alimenta a `GeminiInsightsService` con inscripciones por categoría. `InterestProfile` (entidad `student` + `interests` json `array<string,int>`) no se consulta en ningún punto del flujo. El ejemplo del issue ("70% de 8° prefiere tecnología") no se puede producir con los datos actuales.

**L6** — **#17 sigue parcial.** La vista y el comando existen con umbral 30% fijo, pero ninguno filtra por `enrollmentDeadline`/`enrollmentEnd`: alertan cualquier curso activo bajo 30% durante todo el año. Tampoco hay scheduler en `config/packages` ni referencia al comando en CI/deploy, así que el comando nunca corre solo.

**L7** — **Riesgo abierto en #19: los emails se encolan, no se envían.** `config/packages/messenger.yaml` rutea `SendEmailMessage` a `async` (doctrine). `Mailer::send()` registra el evento con `queued=true` y despacha al bus; si no hay worker `messenger:consume async`, la fila queda en `messenger_messages` para siempre. No hay ninguna referencia a `messenger:consume` en el repo (ni CI, ni scripts, ni docs), así que la entrega real depende de que Dokploy corra un worker — no verificable desde el repo. Por eso #19 NO se cierra todavía.

**L8** — Infra de test: se agregó `when@test` en `messenger.yaml` ruteando `async` a `sync://`. Sin worker durante los tests, esto evita que las filas se acumulen en `messenger_messages` y hace que el mailer recorra el camino real de envío. Con bus configurado cada email genera dos eventos (`queued` y `sent`), por eso los tests filtran `!isEventQueued()` para no contar dos veces.

**L9** — La suite quedó en **158 tests / 606 assertions OK** (antes 149/547). PHPStan limpio, php-cs-fixer 0/129. Nota de fragilidad preexistente: `StudentInterestsTest` depende del estado de la BD; restos de una corrida fallida (6 cursos `ZZ %`) lo hicieron fallar por límite de resultados en recomendaciones. Se limpiaron y se endureció el `tearDown` de los tests nuevos para no dejar restos.

