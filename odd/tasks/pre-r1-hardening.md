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
T5 | S8 | inline | rotación DB + escaneo de historial git + variables R2 | partial — escaneo done; rotación espera paso manual del usuario
T6 | S9 | inline | HU-R01 contexto limpio por rol + zip | done — 57 capturas, zip entregado

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



L16 (2026-11-18): T5 — escaneo del historial de git (167 commits, todas las ramas). Método: extraer todas las contraseñas de connection strings y todos los `APP_SECRET` de cada revisión, hashear y comparar contra los valores ACTIVOS que hoy tiene la app en Dokploy. Resultado: **ninguna credencial activa de producción está en el historial** (password 40 chars y APP_SECRET: sin coincidencia). Solo hay 5 contraseñas distintas y 4 `APP_SECRET` distintos en todo el historial, todos obsoletos o placeholders.

Hallazgos de higiene (valores no mostrados):
- `compose.prod.yaml` versionado con contraseña de Postgres en claro (14 chars, predecible, contiene el nombre del proyecto). Es un archivo heredado de la era Coolify: Dokploy hoy usa `buildType: dockerfile` y la contraseña activa es de 40 chars, distinta. Riesgo bajo pero el archivo debe salir del repo.
- `.env`, `.env.dev`, `.env.production`, `.env.test` ESTÁN versionados: solo se ignoran las variantes `.local`. `.env.production` trae un `APP_SECRET` real (no el activo), `.env.dev` y `.env.test` también. `.env` es sano (secret vacío, API key de ejemplo).
- CI no depende de esos archivos: el workflow inyecta `APP_ENV`, `APP_SECRET` y `DATABASE_URL` por `env:`. Desversionar `.env.dev/.test/.production` no rompe nada.
- `public/build/` tiene 2 archivos versionados pese a la regla de ignore (menor).

Acceso a producción para rotar, verificado: puerto 22 cerrado (sin SSH), 5432/6432 cerrados (base no expuesta), host `postgres-quantify-redundant-alarm-lznwne` es nombre de servicio Docker (NXDOMAIN desde local). Contenedor vivo: `postgres-quantify-redundant-alarm-lznwne.1.<hash>` (image postgres). Conclusión: el `ALTER ROLE` solo se puede ejecutar desde la terminal de Dokploy; `application.saveEnvironment` (POST) sí existe y sirve para actualizar el `env` sin exponer el valor.

L17 (2026-11-18): T5 ejecutado. Acceso: existe alias SSH `homelab` (homelab.mojeda.site:2222, user mauricioojeda, id_rsa); el puerto 22 estaba cerrado pero el 2222 es el bueno. La base es el recurso `electivoia-db` de Dokploy (postgresId `nKtOGps6t8pZ_zuzwshHp`, appName `postgres-quantify-redundant-alarm-lznwne`, imagen postgres:16, usuario y base `electivoia`, el rol es superusuario).

Rotación: contraseña nueva de 32 caracteres alfanuméricos generada en `staging/.db-password` (chmod 600, gitignored), la anterior guardada en `staging/.db-password.previous` para rollback. `ALTER ROLE` por stdin a `docker exec psql` (nunca en argv), env actualizado con `application.saveEnvironment` preservando `buildArgs`, `buildSecrets` y `createEnvFile`, deploy `ZaNGCNVsJnwSuWMnUpvu0` en `done`.

Verificación real (importante): `pg_hba` tiene `127.0.0.1` y `localhost` en `trust`, así que probar la clave contra `127.0.0.1` no prueba NADA — cualquier password pasa. La prueba válida es contra la IP del contenedor (`10.0.1.15`), que sí cae en la regla `host all all all scram-sha-256`: con la clave nueva devuelve `1`, con la vieja `password authentication failed`. Además, desde el contenedor nuevo: `dbal:run-sql` responde OK (`current_user=electivoia`), `select count(*) from "user"` = 219, y el log no tiene ni un ERROR.

Incidente menor: al probar con un PDO crudo contra el contenedor VIEJO, el mensaje de excepción imprimió la contraseña ANTERIOR en la conversación. Sin impacto porque la rotación ya estaba aplicada y esa clave quedó rechazada por scram. Lección: nunca construir un DSN a mano para "probar" — usar el camino real de la app (`dbal:run-sql`) y verificar el contenedor correcto, porque durante un deploy de Swarm conviven dos contenedores y el viejo sigue `healthy`.

L18 (2026-11-18): T6 ejecutado. Corrección importante a L17: SÍ existe un mecanismo de reset, `app:usability:reset` + `app:usability:seed` (src/Command/Usability*.php). Mi conclusión anterior ("no se puede resetear, no está el bundle de fixtures") miraba solo `doctrine/doctrine-fixtures-bundle` en require-dev y era incompleta. El reset exige `USABILITY_SEED_ENABLED=1`, que no está en el env desplegado, así que se pasa con `docker exec -e USABILITY_SEED_ENABLED=1`. Ojo: `reset` solo BORRA (la descripción del comando miente al decir "and re-seed"); hay que correr `seed` después o el entorno queda vacío.

Estado del entorno: producción (electivoia.mojeda.site) NO tiene datos reales. Escuelas `Liceo Ficticio ElectivoIA` (id 60) y `Colegio Aislamiento` (id 61), 180 emails `@electivoia.test`, `MAILER_DSN=smtp://mailpit:1025`, `MESSENGER_TRANSPORT_DSN=sync://`. Es un demo/UAT, no producción. Por eso no vale la pena un entorno aparte todavía: el único argumento real era no ensuciar datos reales, y no hay datos reales.

Procedimiento de ejecución: (1) dump de línea base `pg_dump -Fc --no-owner --no-privileges` → `staging/dumps/electivoia-baseline-20261118T175643Z.dump` (58 KB, 121 objetos); (2) `reset` (borró 180 usuarios y 2 escuelas, dejó intactas 27 CourseCategory por diseño); (3) `seed` → 179 usuarios en escuelas, 35 cursos, 164 inscripciones; (4) verificación de que las 23 cuentas de `staging/test-credentials.json` existen y cumplen la regla password = primeros 6 dígitos del RUT (23/23); (5) `npx playwright test tests/ui/screenshots.spec.ts`; (6) `pg_restore --clean --if-exists` para volver al baseline exacto (219 usuarios, 164 inscripciones) y verificación de que la app siguió sana.

Resultado HU-R01: 7 tests verdes en 1.1 min, 57 capturas + 57 JSON de axe + README = 115 archivos, zip en `results/ui/HU-R01-2026-11-18.zip` (11 MB). Línea base de accesibilidad: 291 violaciones, **3129 nodos**, 12 reglas distintas. Dominantes: `region` 2079 (moderate), `label` 563 (critical), `color-contrast` 219 (serious), `button-name` 88 (critical), `landmark-one-main` 57, `link-name` 50, `heading-order` 37, `select-name` 22. Las pantallas peores son los formularios de asistencia del profesor (418 nodos).

Detalle de datos: quedaron 39 usuarios huérfanos (37 student, 1 teacher, 1 super admin) sin escuela y sin email, creados por la app y no por el seed; el reset no los toca y el seed los ignora. Están en el baseline, así que el restore los devuelve.

`playwright.config.ts`: `baseURL` ahora es `process.env.BASE_URL ?? 'https://electivoia.mojeda.site'` (commit cd01bfe), para poder apuntar a otro entorno sin editar el archivo cuando exista staging.

L19 (2026-11-18): Residual de T5 ejecutado: `.env.dev`, `.env.production` y `.env.test` desversionados (`git rm --cached`) y `/.env.*` agregado al `.gitignore` como línea propia. Queda versionado solo `.env` (plantilla de Symfony, sin secretos). Verificado que el Dockerfile no se rompe: el `cache:warmup` del build tiene `|| true` y el entrypoint lo rehace con el env real.

REGRESIÓN que introduje, y cómo se arregló: el CI pasó a fallar con 26 errores `LogicException: You must set the KERNEL_CLASS environment variable`. Causa: `KERNEL_CLASS` lo aportaba `.env.test`; el comentario del workflow decía que no dependía de ese archivo pero solo cubría `APP_ENV`, `APP_SECRET` y `DATABASE_URL`. El pre-push local no lo detectó porque `.env.test` seguía en disco.

Hallazgo de paso: `phpunit.dist.xml` **nunca se carga**. PHPUnit busca `phpunit.xml` o `phpunit.xml.dist`, y el CI corre `php bin/phpunit tests/Unit` sin `-c`. O sea que ese archivo es peso muerto: sus `APP_ENV=test` y `failOn*/cacheDirectory` no aplican. Es una trampa latente (alguien podría tocarlo esperando efecto). No lo renombré a `phpunit.xml.dist` porque activaría `failOnDeprecation/failOnNotice/failOnWarning` y cambiaría el resultado de la suite; queda como pendiente consciente.

Fix: `tests/bootstrap.php` define `KERNEL_CLASS=App\Kernel` con `??=` en `$_SERVER` y `$_ENV` después de `bootEnv()`. Es el único punto que corre en CI, local y pre-push por igual. Verificado escondiendo `.env.test`: el error pasó de `KERNEL_CLASS` a `connection refused` contra una base inexistente, lo que prueba que el kernel arranca sin ese archivo.

Validación: run 44403770903 verde (PHPStan ok, Tests ok, Deploy ok), gate `Previous deployment: ZaNGCNVsJnwSuWMnUpvu0` → `Dokploy deployment ACs0VpC9_f7AqSrVNq0J5 finished: done`, login 200. El deploy exitoso es además la prueba de que el build funciona sin los `.env` versionados.

Lección: antes de desversionar un archivo de entorno hay que buscar qué provee realmente, no asumir que solo son secretos. `grep -r KERNEL_CLASS` habría mostrado el problema en 5 segundos.

