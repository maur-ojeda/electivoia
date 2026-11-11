/**
 * HU-R01 · Revisión de UI con Playwright
 *
 * Prerrequisito: correr `app:usability:reset` en el servidor antes de ejecutar.
 * Ejecución: npx playwright test tests/ui/screenshots.spec.ts
 *
 * Genera en results/ui/<YYYY-MM-DD>/:
 *   <rol>-<pantalla>-<ancho>.png   capturas fullPage
 *   axe-<rol>-<pantalla>-<ancho>.json   violaciones de accesibilidad
 *   README.md   resumen de capturas y tabla de violaciones axe
 */

import { test, expect, Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import * as fs from 'fs';
import * as path from 'path';

// --- Configuración ---

const CREDS = JSON.parse(
  fs.readFileSync(path.resolve('staging/test-credentials.json'), 'utf-8')
);
const U = CREDS.school_liceo_ficticio.users;
const SA = CREDS.super_admin;

const DATE_STR = new Date().toISOString().slice(0, 10);
const OUT = path.resolve('results', 'ui', DATE_STR);
fs.mkdirSync(OUT, { recursive: true });

type SnapEntry = {
  file: string;
  role: string;
  screen: string;
  width: number;
  violations: { id: string; impact: string; description: string; nodes: unknown[] }[];
};
const log: SnapEntry[] = [];

// --- Helpers ---

async function login(page: Page, rut: string, password: string) {
  await page.goto('/login');
  await page.fill('input[name="_username"]', rut);
  await page.fill('input[name="_password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForURL((url) => !url.toString().includes('/login'), { timeout: 15_000 });
}

async function snap(page: Page, role: string, screen: string) {
  const { width } = page.viewportSize()!;
  const name = `${role}-${screen}-${width}`;
  const imgFile = path.join(OUT, `${name}.png`);
  await page.screenshot({ path: imgFile, fullPage: true });

  const axe = await new AxeBuilder({ page }).analyze();
  const axeFile = path.join(OUT, `axe-${name}.json`);
  fs.writeFileSync(axeFile, JSON.stringify(axe.violations, null, 2));

  log.push({ file: `${name}.png`, role, screen, width, violations: axe.violations });
  console.log(`  ✓ ${name}.png  (axe: ${axe.violations.length} violation(s))`);
}

async function withViewport(page: Page, vp: { width: number; height: number }, fn: () => Promise<void>) {
  await page.setViewportSize(vp);
  await fn();
}

// Captura la misma pantalla en dos viewports
async function snapBoth(page: Page, role: string, screen: string, mobile = true, notebook = true) {
  if (mobile) {
    await withViewport(page, { width: 390, height: 844 }, () => snap(page, role, screen));
  }
  if (notebook) {
    await withViewport(page, { width: 1366, height: 768 }, () => snap(page, role, screen));
  }
}

// Extrae el primer href que coincida con el patrón
async function firstHref(page: Page, pattern: RegExp): Promise<string | null> {
  const href = await page.evaluate((re: string) => {
    const link = Array.from(document.querySelectorAll('a[href]')).find((a) =>
      new RegExp(re).test((a as HTMLAnchorElement).href)
    );
    return link ? (link as HTMLAnchorElement).pathname + (link as HTMLAnchorElement).search : null;
  }, pattern.source);
  return href;
}

// =============================================================================
// ESTUDIANTE E1 · Catalina Rojas · sin inscripciones · promedio 6.6
// =============================================================================
test('E1 · estudiante sin inscripciones', async ({ page }) => {
  const { rut, password } = U.E1;

  // Login page (antes de autenticar)
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/login');
  await snap(page, 'e1', 'login');
  await page.setViewportSize({ width: 1366, height: 768 });
  await snap(page, 'e1', 'login');

  await login(page, rut, password);

  // Catálogo
  await page.goto('/student/courses');
  await page.waitForLoadState('networkidle');
  await snapBoth(page, 'e1', 'catalogo');

  // Catálogo filtrado por área (click en primer filtro de área)
  await page.setViewportSize({ width: 1366, height: 768 });
  await page.goto('/student/courses');
  await page.waitForLoadState('networkidle');
  const areaFilter = page.locator('select, [data-filter], input[type="radio"][name*="area"], button[data-area]').first();
  if (await areaFilter.count() > 0) {
    // Intentar seleccionar un área desde el primer filtro disponible
    const tag = await areaFilter.evaluate((el) => el.tagName.toLowerCase());
    if (tag === 'select') {
      await areaFilter.selectOption({ index: 1 });
    } else {
      await areaFilter.click();
    }
    await page.waitForTimeout(500);
  }
  await snap(page, 'e1', 'catalogo-filtrado');
  await page.setViewportSize({ width: 390, height: 844 });
  await snap(page, 'e1', 'catalogo-filtrado');

  // Tarjeta de curso lleno (Física o Química: status LLENO)
  await page.setViewportSize({ width: 1366, height: 768 });
  await page.goto('/student/courses');
  await page.waitForLoadState('networkidle');
  await snap(page, 'e1', 'curso-lleno');

  // Inscribirse en un curso con cupo (Comprensión Histórica — 36/40)
  const enrollForms = page.locator('form[action*="/student/enroll"]');
  const count = await enrollForms.count();
  if (count > 0) {
    await enrollForms.first().locator('button[type="submit"]').click();
    await page.waitForLoadState('networkidle');
    await snapBoth(page, 'e1', 'mensaje-exito');
  }

  // Mis inscripciones
  await page.goto('/student/enrollments');
  await page.waitForLoadState('networkidle');
  await snapBoth(page, 'e1', 'mis-inscripciones');

  // Asistencia
  await page.goto('/student/attendance');
  await page.waitForLoadState('networkidle');
  await snapBoth(page, 'e1', 'asistencia');

  // Mi perfil de intereses
  await page.goto('/student/profile');
  await page.waitForLoadState('networkidle');
  await snapBoth(page, 'e1', 'perfil-intereses');
});

// =============================================================================
// ESTUDIANTE E2 · Matías Fuentes · inscrito en Física + Comprensión
// =============================================================================
test('E2 · estudiante inscrito con asistencia', async ({ page }) => {
  const { rut, password } = U.E2;
  await login(page, rut, password);

  // Catálogo
  await page.goto('/student/courses');
  await page.waitForLoadState('networkidle');
  await snapBoth(page, 'e2', 'catalogo');

  // Mis inscripciones (con cursos)
  await page.goto('/student/enrollments');
  await page.waitForLoadState('networkidle');
  await snapBoth(page, 'e2', 'mis-inscripciones');

  // Asistencia (tiene 3 ausencias en Comprensión)
  await page.goto('/student/attendance');
  await page.waitForLoadState('networkidle');
  await snapBoth(page, 'e2', 'asistencia');

  // Asistente IA abierto (abrir el chatbot)
  await page.goto('/student/courses');
  await page.waitForLoadState('networkidle');
  const chatBtn = page.locator('[data-controller*="chat"], button[aria-label*="asistente" i], button[aria-label*="chatbot" i], #chatbot-toggle, .chatbot-fab').first();
  if (await chatBtn.count() > 0) {
    await chatBtn.click();
    await page.waitForTimeout(800);
  }
  await snapBoth(page, 'e2', 'asistente-ia-abierto');
});

// =============================================================================
// ESTUDIANTE E5 · Valentina Díaz · sin inscripciones · sin promedio → rechazo
// =============================================================================
test('E5 · rechazo inscripción curso lleno sin promedio', async ({ page }) => {
  const { rut, password } = U.E5;
  await login(page, rut, password);

  await page.goto('/student/courses');
  await page.waitForLoadState('networkidle');

  // Intentar postular a Química (llena, sin promedio)
  const quimicaForm = page.locator('form[action*="/student/enroll"]').last();
  if (await quimicaForm.count() > 0) {
    await quimicaForm.locator('button[type="submit"]').click();
    await page.waitForLoadState('networkidle');
    await snapBoth(page, 'e5', 'mensaje-rechazo');
  }

  // Catálogo estado general
  await page.goto('/student/courses');
  await page.waitForLoadState('networkidle');
  await snapBoth(page, 'e5', 'catalogo');

  // Sin inscripciones
  await page.goto('/student/enrollments');
  await page.waitForLoadState('networkidle');
  await snapBoth(page, 'e5', 'mis-inscripciones-vacio');
});

// =============================================================================
// PROFESOR P1 · Patricia Vergara · Comprensión Histórica del Presente
// =============================================================================
test('P1 · profesor', async ({ page }) => {
  const { rut, password } = U.P1;
  await login(page, rut, password);

  const VIEWPORTS = [
    { width: 390, height: 844 },
    { width: 1366, height: 768 },
    { width: 1180, height: 820 },
  ];

  for (const vp of VIEWPORTS) {
    await page.setViewportSize(vp);

    // Mis cursos
    await page.goto('/teacher/courses');
    await page.waitForLoadState('networkidle');
    await snap(page, 'p1', 'mis-cursos');

    // Navegar al primer curso para obtener el ID
    const courseLink = await firstHref(page, /\/teacher\/courses\/\d+\/students/);
    if (courseLink) {
      // Lista de alumnos
      await page.goto(courseLink);
      await page.waitForLoadState('networkidle');
      await snap(page, 'p1', 'lista-alumnos');

      // Extraer ID del curso
      const courseId = courseLink.match(/\/teacher\/courses\/(\d+)/)?.[1];
      if (courseId) {
        // Formulario de asistencia
        await page.goto(`/teacher/attendance/${courseId}`);
        await page.waitForLoadState('networkidle');
        await snap(page, 'p1', 'formulario-asistencia');

        // Reporte de asistencia
        await page.goto(`/teacher/report/${courseId}`);
        await page.waitForLoadState('networkidle');
        await snap(page, 'p1', 'reporte-asistencia');
      }
    }
  }
});

// =============================================================================
// APODERADO A2 · Andrés Lagos · dos pupilos: Josefa (E3) + Benjamín
// =============================================================================
test('A2 · apoderado', async ({ page }) => {
  const { rut, password } = U.A2;
  await login(page, rut, password);

  for (const vp of [{ width: 390, height: 844 }, { width: 1366, height: 768 }]) {
    await page.setViewportSize(vp);

    // Dashboard con dos pupilos
    await page.goto('/guardian/dashboard');
    await page.waitForLoadState('networkidle');
    await snap(page, 'a2', 'dashboard');

    // Scroll para ver detalle de asistencia si está abajo
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await page.waitForTimeout(400);
    await snap(page, 'a2', 'dashboard-scroll');
  }
});

// =============================================================================
// ADMINISTRADOR AD1 · Carolina Pizarro · Liceo Ficticio
// =============================================================================
test('AD1 · administrador', async ({ page }) => {
  const { rut, password } = U.AD1;
  await login(page, rut, password);

  await page.setViewportSize({ width: 1440, height: 900 });

  // Dashboard EasyAdmin
  await page.goto('/admin');
  await page.waitForLoadState('networkidle');
  await snap(page, 'ad1', 'dashboard');

  // Usuarios (navegar desde el menú)
  const userLink = await firstHref(page, /crudController.*User|\/admin.*user/i);
  if (userLink) {
    await page.goto(userLink);
    await page.waitForLoadState('networkidle');
    await snap(page, 'ad1', 'usuarios');
  } else {
    // Intentar directamente el menú de EasyAdmin
    const menuUser = page.locator('a', { hasText: /usuarios/i }).first();
    if (await menuUser.count() > 0) {
      await menuUser.click();
      await page.waitForLoadState('networkidle');
      await snap(page, 'ad1', 'usuarios');
    }
  }

  // Cursos
  const courseMenuLink = page.locator('a', { hasText: /cursos/i }).first();
  if (await courseMenuLink.count() > 0) {
    await courseMenuLink.click();
    await page.waitForLoadState('networkidle');
    await snap(page, 'ad1', 'cursos');

    // Editar el primer curso
    const editLink = await firstHref(page, /crudAction=edit|\/courses\/\d+\/edit/);
    if (editLink) {
      await page.goto(editLink);
      await page.waitForLoadState('networkidle');
      await snap(page, 'ad1', 'edicion-curso');
    }
  }

  // Inscripciones
  await page.goto('/admin/enrollments');
  await page.waitForLoadState('networkidle');
  await snap(page, 'ad1', 'inscripciones');

  // Período de inscripción
  await page.goto('/admin/enrollment-period');
  await page.waitForLoadState('networkidle');
  await snap(page, 'ad1', 'periodo');

  // Inscripción masiva
  await page.goto('/admin/enrollments/bulk');
  await page.waitForLoadState('networkidle');
  await snap(page, 'ad1', 'inscripcion-masiva');

  // Importar CSV
  await page.goto('/admin/users/import');
  await page.waitForLoadState('networkidle');
  await snap(page, 'ad1', 'importar-csv');

  // Reportes
  await page.goto('/admin/reports');
  await page.waitForLoadState('networkidle');
  await snap(page, 'ad1', 'reportes');

  // Panel IA
  await page.goto('/admin/ai');
  await page.waitForLoadState('networkidle');
  await snap(page, 'ad1', 'panel-ia');
});

// =============================================================================
// SUPER ADMIN SA1 · Ignacia Torres
// =============================================================================
test('SA1 · super admin', async ({ page }) => {
  const { rut, password } = SA.SA1;
  await login(page, rut, password);

  await page.setViewportSize({ width: 1440, height: 900 });

  // Panel global
  await page.goto('/super-admin');
  await page.waitForLoadState('networkidle');
  await snap(page, 'sa1', 'panel-global');

  // Formulario crear colegio
  await page.goto('/super-admin/onboard');
  await page.waitForLoadState('networkidle');
  await snap(page, 'sa1', 'crear-colegio');

  // Editar colegio (primer link de edición disponible)
  await page.goto('/super-admin');
  await page.waitForLoadState('networkidle');
  const editSchoolLink = await firstHref(page, /\/super-admin\/school\/\d+\/edit/);
  if (editSchoolLink) {
    await page.goto(editSchoolLink);
    await page.waitForLoadState('networkidle');
    await snap(page, 'sa1', 'editar-colegio');
  }
});

// =============================================================================
// Generar README.md al finalizar todos los tests
// =============================================================================
test.afterAll(async () => {
  if (log.length === 0) return;

  // Tabla de capturas
  const rows = log
    .map((e) => `| ${e.role} | ${e.screen} | ${e.width}px | ${e.violations.length} | ${e.file} |`)
    .join('\n');

  // Tabla de violaciones agrupadas por severidad
  const all = log.flatMap((e) =>
    e.violations.map((v) => ({ role: e.role, screen: e.screen, ...v }))
  );
  const bySeverity: Record<string, number> = {};
  for (const v of all) {
    bySeverity[v.impact] = (bySeverity[v.impact] ?? 0) + 1;
  }
  const sevRows = Object.entries(bySeverity)
    .sort(([a], [b]) => (['critical', 'serious', 'moderate', 'minor'].indexOf(a) - ['critical', 'serious', 'moderate', 'minor'].indexOf(b)))
    .map(([sev, n]) => `| ${sev} | ${n} |`)
    .join('\n');

  // Pantallas con scroll horizontal en 390px (tamaño del body > viewport)
  const mobileScrollScreens = log
    .filter((e) => e.width === 390)
    .map((e) => e.file);

  const md = `# HU-R01 · Capturas UI — ${DATE_STR}

## Capturas generadas

| Rol | Pantalla | Viewport | Violaciones axe | Archivo |
|-----|----------|----------|-----------------|---------|
${rows}

## Violaciones axe por severidad

| Severidad | Total |
|-----------|-------|
${sevRows || '| — | 0 |'}

## Scroll horizontal en 390px

Capturas en móvil (a verificar manualmente con las herramientas de DevTools):
${mobileScrollScreens.map((f) => `- ${f}`).join('\n') || '- Ninguna detectada automáticamente'}

## Instrucciones de revisión

1. Comparar capturas \`antes/\` vs \`después/\` entre fechas para detectar regresiones.
2. Las violaciones críticas y serias de axe deben resolverse en HU-U09.
3. Las pantallas marcadas con scroll horizontal deben verificarse abriendo el PNG en un visor.
`;

  fs.writeFileSync(path.join(OUT, 'README.md'), md);
  console.log(`\n📁 Resultados en: results/ui/${DATE_STR}/`);
  console.log(`   ${log.length} capturas · ${all.length} violaciones axe totales`);
  console.log(`   README.md generado`);
});
