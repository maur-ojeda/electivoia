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

T1 | S1,S3,S4 | inline | `.gitignore` + `git rm --cached` + commit de `docs/personas.md` | pending
T2 | S2 | inline | revisión de secretos en artefactos locales (reporte de tipos, sin valores) | pending
T3 | S5,S6 | inline | CI por carpeta completa + fix error controlado sin API key | pending
T4 | S7 | inline | verificación Dokploy "done" + job espera resultado | pending
T5 | S8 | inline | rotación DB + escaneo de historial git + variables R2 | pending
T6 | S9 | inline | HU-R01 contexto limpio por rol + zip | pending

## Log

L1: "No empieces la migración a Flowbite. Orden:
1. Limpieza del repo: deja de versionar .DS_Store (git rm --cached) y agrégalo al .gitignore, junto con results/, test-results/, .playwright-mcp/, *.log y deploy-log-*.md / deploy-history-*.md. Antes de ignorar .playwright-mcp/ y los logs de deploy, revisa si contienen contraseñas, claves o DATABASE_URL. No me muestres el valor: solo dime qué archivo y qué tipo de secreto. Commitea docs/personas.md (es el documento del estudio, no se ignora). Decide con criterio si .atl/ se versiona o se ignora y dime por qué.
2. CI: corre las carpetas tests/Unit y tests/Integration completas, sin exclusiones. Corrige ChatbotControllerIntegrationTest: sin clave de API el endpoint debe responder un error controlado, no 500 (parte de HU-S04).
3. Confirma en Dokploy que el último deploy terminó en "done" y haz que el job de deploy espere ese resultado.
4. HU-S05: rota la contraseña de la base sin mostrarla y dime qué variables necesitas para los respaldos en Cloudflare R2.
5. Corre HU-R01 de nuevo con contexto limpio por rol y entrégame el zip."

L2 (2026-11-18): Recon de T1. `.DS_Store` versionado en 15 rutas. `.atl/skill-registry.md` (101 KB, 160 rutas absolutas `/Users/...`) + `.atl/.skill-registry.cache.json` (fingerprint) también versionados. `results/` (18 MB), `.playwright-mcp/` (8.2 MB), `test-results/`, `curl_monitor.log` (1.3 MB) y 3 `deploy-log*/deploy-history*` están untracked. `docs/personas.md` untracked. Ningún `*.log` versionado.

L3 (2026-11-18): Recon de T2. Escaneo negativo para credenciales reales: sin `postgresql://user:pass@`, sin `APP_SECRET=<valor>`, sin `GEMINI_API_KEY=<valor>` ni `AIza...`, sin `AKIA...`/`cloudflarestorage.com`, sin claves privadas PEM, sin `Set-Cookie`/`PHPSESSID` en los artefactos locales. Los matches iniciales de "password/token" son texto de UI y CSRF de snapshots de página.
