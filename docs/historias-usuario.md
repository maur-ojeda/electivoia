# Historias de usuario — ElectivoIA

Nov 11, 2026 · @Mauricio

## Cómo usar este backlog

Este documento convierte los hallazgos y las 15 decisiones de UX del pre-test con personas sintéticas (ver `docs/personas.md`) en 24 historias de usuario para Claude Code. Cada historia trae su rol, su objetivo, sus criterios de aceptación y las referencias a los hallazgos (P#) y decisiones (D#) que resuelve.

**Reglas que aplican a todas las historias**

1. Antes de escribir código, mostrar al usuario un plan breve de la historia y esperar su visto bueno.
2. Nunca mostrar secretos (contraseñas, claves, `DATABASE_URL`) en el chat ni en logs.
3. Nunca crear archivos en el servidor de producción; las consultas a la base se hacen por consola (`docker exec` y `bin/console dbal:run-sql`).
4. Antes de cualquier migración o borrado de datos, un `pg_dump` completo guardado fuera del servidor.
5. No hacer push a `main` mientras haya un deploy en curso en Dokploy, y confirmar que cada commit quedó desplegado.
6. Las historias marcadas "Validar con UTP" no se publican a usuarios reales hasta que el usuario lo apruebe.
7. Las pruebas en producción se hacen solo con el Liceo Ficticio y el Colegio Aislamiento.

**Definición de terminado**

Una historia está terminada cuando:

- Cumple todos sus criterios de aceptación.
- Tiene tests automáticos (funcionales o de integración) que cubren sus criterios, y pasan.
- Está desplegada y verificada en producción con el colegio de prueba.
- Tiene capturas de Playwright del antes y el después (ver HU-R01), guardadas en `results/ui/`.
- Se informa al usuario con: estado, commit, tests, capturas y cualquier desviación del criterio.

## HU-R01 · Revisión de UI con Playwright

**Como** responsable del producto, **quiero** capturas de todas las pantallas clave por rol y tamaño de pantalla, más un reporte automático de accesibilidad, **para** hacer una revisión de UI y comparar el antes y el después de cada entrega.

Se ejecuta primero, como línea base, y se repite después de R1, R2 y R3. Usa un script de Playwright (`tests/ui/screenshots.spec.ts`), no agentes, para que las capturas sean repetibles.

**Pantallas por rol**

| Rol (persona) | Pantallas |
| --- | --- |
| Estudiante (E1, E2, E5) | Login, catálogo, catálogo filtrado por área, tarjeta de curso lleno, mensaje de éxito, mensaje de rechazo, Mis inscripciones, Asistencia, Mi perfil de intereses, Asistente IA abierto |
| Estudiante desplazado (E2, después de S1) | Catálogo y Mis inscripciones tras el desplazamiento |
| Profesor (P1) | Mis cursos, lista de alumnos, formulario de asistencia, reporte de asistencia |
| Apoderado (A2) | Dashboard con dos pupilos y detalle de asistencia |
| Administrador (AD1) | Usuarios, cursos, edición de curso, inscripciones, período, inscripción masiva, importar CSV, reportes, Panel IA |
| Super admin (SA1) | Panel global, crear colegio, editar colegio |

**Criterios de aceptación**

- Cada pantalla se captura completa (`fullPage`) en los tamaños que aplican: celular 390x844 y notebook 1366x768 para estudiante y apoderado; además tablet 1180x820 para profesor; escritorio 1440x900 para administrador y super admin.
- Las capturas se nombran `results/ui/<fecha>/<rol>-<pantalla>-<ancho>.png`.
- En cada pantalla se corre `@axe-core/playwright` y se guarda el resultado en `results/ui/<fecha>/axe-<rol>-<pantalla>.json`.
- Se genera `results/ui/<fecha>/README.md` con: la lista de capturas, una tabla de violaciones de axe por severidad y pantalla, y las pantallas con scroll horizontal en 390 px.
- Se incluyen los estados especiales: curso lleno, rechazo sin promedio, rechazo por promedio, banner de desplazamiento (cuando exista), modal de confirmación (cuando exista) y página sin datos.
- El script hace login con las credenciales de `staging/test-credentials.json` sin mostrarlas, y corre después de un reset.
- Al terminar se entrega al usuario un `.zip` con la carpeta de la fecha, para la revisión de UI.

## Seguridad y bugs pendientes

Estas siete historias van antes que cualquier mejora de UX, porque afectan la seguridad, la privacidad o la integridad de los datos.

### HU-S01 · Acceso seguro

**Como** estudiante o apoderado, **quiero** entrar con una contraseña que solo yo conozca y poder recuperarla, **para** que nadie entre a mi cuenta. Resuelve D15, hallazgos previos 1 y 5, P3 y P52.

- La contraseña inicial sigue una sola regla, igual en el seed, el importador CSV y lo que se comunica al colegio.
- En el primer ingreso, el usuario debe cambiar la contraseña antes de usar el sistema: mínimo 8 caracteres y distinta de su RUT.
- El login no muestra pistas sobre la contraseña.
- Tras 5 intentos fallidos en 15 minutos para un mismo RUT o IP, el acceso se bloquea temporalmente con un mensaje claro.
- "¿Olvidaste tu contraseña?" envía al correo registrado un enlace de un solo uso, válido por 1 hora. Si el usuario no tiene correo, se le indica que contacte a su colegio.
- El login acepta el RUT con o sin puntos y con k minúscula (ya implementado; mantener el test).

### HU-S02 · Aislamiento entre colegios

**Como** colegio, **quiero** que nadie de otro colegio pueda ver ni modificar mis datos, **para** proteger la información de mis estudiantes. Resuelve P48.

- Un usuario del colegio A recibe 403 o 404 al ver, editar, borrar o usar acciones por lote sobre entidades del colegio B en todos los CRUD del panel: cursos, usuarios, inscripciones, asistencia, perfiles IA, señales IA y apoderado-pupilo.
- Los selectores y el autocompletado de los formularios solo ofrecen entidades del propio colegio.
- Todas las rutas propias que reciben un ID (estudiante, profesor, apoderado, administrador y exportaciones) responden 403 o 404 con entidades de otro colegio. Entregar el listado de rutas revisadas.
- La URL con formato antiguo de EasyAdmin responde 404, no 500, y no carga la entidad.
- El super admin conserva acceso a todo.
- Los tests funcionales de aislamiento corren antes de cada deploy.

### HU-S03 · Avatares sin datos personales

**Como** estudiante, **quiero** que mis datos no se envíen a servicios externos, **para** proteger mi privacidad. Resuelve P21.

- Los avatares se generan con las iniciales en el propio servidor o con CSS.
- Ninguna petición a un dominio externo contiene RUT, nombre ni correo (verificado con las peticiones de red de Playwright en las vistas de profesor y administrador).
- La consola no muestra errores 502 de avatares.

### HU-S04 · Asistente IA apto para menores

**Como** apoderado, **quiero** que los datos de mi hijo no se usen para entrenar modelos de terceros, **para** confiar en el asistente. Resuelve P42, P50 y P38.

- Antes de implementar, presentar al usuario dos opciones de modelo con su política de datos y costo mensual estimado, y esperar su elección.
- El modelo elegido no entrena ni retiene los mensajes; queda documentado cuál es y su política.
- Al modelo solo se envían el primer nombre, el nivel, los intereses, las inscripciones y los cursos disponibles; nunca el RUT ni el nombre completo.
- Ante una respuesta vacía o un error, se muestra "El asistente está ocupado. Intenta de nuevo en unos segundos" con un botón de reintento, sin bloquear el envío.
- En 20 mensajes de prueba, el 95% responde en menos de 8 segundos.
- Los cupos que informa el asistente coinciden con los de la página.

### HU-S05 · Credenciales y respaldos

**Como** responsable técnico, **quiero** secretos protegidos y respaldos automáticos, **para** poder recuperarme de un error o un incidente. Resuelve P51.

- La contraseña de la base se rota otra vez, sin mostrarla.
- Se revisa el historial de git en busca de secretos; si aparece alguno, se informa sin mostrarlo.
- Respaldo diario de la base completa hacia Cloudflare R2, configurado en Dokploy (el usuario entrega las credenciales del bucket).
- Se prueba una vez la restauración del respaldo en una base temporal.
- La carpeta `public/` solo contiene archivos del código versionado.

### HU-S06 · Zona horaria de Chile

**Como** coordinadora, **quiero** que el período de inscripción abra y cierre a la hora de Chile, **para** que coincida con lo que comuniqué a los estudiantes. Resuelve P2.

- Las fechas se guardan en UTC y se muestran en America/Santiago en pantallas, correos y exportaciones.
- Un período que cierra a las 23:59 rechaza inscripciones a las 00:00 hora de Chile.
- Un test cubre el cambio de horario de verano.

### HU-S07 · Errores pendientes del panel

**Como** administrador, **quiero** mensajes claros en vez de errores del servidor, **para** saber qué corregir. Resuelve P29, P39 y P44.

- Crear un colegio con un correo de administrador ya registrado muestra un error en el campo, sin HTTP 500.
- Hay un solo panel por rol: se explica y corrige por qué dos administradores veían paneles distintos.
- El importador CSV indica el número de línea de cada duplicado y ofrece una plantilla descargable.

## Entrega R1 · Núcleo de la inscripción

R1 debe estar lista antes del próximo período de inscripción: cubre el desplazamiento, la privacidad y el horario.

### HU-U01 · Límite de electivos y áreas

**Como** estudiante, **quiero** saber cuántos electivos debo tomar y de qué áreas, **para** elegir bien y no acaparar cupos. Resuelve D1, P8, P11 y P36. **Validar con UTP** el límite y la regla de áreas antes de publicar.

- Cada colegio configura el máximo de electivos (por defecto 3) y el mínimo de áreas distintas (por defecto 2).
- Al intentar superar el máximo: "Ya tienes 3 electivos. Date de baja de uno para inscribirte en otro".
- El catálogo muestra el avance: "Llevas 2 de 3 electivos, de 2 áreas", y avisa si falta cubrir un área.
- Una leyenda explica qué son las Áreas A, B y C.
- Solo estudiantes de 3M y 4M pueden inscribirse; los demás ven el catálogo sin botón y con una explicación.

### HU-U02 · Lista de espera por promedio

**Como** estudiante desplazado, **quiero** quedar en una lista de espera, **para** recuperar el cupo si se libera. Resuelve D2, P16, P26 y el escenario S2.

- Al ser desplazado, el estudiante entra a la lista de espera de ese curso.
- Quien postula a un curso lleno sin el promedio suficiente también entra a la lista.
- La lista se ordena por promedio descendente; los empates, por fecha de postulación.
- Al liberarse un cupo, por una baja o una ampliación, entra automáticamente el primero de la lista, si no supera su límite de electivos ni tiene choque de horario. Se le avisa por correo y en la app.
- El estudiante ve su puesto y puede salir de la lista; el profesor y el administrador ven la lista completa.
- Al cierre del período, la lista se cierra.

### HU-U03 · Cursos llenos explicados

**Como** estudiante, **quiero** saber antes de postular que un curso está lleno y qué pasará, **para** decidir con información. Resuelve D3, P4, P5 y P32.

- La tarjeta de un curso lleno muestra "Lleno · se asigna por promedio" y el botón dice "Postular".
- Al postular a un curso lleno aparece una confirmación: "Este curso está lleno. Si tu promedio supera al del último inscrito, entrarás y esa persona pasará a lista de espera". Cancelar no cambia nada.
- En cursos con cupos, la inscripción sigue siendo de un clic.
- La confirmación funciona con teclado: el foco queda dentro y Escape la cierra.
- Las recomendaciones de la IA marcan los cursos llenos.

### HU-U04 · Privacidad del desplazamiento

**Como** estudiante, **quiero** que nadie sepa que yo perdí un cupo ni cuál es mi promedio, **para** evitar conflictos con compañeros. Resuelve D4 y P13.

- Ningún mensaje, correo ni pantalla para estudiantes o apoderados muestra el nombre o el promedio de otro estudiante.
- Mensaje al entrar a un curso lleno: "Entraste a Física. El curso estaba lleno y tu promedio te dio prioridad".
- Un test verifica que la respuesta de la inscripción no contiene el nombre del desplazado.

### HU-U05 · Estado del cupo

**Como** estudiante, **quiero** saber si mi cupo es definitivo, **para** no llevarme sorpresas. Resuelve D5, P7 y P10.

- Durante el período, la tarjeta y "Mis inscripciones" dicen "Inscrito · puede cambiar hasta el \[fecha de cierre\]".
- Al cierre, el estado pasa a "Confirmado".
- El correo de confirmación incluye la misma advertencia y la fecha de cierre.

### HU-U06 · Aviso al desplazado

**Como** estudiante desplazado, **quiero** enterarme en la app y saber qué hacer, **para** inscribirme en otro curso a tiempo. Resuelve D6, P6 y el hallazgo previo 3.

- Al entrar, un banner explica que perdió el cupo en un curso y que está en su lista de espera; queda visible hasta que lo cierra.
- El correo trae un enlace directo, los cursos con cupo y su puesto en la lista. Se elimina la frase sobre "próximas inscripciones".
- El apoderado ve el mismo aviso en su portal.

### HU-U07 · Rechazos con siguiente paso

**Como** estudiante, **quiero** que cada rechazo me diga qué hacer, **para** no quedarme sin opciones. Resuelve D7 y P26.

- Cada colegio registra un contacto de coordinación (correo o teléfono de UTP).
- Sin promedio y curso lleno: "Este curso está lleno y se asigna por promedio. Aún no tienes promedio registrado; pide a coordinación que lo cargue: \[contacto\]".
- Con promedio insuficiente: "Quedaste en la lista de espera, puesto N".

### HU-U08 · Horario y choques

**Como** estudiante, **quiero** ver mi horario y no inscribirme en cursos que chocan, **para** poder asistir a todos. Resuelve D8, P9 y P19.

- "Mis inscripciones" muestra día y hora de cada curso, además de una vista semanal.
- Inscribirse o postular a un curso que choca con otro se bloquea con el mensaje: "Este curso choca con \[curso\] el \[día\] a las \[hora\]".

### HU-U09 · Accesibilidad y pulido

**Como** estudiante con baja visión, **quiero** que todos los controles tengan nombre y funcionen con teclado, **para** usar el sistema sin ayuda. Resuelve D14, P12 y P45.

- Los botones de menú y de búsqueda tienen nombre accesible; el filtro de área tiene etiqueta y funciona con las flechas del teclado (verificado a mano).
- Hay un solo botón de cerrar sesión, en la barra lateral, y la interfaz no usa emojis.
- axe-core no reporta violaciones serias ni críticas en las pantallas de estudiante (comparado con la línea base de HU-R01).

## Entregas R2 y R3 · Asistencia, docentes, apoderados e IA

R2 mejora el trabajo diario de los profesores y el cálculo de asistencia; R3 completa el portal del apoderado y el asistente IA.

### HU-U10 · Cálculo de asistencia (R2)

**Como** profesor, **quiero** que el porcentaje de asistencia refleje la presencia real, **para** detectar a tiempo a los estudiantes en riesgo. Resuelve D9, P22, P23 y P27. **Validar con UTP** antes de publicar.

- Asistencia = (presentes + atrasos) / sesiones registradas. Las inasistencias justificadas se muestran aparte y no suman.
- El umbral es 85%, configurable por colegio, y se usa igual en el reporte docente, la vista del estudiante y el portal del apoderado.
- Test con el caso de Matías: 15 presentes, 3 ausentes y 2 justificadas en 20 sesiones da 75% y aparece bajo el umbral.
- Test con una alumna que solo tiene un atraso: su asistencia es 100%, no 0%.

### HU-U11 · Pasar lista (R2)

**Como** profesora, **quiero** registrar y corregir la asistencia rápido, **para** no perder tiempo de clase. Resuelve D10, P25 y P46.

- "Pasar lista" abre directamente el formulario de asistencia del día.
- Los alumnos aparecen ordenados por apellido.
- Un selector de fecha permite registrar o corregir sesiones de los últimos 14 días.
- Si la sesión ya estaba registrada, el formulario lo indica ("Estás editando la asistencia del \[fecha\]") y el mensaje final dice "actualizada".

### HU-U12 · Vista docente (R2)

**Como** profesor, **quiero** ver promedio y asistencia juntos, **para** identificar a los estudiantes en riesgo sin cambiar de página. Resuelve D11 y P20.

- La lista de alumnos muestra la asistencia junto al promedio y marca a quienes están bajo el umbral.
- La tabla se puede ordenar por columna y buscar por nombre o RUT.
- El reporte incluye el detalle por sesión: una columna por fecha, como el libro de clases.

### HU-U13 · Portal del apoderado (R3)

**Como** apoderada, **quiero** seguir la asistencia y las inscripciones de mi hijo y entender las reglas, **para** apoyarlo y saber a quién acudir. Resuelve D12, P18 y P37.

- Por cada pupilo: cursos con horario, asistencia con color según el umbral y detalle de sesiones desplegable.
- Aviso visible si el pupilo fue desplazado, con su puesto en la lista de espera.
- Una sección explica la regla de asignación por promedio y muestra el contacto de coordinación del colegio.
- Sin sesiones registradas se lee "Sin clases registradas aún", no "—".
- Funciona en celular de 360 px sin scroll horizontal.

### HU-U14 · Asistente IA y recomendaciones (R3)

**Como** estudiante indecisa, **quiero** usar el asistente y ver recomendaciones antes de inscribirme, **para** elegir con ayuda. Resuelve D13, P30 y P31.

- El botón del Asistente IA está disponible en el catálogo para todos los estudiantes, tengan o no inscripciones.
- Sin perfil de intereses, una tarjeta en el catálogo invita a completarlo ("Toma 1 minuto").
- Los cursos recomendados se marcan dentro de la grilla con su razón ("Coincide con tu interés en Ciencias").
- Al usar un perfil rápido ("Más creativo"), se avisa que reemplazará los ajustes manuales.

## Revisión de UI · línea base del 11 nov 2026

La línea base de HU-R01 (57 capturas y sus reportes de axe) muestra tres problemas de estructura que afectan a todas las pantallas, 291 violaciones de accesibilidad concentradas en 6 reglas, y confirma visualmente varios hallazgos del pre-test. Lo marcado "verificar a mano" puede ser un efecto de la captura y debe revisarse en un navegador antes de corregirlo.

### Problemas de estructura

| # | Problema | Dónde | Prioridad |
| --- | --- | --- | --- |
| UI-1 | El botón "Ingresar" no aparece en el login: hay un espacio en blanco entre la pista de contraseña y "o". Verificar a mano; si es real, solo se puede entrar con Enter | Login, celular y notebook | Alta |
| UI-2 | La barra lateral aparece en estados distintos: completa, como franja delgada o vacía en notebook, y abierta sobre el contenido, sin fondo oscuro, en celular. Verificar a mano; probablemente la captura ocurre durante la animación | Catálogo, Mis inscripciones, Asistencia, Perfil | Alta |
| UI-3 | El fondo y la barra lateral terminan a la altura de la ventana (768 o 900 px): al bajar, el fondo cambia de color. Por lo mismo, muchas capturas de página completa salieron cortadas. Probable `h-screen` en vez de `min-h-screen` en el layout | Todas las vistas de estudiante, profesor y administrador | Media |
| UI-4 | Un mismo administrador ve dos paneles con menús y estilos distintos: EasyAdmin (Cursos, Usuarios) y el panel propio (Panel Admin, Reportes, Inscripción masiva). La super admin ve el menú del administrador del colegio, sin opciones propias | Administración y super admin | Alta |
| UI-5 | En EasyAdmin, la página es más ancha que la pantalla (1458 px en 1440) y el zoom está bloqueado (`meta viewport`) | Usuarios y dashboard de EasyAdmin | Media |

### Accesibilidad (axe)

| Regla | Severidad | Páginas | Causa principal |
| --- | --- | --- | --- |
| Botones sin nombre | Crítica | 51 de 57 | Botón de modo oscuro (`#theme-toggle`), menú hamburguesa, lupa de búsqueda y menús de acciones |
| Listas desplegables sin nombre | Crítica | 21 | Filtro de área del catálogo, filtro de categoría del profesor y los dos campos de inscripción masiva (tienen texto visible, pero no está asociado) |
| Campos sin etiqueta | Crítica | 11 | 148 radios por página en el libro de asistencia, los 7 deslizadores del perfil de intereses y las casillas de EasyAdmin |
| Contraste insuficiente | Seria | 40 | Texto gris `#99a1af` sobre blanco (2,6:1, mínimo 4,5:1) justo en el horario de cada curso y en "Inscrito el"; títulos del menú de EasyAdmin (2,45:1) |
| Tablas con desplazamiento sin acceso por teclado | Seria | 2 | Lista de alumnos y reporte en celular |
| Sin región principal (`main`) y saltos de encabezado | Moderada | 57 | Falta `<main>`; las tarjetas pasan de h1 a h3 |

### Hallazgos por rol

**Estudiante**

- Cuatro de los siete cursos usan la misma imagen (Ciencias de la Salud, Física, Química y Biología), por lo que las tarjetas se confunden. En celular, el catálogo mide unos 3.000 px por la altura de las imágenes.
- Los cursos llenos se ven igual que los que tienen cupos; el "30/30" está en texto pequeño (confirma P4 y D3).
- Los botones de las tarjetas quedan a distintas alturas, porque no están fijos al pie de la tarjeta.
- Hay dos botones flotantes: "Mis intereses", en un morado que no aparece en otra parte, y el del asistente, que tapa el botón "Inscribirse" de Química. En celular el panel del asistente no ocupa toda la pantalla.
- La hora UTC se ve en la interfaz: "abiertas hasta el 11/12/2026 a las 05:00" e "Inscrito el 24/10/2026 05:02" (confirma P2).
- La asistencia de Matías aparece en verde con 85% (confirma P27). Hay emojis en horarios, títulos y el asistente (D14).
- Positivo: la barra de filtros es clara y el resumen de asistencia con etiquetas de color se entiende de un vistazo.

**Profesor**

- El libro de asistencia ordena por RUT y deja a Matías y Catalina al final (confirma D10). Positivo: todos vienen marcados como presentes y basta cambiar las excepciones.
- En celular, el reporte muestra 4 de 7 columnas, los nombres ocupan tres líneas y el RUT se corta en dos. En pantallas angostas conviene una tarjeta por alumno.
- Verificar: en el reporte, los estudiantes de relleno aparecen con 0 presentes en 20 sesiones.

**Apoderado**

- No se pudo evaluar: Josefa y Benjamín aparecen sin cursos porque la captura corrió antes de inscribirlos.

**Administración**

- En la tabla de cursos, la fecha límite aparece como "Nulo" y 93% de ocupación tiene el mismo rojo que 100%.
- En Reportes, el cupo máximo es una línea que une cursos distintos (debería ser una marca por curso), la carga por profesor tiene un eje con decimales (0,2 a 2,0) para contar cursos, y diez botones con el mismo peso y cuatro colores cierran la página.
- En el Panel IA, la tarjeta "Inscripciones totales" tiene como subtítulo "Cursos activos", y la actividad de señales está en cero (verificar si se registran).

### Historias nuevas

**HU-U15 · Layout y navegación consistentes (R1).** **Como** usuario de cualquier rol, **quiero** que la navegación se vea y funcione igual en todas las pantallas, **para** no perderme. Resuelve UI-1 a UI-5.

- El botón "Ingresar" es visible y tiene contraste suficiente en celular y notebook.
- En celular, la barra lateral empieza cerrada, se abre con el menú hamburguesa sobre un fondo oscuro y se cierra al tocar fuera o con Escape.
- En notebook, la barra lateral se ve igual en todas las pantallas del mismo rol.
- El fondo cubre toda la página al bajar (`min-h-screen`), y las capturas de página completa muestran todo el contenido.
- El administrador tiene un solo menú que reúne las secciones de EasyAdmin y del panel propio, con el mismo estilo; la super admin tiene su propio menú.
- Ninguna página tiene desplazamiento horizontal en 390 px ni en 1440 px, y el zoom no está bloqueado.

**HU-U16 · Sistema visual (R2).** **Como** estudiante, **quiero** distinguir los cursos y leer bien sus datos, **para** elegir sin esfuerzo. Resuelve los hallazgos visuales de esta revisión.

- Cada curso tiene una imagen distinta, o la tarjeta usa un color e ícono por área; en celular la imagen es más baja o el catálogo se muestra como lista.
- Los botones de las tarjetas quedan alineados al pie.
- Queda un solo botón flotante (el asistente), que no tapa contenido; "Mis intereses" pasa al menú o a la tarjeta de D13. En celular, el asistente ocupa toda la pantalla.
- La ocupación usa tres niveles de color con texto legible: con cupos, casi lleno (desde 80%) y lleno.
- En Reportes: ocupación como barras con una marca de cupo por curso, carga por profesor con eje de números enteros, y las herramientas como un menú, no como diez botones.
- Las fechas sin valor dicen "Sin fecha", no "Nulo". Se corrige el subtítulo de "Inscripciones totales".

**Cambios a HU-U09:** agregar como criterios que axe no reporte botones sin nombre, listas desplegables sin nombre ni campos sin etiqueta en ninguna página (incluidos radios de asistencia y deslizadores del perfil), que el texto secundario tenga contraste de al menos 4,5:1 (gris `#4b5563` o más oscuro sobre blanco), y que cada página tenga una región `<main>`.

**Cambios al script de HU-R01:**

- Desactivar animaciones y transiciones antes de capturar, y esperar a que la barra lateral termine de moverse.
- La captura del rechazo debe postular a un curso lleno sin promedio; hoy muestra "¡Inscripción exitosa!".
- Capturar al apoderado después de inscribir a sus pupilos, y quitar "dashboard-scroll", que es igual a "dashboard".
- Medir el desplazamiento horizontal automáticamente (`scrollWidth > clientWidth`) en vez de dejarlo como revisión manual.
- Agregar el dashboard del estudiante, el profesor P2 en tablet y el login con el botón visible.

## Orden de ejecución

El orden va de lo que protege datos a lo que mejora la experiencia, y cada entrega cierra con capturas y regresión.

| Paso | Historias | Cierre |
| --- | --- | --- |
| 1 · Línea base | HU-R01 | Zip de capturas y reporte axe para la revisión de UI |
| 2 · Seguridad | HU-S02, HU-S05, HU-S01, HU-S03, HU-S04, HU-S06, HU-S07 | Tests de aislamiento y acceso en verde |
| 3 · Entrega R1 | HU-U01 a HU-U09 y HU-U15 | HU-R01 de nuevo y regresión con las 12 personas |
| 4 · Entrega R2 | HU-U10 a HU-U12 y HU-U16 | HU-R01 de nuevo y regresión con P1, P2 y A1 |
| 5 · Entrega R3 | HU-U13 y HU-U14 | HU-R01 de nuevo y regresión con A1, A2, E3 y E4 |

HU-U01 y HU-U10 se pueden desarrollar, pero no se publican hasta que el usuario confirme la validación con UTP.
