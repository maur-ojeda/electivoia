# Pre-R1 hardening — higiene de repo, CI, deploy gate, secretos y línea base UI
Branch: main | Runner: php bin/phpunit / npm run build / GitHub Actions | Strategy: inline

## Specs

S1 (limpieza — versionado): Dejar de versionar `.DS_Store` (`git rm --cached`) y agregarlo al `.gitignore`, junto con `results/`, `test-results/`, `.playwright-mcp/`, `*.log` y `deploy-log-*.md` / `deploy-history-*.md`.

S2 (limpieza — revisión de secretos): Antes de ignorar `.playwright-mcp/` y los logs de deploy, revisar si contienen contraseñas, claves o `DATABASE_URL`. No mostrar el valor: solo informar qué archivo y qué tipo de secreto.

S3 (limpieza — documento): Commitear `docs/personas.md` (es el documento del estudio, no se ignora).

S4 (limpieza — decisión `.atl/`): Decidir con criterio si `.atl/` se versiona o se ignora, y explicar por qué.

S5 (CI): Correr las carpetas `tests/Unit` y `tests/Integration` completas en el workflow, sin exclusiones.

S6 (CI — HU-S04): Corregir `ChatbotControllerIntegrationTest`: sin clave de API el endpoint debe responder un error controlado, no 500.

S7 (deploy): Confirmar en Dokploy que el último deploy terminó en "done" y hacer que el job de deploy espere ese resultado.

S8 (HU-S05 — credenciales): Rotar la contraseña de la base sin mostrarla; revisar el historial de git buscando secretos e informar sin mostrarlos; indicar qué variables se necesitan para los respaldos en Cloudflare R2.

S9 (HU-R01): Correr HU-R01 de nuevo con contexto limpio por rol (`tests/ui/screenshots.spec.ts`), entregar el `.zip` de `results/ui/<fecha>/`.

## Tasks

T1 | S1,S3,S4 | inline | `.gitignore` + `git rm --cached` + commit de `docs/personas.md` | done (406bfef, 436b66c)
T2 | S2 | inline | revisión de secretos en artefactos locales (reporte de tipos, sin valores) | done — sin secretos reales
T3 | S5,S6 | inline | CI por carpeta completa + fix error controlado sin API key | done (aeb5010) — verificado en CI
T4 | S7 | inline | verificación Dokploy "done" + job espera resultado | done (bb005fc, 5b3057e) — validado en deploy real
T5 | S8 | inline | rotación DB + escaneo de historial git + variables R2 | pending — falta acceso prod
T6 | S9 | inline | HU-R01 contexto limpio por rol + zip | pending — falta decisión de entorno

## Log

L1: "No empieces la migración a Flowbite. Orden:
1. Limpieza del repo: deja de versionar .DS_Store (git rm --cached) y agrégalo al .gitignore, junto con results/, test-results/, .playwright-mcp/, *.log y deploy-log-*.md / deploy-history-*.md. Antes de ignorar .playwright-mcp/ y los logs de deploy, revisa si contienen contraseñas, claves o DATABASE_URL. No me muestres el valor: solo dime qué archivo y qué tipo de secreto. Commitea docs/personas.md (es el documento del estudio, no se ignora). Decide con criterio si .atl/ se versiona o se ignora y dime por qué.
2. CI: corre las carpetas tests/Unit y tests/Integration completas, sin exclusiones. Corrige ChatbotControllerIntegrationTest: sin clave de API el endpoint debe responder un error controlado, no 500 (parte de HU-S04).
3. Confirma en Dokploy que el último deploy terminó en "done" y haz que el job de deploy espere ese resultado.
4. HU-S05: rota la contraseña de la base sin mostrarla y dime qué variables necesitas para los respaldos en Cloudflare R2.
5. Corre HU-R01 de nuevo con contexto limpio por rol y entrégame el zip."

L2 (2026-11-18): Recon de T1. `.DS_Store` versionado en 15 rutas. `.atl/skill-registry.md` (101 KB, 160 rutas absolutas `/Users/...`) + `.atl/.skill-registry.cache.json` (fingerprint) también versionados. `results/` (18 MB), `.playwright-mcp/` (8.2 MB), `test-results/`, `curl_monitor.log` (1.3 MB) y 3 `deploy-log*/deploy-history*` están untracked. `docs/personas.md` untracked. Ningún `*.log` versionado.

L3 (2026-11-18): Recon de T2. Escaneo negativo para credenciales reales: sin `postgresql://user:pass@`, sin `APP_SECRET=<valor>`, sin `GEMINI_API_KEY=<valor>` ni `AIza...`, sin `AKIA...`/`cloudflarestorage.com`, sin claves privadas PEM, sin `Set-Cookie`/`PHPSESSID` en los artefactos locales. Los matches iniciales de "password/token" son texto de UI y CSRF de snapshots de página.

L4 (2026-11-18): T1 ejecutado. `.gitignore` suma bloques de OS, artefactos Playwright, logs/dumps locales y `.atl/`. `git rm --cached` sobre 15 `.DS_Store` + 2 archivos de `.atl/`. Decisión `.atl/`: se ignora porque `skill-registry.md` es un índice autogenerado del entorno de cada máquina (160 rutas absolutas) y `.skill-registry.cache.json` es un fingerprint local; versionarlos filtra configuración personal y genera conflictos en cada regeneración. Commits: 406bfef (untrack + ignore), 436b66c (docs/personas.md), bdccae2 (este documento).

L5 (2026-11-18): T3 — verificación del handoff anterior. El test `testUnauthenticatedRequestReturns401` PASA aislado con `GEMINI_API_KEY=''` y sin base de datos; el "500 en vez de 401" no se reproduce por esa vía. La causa real encontrada: (a) `chat()` capturaba `\Exception`, no `\Throwable`, así que un `TypeError` interno escapaba como 500; (b) `GeminiMockHttpClient` devolvía un payload de formato Gemini (`candidates[]`) mientras el servicio parsea SSE de OpenRouter (`data: {"choices":[{"delta":...}]}`), dejando toda respuesta mockeada vacía. (c) 3 archivos de test fuera del CI habían quedado con la firma previa del constructor de `RecommendationService` (16 errores).

L6 (2026-11-18): T3 ejecutado. `tests/Unit` pasó de 53 tests (allow-list) a 87 verdes / 293 assertions; PHPStan sin errores. Fix aplicado: guard de `aiApiKey` vacía que devuelve mensaje controlado con `retry`, `catch (\Throwable)`, mock en formato SSE, y workflow ahora corre `tests/Unit` y `tests/Integration` completos. Commit aeb5010. La carpeta `tests/Integration` NO se pudo ejecutar en local: el usuario de Postgres no tiene permiso `CREATEDB` y Docker no está corriendo, así que no hay base aislada; correrlos contra `.env.local` habría usado la base de desarrollo.

L7 (2026-11-18): Recon de T4/T5/T6. Dokploy responde en `https://dokploy.mojeda.site`, aplicación `abkpgAERWTHYe83QNhpKH`, autenticado con `secrets.DOKPLOY_API_KEY` (secreto de GitHub, no disponible en local). Sitio de producción `https://electivoia.mojeda.site` responde 200. Playwright 1.65 y `@axe-core/playwright` instalados. `staging/test-credentials.json` presente con 12+ usuarios por rol.

L8 (2026-11-18): Último deploy confirmado en Dokploy: `mHcZNPlfkG0Q4UBLVYaWI`, status `done`, 13:27:03Z → 13:31:03Z, commit del fix de RUT. `application.one` devuelve el campo `env` con secretos en claro (diseño de la API de Dokploy), por lo que respuestas crudas de tRPC no deben imprimirse nunca.

L9 (2026-11-18): T4 — polling escrito en `bb005fc`. `ci.yml` ahora dispara el deploy y sondea `deployment.all` cada 15 s hasta estado terminal (`done` → exit 0; `error|failed|cancelled` → exit 1; timeout 20 min → exit 1). Filtro `jq` validado contra la respuesta real guardada: con `since` previo devuelve `mHcZNPlfkG0Q4UBLVYaWI done`; con `since` posterior devuelve `? ?` y el loop sigue esperando. Job con `timeout-minutes: 25`.

L10 (2026-11-18): MCP oficial de Dokploy instalado en `~/.config/opencode/opencode.json` con `DOKPLOY_ENABLED_TAGS=deployment` (9 tools de solo lectura), `DOKPLOY_REDACT_ENV=true` y `DOKPLOY_API_KEY` como placeholder `PEGAR_CLAVE_NUEVA_AQUI` — la clave la escribe el usuario, nunca pasa por el chat. Backup del config en `opencode.json.bak-<timestamp>`. Inserción por string, sin round-trip JSON, para no reformatear el archivo de 143 KB.

L11 (2026-11-18): Incidente propio. Al inspeccionar el bloque `mcp` del config global se imprimió el token de Neon (`napi_...`) en la conversación. Debe rotarse. Regla adoptada: nunca volcar contenido de archivos de configuración, solo estructura y nombres de claves.

L12 (2026-11-18): Estado de credenciales tras la rotación del usuario: la API key vieja de Dokploy ahora responde `401 UNAUTHORIZED` (la revocación sí tomó). Pendiente y roto hasta resolverlo: (a) `secrets.DOKPLOY_API_KEY` de GitHub sigue con el valor viejo → todo deploy futuro falla 401; (b) `AI_API_KEY` en el `env` de Dokploy sigue con la clave vieja de OpenRouter → el chatbot de producción está degradado hasta actualizar y redesplegar.

L13 (2026-11-18): Incidente de secretos (lección). Al correr `gh secret set` sin nombre en el comando, `gh` pidió primero el nombre y el usuario pegó la API key ahí: quedó como NOMBRE de secreto (`RFXT...`, 2026-11-18T15:30:25Z). Los nombres de secretos de GitHub no están cifrados ni redactados. Se borró el secreto basura; esa llave de Dokploy quedó quemada y hubo que crear una tercera. Regla: siempre `gh secret set NOMBRE` con el nombre en la línea de comando, y el valor por stdin (`pbpaste |` o pipe desde config), nunca por prompt interactivo.

L14 (2026-11-18): Dos ediciones manuales seguidas de `~/.config/opencode/opencode.json` lo invalidaron en la misma línea: (a) clave sin comilla de cierre; (b) clave sin coma final. Ambos casos tumban TODOS los servidores MCP, no solo dokploy. Regla: validar con `python3 -c "import json;json.load(open(...))"` después de cada edición manual.

L15 (2026-11-18): T4 cerrado y validado en un deploy real. Push de `5b3057e` (7 commits) → run 44353984072: PHPStan success, Tests success (carpetas completas, incluida `tests/Integration` con `ChatbotControllerIntegrationTest`), Deploy success. Log del gate: `Previous deployment: sucDgBHGy81XrSX7iawWK`, luego `deployment=80Ee6SqNeiZtK_WnMkW0Y status=running` (x2), `status=done`, `Dokploy deployment 80Ee6SqNeiZtK_WnMkW0Y finished: done`. Dato util: `application.deploy` responde `{"json":null,...}`, no devuelve deploymentId — por eso el gate no debe parsear la respuesta del trigger. El filtro pasó de timestamp a ID por riesgo de desfase de reloj. `Coding Standards (advisory)` sigue en rojo y no bloquea.



