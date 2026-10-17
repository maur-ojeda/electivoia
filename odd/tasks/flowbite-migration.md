# Flowbite Admin Dashboard Migration
Branch: main | Runner: npm run build / redeploy Coolify | Strategy: inline

## Specs
S1: Reemplazar Bootstrap + DaisyUI + 15 plugins CSS por Tailwind 4 + Flowbite 3
S2: Layout base: sidebar fijo izquierda (w-64), navbar top, contenido principal — patrón exacto de flowbite-admin-dashboard
S3: Navegación sidebar role-aware (ROLE_STUDENT / ROLE_TEACHER / ROLE_ADMIN / ROLE_SUPER_ADMIN)
S4: Páginas a migrar: login, student/dashboard, student/courses, student/enrollments, student/profile, teacher/courses, teacher/attendance, admin custom templates
S5: EasyAdmin (/admin) mantiene su propio layout — no se toca
S6: Dark mode nativo de Flowbite (toggle en navbar)
S7: Build debe pasar sin errores (npm run build)

## Tasks
T1 | S1,S2 | inline | CSS entry + base.html.twig con sidebar Flowbite | pending
T2 | S1 | inline | security/login.html.twig con Flowbite login card | pending
T3 | S3,S4 | inline | student/* templates | pending
T4 | S3,S4 | inline | teacher/* templates | pending
T5 | S4 | inline | admin custom templates (reports, enrollment period, bulk enroll) | pending
T6 | S7 | inline | build + deploy staging | pending

## Log
L1: Usuario pide migrar UI a estándar flowbite-admin-dashboard (https://github.com/themesberg/flowbite-admin-dashboard). Stack actual: Bootstrap+DaisyUI+15 plugins. Tailwind 4 + Flowbite 3 ya instalados en package.json pero sin usar.
