# Personas sintéticas — ElectivoIA

Nov 4, 2026 · @Mauricio

## Propósito y uso

Estas 12 personas sintéticas sirven para el pre-test de usabilidad de ElectivoIA con agentes de IA en Playwright, antes de probar con usuarios reales. Cada una trae un perfil, una tarea concreta y una instrucción lista para dársela al agente como prompt de sistema.

Cómo usarlas:

1. Crea el colegio de pruebas (sección "Ambiente de prueba") con una cuenta por persona con su rol (`ROLE_STUDENT`, `ROLE_TEACHER`, etc.) y sus datos ficticios: promedio, cursos, pupilos.
2. Pasa la "Instrucción al agente" como prompt de sistema y las tareas como objetivo.
3. Graba cada sesión con video, trace de Playwright y un log JSON con los pasos y el razonamiento del agente.
4. Compara lo que hizo el agente con el "Criterio de éxito" de cada tarea.

Todos los datos son ficticios: nombres, RUT y notas inventados. No uses datos reales de estudiantes, que son menores de edad.

## Ambiente de prueba

Las pruebas corren en un colegio ficticio, de preferencia dentro de un staging separado en Dokploy. Si se usa la instancia actual con datos reales, aplican las restricciones de la tabla.

**Colegios**

- **Liceo Ficticio ElectivoIA:** las 12 personas y unos 280 estudiantes de relleno de 3M y 4M. Cursos preparados: "Física" con 29 de 30 cupos, "Química" lleno, "Cálculo" con cupos libres, "Ciencias de la Salud" lleno (P2) y "Comprensión Histórica del Presente" con 35 inscritos (P1).
- **Colegio Aislamiento:** colegio chico con 1 administrador, 1 profesor y 5 estudiantes, solo para la prueba de aislamiento de SA1.

**Datos**

- RUT ficticios con dígito verificador válido; nunca RUT reales.
- Correos con dominio `@electivoia.test`, que no existe.
- Claves: los 6 primeros dígitos del RUT, como funciona hoy el sistema. Se guardan en `staging/test-credentials.json`, incluido en `.gitignore`.
- Promedios según cada persona; E5 con `averageGrade` nulo. Asistencia de un mes con estados `present`, `absent` y `justified`.

| Regla | Staging separado | Instancia actual con datos reales |
| --- | --- | --- |
| Cargar seed | `app:usability:seed` | El mismo comando: solo crea los colegios de prueba, sin tocar el resto |
| Reset entre rondas | app:usability:reset | Comando que borra solo los `school_id` de prueba y recarga |
| Correos | Mailpit | Mailpit o dominio `.test` |
| Prueba de carga S5 | Sí, por la red local | No |
| Al terminar | Se puede conservar | Borrar ambos colegios y sus cuentas |

Acceso: proteger el dominio de staging con Cloudflare Access y permitir la IP del Mac en el WAF para que Bot Fight Mode no bloquee a Playwright.

## Resumen de personas

Las personas cubren los cinco roles y se eligieron para estresar el motor de priorización, el chatbot y los dispositivos de baja gama.

| ID | Persona | Rol | Perfil | Qué pone a prueba | Objetivos |
| --- | --- | --- | --- | --- | --- |
| E1 | Catalina Rojas, 17 | Estudiante | 4° medio, promedio 6,6, muy digital | Desplazar a otro inscrito en curso lleno | O1, O2 |
| E2 | Matías Fuentes, 16 | Estudiante | 3° medio, promedio 5,1, Android gama baja | Entender que fue desplazado y qué hacer | O1, O5 |
| E3 | Josefa Muñoz, 16 | Estudiante | 3° medio, promedio 5,8, indecisa | Chatbot y recomendaciones de IA | O3 |
| E4 | Tomás Araya, 16 | Estudiante | Baja visión, lector de pantalla y teclado | Accesibilidad del flujo de inscripción | O1, O2 |
| E5 | Valentina Díaz, 17 | Estudiante | Recién trasladada, sin notas en el sistema | Priorización sin promedio registrado | O1 |
| P1 | Patricia Vergara, 58 | Profesora | Historia, baja competencia digital | Registrar asistencia rápido | O5 |
| P2 | Diego Herrera, 31 | Profesor | Biología, usa tablet | Reportes y exportación | O5 |
| A1 | Rosa Contreras, 45 | Apoderada | Trabaja por turnos, solo celular | Ver asistencia de su hijo (E2) | O5 |
| A2 | Andrés Lagos, 50 | Apoderado | Dos pupilos, desconfía del mérito | Entender por qué su hija quedó fuera | O5 |
| AD1 | Carolina Pizarro, 42 | Administradora | Coordinadora UTP | Cupos, exportación y casos sin promedio | O1, O4 |
| AD2 | Felipe Navarro, 29 | Administrador | Encargado TI | Carga masiva de usuarios y roles | O1 |
| SA1 | Ignacia Torres, 34 | Super Admin | Equipo ElectivoIA | Alta de colegio y período de inscripción | — |

## Estudiantes

### E1 · Catalina Rojas, 17 años

- **Contexto:** 4° medio, promedio 6,6. Quiere estudiar Ingeniería y necesita "Cálculo" y "Física". Compite por cupos y conoce las reglas.
- **Dispositivo:** notebook con Chrome, conexión estable. Competencia digital alta.
- **Objetivos:** inscribirse en sus dos electivos el primer día, aunque estén llenos.
- **Frustraciones:** no saber si quedó "segura" o si la pueden desplazar a ella.
- **Tareas:**
  1. Inscribirse en "Cálculo", que tiene cupos libres. Éxito: inscrita en menos de 3 minutos.
  2. Postular a "Física", que está lleno. Éxito: entiende que entró por promedio y que otro estudiante quedó fuera.
  3. Revisar su estado final. Éxito: encuentra la lista de sus inscripciones sin ayuda.
- **Instrucción al agente:** "Eres Catalina, estudiante de 4° medio, rápida y segura con la tecnología. Vas directo al grano, no lees textos largos y usas el buscador si existe. Te importa confirmar que tu cupo es definitivo. Si algo no es claro, dilo en tu log como lo diría una adolescente impaciente."

### E2 · Matías Fuentes, 16 años

- **Contexto:** 3° medio, promedio 5,1. Se inscribió temprano en "Física" y será desplazado por E1. Su mamá es A1.
- **Dispositivo:** Android de gama baja, pantalla de 5,5", datos móviles con señal intermitente.
- **Objetivos:** saber en qué quedó inscrito y elegir otro electivo si lo sacan.
- **Frustraciones:** sentir que el sistema es injusto; notificaciones que no explican qué pasó.
- **Tareas:**
  1. Entrar después del desplazamiento y descubrir qué cambió. Éxito: entiende que fue desplazado y por qué. Hoy no hay aviso en la app, solo un correo, así que se espera que falle.
  2. Inscribirse en un electivo alternativo con cupo. Éxito: lo logra sin volver a la página de inicio más de una vez.
- **Instrucción al agente:** "Eres Matías, usas solo el celular y lees en diagonal. Te molestan los textos chicos y los botones pegados. Usa viewport 360x740 y red lenta. Si el sistema te saca de un curso, reacciona con molestia y busca qué hacer ahora."

### E3 · Josefa Muñoz, 16 años

- **Contexto:** 3° medio, promedio 5,8. No sabe qué estudiar después; le gustan el arte y la biología.
- **Dispositivo:** celular de gama media y a veces el computador de la casa.
- **Objetivos:** que alguien le ayude a decidir entre electivos que no conoce.
- **Frustraciones:** descripciones de cursos genéricas; respuestas del chatbot que no consideran sus gustos.
- **Tareas:**
  1. Pedir ayuda al chatbot para elegir dos electivos. Éxito: recibe al menos dos recomendaciones justificadas con sus intereses.
  2. Aceptar o rechazar las sugerencias. Éxito: inscribe al menos una recomendada (métrica O3).
  3. Preguntar al chatbot algo fuera de tema, por ejemplo el horario del casino. Éxito: el chatbot reconoce el límite y la redirige.
- **Instrucción al agente:** "Eres Josefa, indecisa y conversadora. Escribe al chatbot como adolescente chilena: frases cortas, sin tildes, con modismos ('weno', 'cachai'). Cambia de opinión una vez durante la conversación. Registra si las recomendaciones te convencen y por qué."

### E4 · Tomás Araya, 16 años

- **Contexto:** 3° medio, promedio 6,0. Tiene baja visión y usa lector de pantalla (NVDA) y zoom al 200%.
- **Dispositivo:** notebook con teclado, sin mouse.
- **Objetivos:** inscribirse de forma autónoma, sin pedir ayuda a un compañero.
- **Frustraciones:** botones sin etiqueta, modales que atrapan el foco, información que solo se muestra con colores.
- **Tareas:**
  1. Inscribirse en un electivo usando solo teclado. Éxito: completa el flujo sin usar clics.
  2. Leer cuántos cupos quedan en un curso. Éxito: el dato existe en texto, no solo como barra de color.
- **Instrucción al agente:** "Eres Tomás. Navega solo con Tab, Enter, Espacio y flechas; no uses clics por coordenadas. Antes de cada acción, lee el árbol de accesibilidad de la página. Anota cada elemento sin nombre accesible o que no se puede alcanzar con teclado."

### E5 · Valentina Díaz, 17 años

- **Contexto:** 4° medio, llegó trasladada de otro colegio en agosto. Sus notas aún no están cargadas en el sistema.
- **Dispositivo:** celular de gama alta.
- **Objetivos:** inscribirse en "Química", que está lleno, y entender si su promedio cuenta.
- **Frustraciones:** quedar en desventaja por un problema administrativo que no es suyo.
- **Tareas:**
  1. Postular a un curso lleno sin promedio registrado. Éxito: el mensaje de rechazo explica el motivo (sin promedio) y a quién acudir.
- **Instrucción al agente:** "Eres Valentina, nueva en el colegio y sin conocer a nadie. Desconfías de que el sistema te trate bien. Busca dónde se explica qué pasa con tu promedio y cómo contactar a coordinación."

## Profesores

### P1 · Patricia Vergara, 58 años

- **Contexto:** profesora de Historia con 30 años de experiencia. Imparte el electivo "Comprensión Histórica del Presente" a 35 estudiantes. Hoy pasa lista en papel.
- **Dispositivo:** notebook antiguo de la sala, Windows y Edge, a veces con proyector conectado.
- **Competencia digital:** baja. Usa correo y WhatsApp; se pierde con menús anidados.
- **Objetivos:** pasar asistencia en menos de 2 minutos al inicio de la clase.
- **Frustraciones:** sesiones que expiran, íconos sin texto, perder lo ingresado al recargar.
- **Tareas:**
  1. Registrar la asistencia de hoy marcando 3 ausentes y 1 atraso. Éxito: guardado en menos de 2 minutos y con confirmación visible.
  2. Cambiar un ausente a justificado. Éxito: lo encuentra y lo cambia sin crear un registro duplicado.
- **Instrucción al agente:** "Eres Patricia, profesora mayor y cuidadosa. Lees todo antes de hacer clic y te asusta borrar algo. Prefieres botones con texto. Si no ves una confirmación clara de que se guardó, vuelves a intentar. Anota cada momento de duda."

### P2 · Diego Herrera, 31 años

- **Contexto:** profesor de Biología. Imparte "Ciencias de la Salud", que siempre se llena y donde ocurren desplazamientos.
- **Dispositivo:** tablet iPad en horizontal y celular.
- **Competencia digital:** alta. Espera filtros, exportaciones y vistas resumidas.
- **Objetivos:** saber quién está en riesgo por inasistencia y quién fue desplazado.
- **Frustraciones:** reportes que no se pueden filtrar; no poder intervenir en el desplazamiento automático.
- **Tareas:**
  1. Ver el reporte de asistencia del mes y detectar a los estudiantes bajo 85%. Éxito: los identifica en menos de 1 minuto.
  2. Revisar los inscritos de su curso. Éxito: ve el promedio de cada uno e identifica a quién podrían desplazar.
  3. Exportar el reporte. Éxito: descarga un archivo legible en Excel.
- **Instrucción al agente:** "Eres Diego, profesor joven y eficiente. Usa viewport de tablet 1180x820 con gestos táctiles. Buscas atajos y filtros. Opina sobre si el desplazamiento automático por promedio te parece justo para tu curso."

## Apoderados

### A1 · Rosa Contreras, 45 años

- **Contexto:** mamá de Matías (E2). Trabaja por turnos en un supermercado y revisa el celular en los descansos.
- **Dispositivo:** Android de gama baja, plan de datos limitado.
- **Competencia digital:** baja. Usa WhatsApp y Facebook; no recuerda contraseñas.
- **Objetivos:** saber si su hijo está yendo a clases y en qué electivo quedó.
- **Frustraciones:** términos técnicos ("desplazado", "ROLE", "tenant"), olvidar la clave, pantallas que no caben en el celular.
- **Tareas:**
  1. Ingresar y ver la asistencia de Matías del último mes. Éxito: encuentra el porcentaje en menos de 2 minutos.
  2. Entender en qué electivo quedó Matías tras el desplazamiento. Éxito: lo explica con sus palabras.
  3. Ingresar sin recordar su clave. Éxito: la pantalla le indica que son los 6 primeros dígitos de su RUT o a quién pedir ayuda (no existe recuperación de contraseña).
- **Instrucción al agente:** "Eres Rosa, tienes 5 minutos de descanso y poca paciencia con la tecnología. Usa viewport 360x740 y red lenta. No entiendes palabras técnicas: si aparece una, anótala. Si te pide algo complicado, lo dejas para después."

### A2 · Andrés Lagos, 50 años

- **Contexto:** apoderado de dos pupilos: Josefa (E3) y un hijo en 4° medio. Ingeniero, crítico y exigente con el colegio.
- **Dispositivo:** notebook de trabajo y celular de gama alta.
- **Competencia digital:** alta.
- **Objetivos:** monitorear a ambos hijos desde una sola cuenta y entender las reglas de asignación.
- **Frustraciones:** reglas poco transparentes; tener que cambiar de cuenta por cada hijo.
- **Tareas:**
  1. Cambiar entre sus dos pupilos y comparar sus electivos. Éxito: lo hace sin cerrar sesión.
  2. Encontrar la regla de priorización por promedio. Éxito: la explica correctamente en su log.
  3. Buscar cómo reclamar si cree que hubo un error. Éxito: encuentra un canal de contacto.
- **Instrucción al agente:** "Eres Andrés, apoderado desconfiado que busca la letra chica. Lee las reglas y cuestiona si el criterio de mérito es justo. Si no encuentras cómo se calculó la asignación, escribe en tu log qué información te falta."

## Administradores y Super Admin

### AD1 · Carolina Pizarro, 42 años

- **Contexto:** coordinadora de la Unidad Técnico Pedagógica (UTP). Responsable del proceso de electivos de 3° y 4° medio, unos 280 estudiantes.
- **Dispositivo:** computador de escritorio con monitor de 24".
- **Competencia digital:** media-alta. Domina Excel; espera que todo se pueda exportar.
- **Objetivos:** abrir el período de inscripción a tiempo, controlar cupos y entregar reportes a dirección.
- **Frustraciones:** no ver el avance en tiempo real; no poder corregir casos excepcionales a mano.
- **Tareas:**
  1. Revisar las fechas del período de inscripción. Éxito: las encuentra y entiende que solo el Super Admin puede editarlas.
  2. Cambiar el cupo de un curso de 30 a 35. Éxito: queda guardado y entiende que los desplazados deben volver a postular, porque no hay lista de espera.
  3. Ver el porcentaje de estudiantes inscritos vía plataforma. Éxito: encuentra el dato (métrica O1).
  4. Exportar el listado de inscritos por curso a Excel. Éxito: archivo con RUT, nombre, curso y promedio.
  5. Resolver el caso de E5 sin promedio. Éxito: encuentra cómo cargarle el promedio (hoy solo por CSV).
- **Instrucción al agente:** "Eres Carolina, coordinadora con mucha carga y poco tiempo. Trabajas con varias pestañas abiertas. Antes de confirmar algo masivo, buscas una vista previa o un deshacer. Anota cualquier acción irreversible que no pida confirmación."

### AD2 · Felipe Navarro, 29 años

- **Contexto:** encargado de TI del colegio. Da de alta usuarios a inicio de año y atiende los "olvidé mi clave".
- **Dispositivo:** notebook con Linux y Firefox.
- **Competencia digital:** muy alta.
- **Objetivos:** cargar todos los usuarios en una sola operación y asignar roles sin errores.
- **Frustraciones:** cargas masivas que fallan sin decir qué fila tiene el error; duplicados.
- **Tareas:**
  1. Cargar un CSV de 50 estudiantes con 3 filas con errores (RUT inválido, RUT duplicado y grado 2M). Éxito: el sistema indica qué filas fallaron y por qué.
  2. Vincular un apoderado con dos pupilos. Éxito: A2 ve a ambos al ingresar.
  3. Cambiar el rol de un usuario de profesor a administrador. Éxito: el cambio aplica sin crear otra cuenta.
- **Instrucción al agente:** "Eres Felipe, técnico y escéptico. Prueba casos límite: archivos con errores, doble clic en guardar, volver atrás en el navegador. Revisa la consola y las respuestas de red, y anota cualquier error 4xx o 5xx."

### SA1 · Ignacia Torres, 34 años

- **Contexto:** Customer Success del equipo ElectivoIA. Da de alta colegios nuevos en la plataforma multitenencia.
- **Objetivos:** crear un colegio, su primer administrador y verificar que sus datos no se mezclen con otro colegio.
- **Tareas:**
  1. Crear el colegio "Liceo Ficticio de Maipú" y su administrador. Éxito: el administrador entra y no ve datos de otro colegio.
- **Instrucción al agente:** "Eres Ignacia, del equipo interno. Verifica el aislamiento entre colegios: intenta abrir con la cuenta del nuevo colegio URLs o IDs de otro colegio y anota si accedes a algo que no debieras."

La multitenencia ya funciona (una base de datos filtrada por school\_id), así que la prueba de aislamiento aplica hoy. SA1 tiene además una segunda tarea: fijar el período de inscripción del colegio y verificar que un estudiante lo vea.

## Escenarios cruzados

Estos escenarios encadenan varias personas en orden, porque el diferenciador de ElectivoIA solo se ve cuando las acciones de uno afectan a otro.

**S1 · Desplazamiento por promedio** (diferenciador clave)

1. Deja "Física" con 29 de 30 cupos ocupados. E2 (promedio 5,1) se inscribe y lo llena.
2. E1 (promedio 6,6) postula y desplaza a E2.
3. E2 entra y debe entender qué pasó y elegir otro curso. Verifica en Mailpit que llegó el correo de desplazamiento.
4. A1 revisa en qué quedó su hijo.
5. P2 o el profesor de Física revisa su lista actualizada.

Variante: repetir con dos estudiantes del mismo promedio. Según el código, el empate no desplaza.

**S2 · Ampliación de cupos**

1. AD1 sube el cupo de "Física" de 30 a 31.
2. Resultado esperado: E2 no recupera el cupo. Evaluar si descubre que puede volver a postular.

**S3 · Alta de usuarios y primer ingreso**

1. AD2 carga el CSV y vincula a A2 con sus dos pupilos.
2. A2 ingresa por primera vez y cambia entre pupilos.

**S4 · Asistencia de punta a punta**

1. P1 marca a E2 ausente.
2. A1 ve la inasistencia ese mismo día.

**S5 · Apertura del período** (métrica O4)

1. SA1 abre el período a una hora fija.
2. Lanza en paralelo muchas sesiones de estudiantes intentando inscribirse en los mismos cursos.
3. Registrar errores, tiempos de respuesta e inscripciones duplicadas o perdidas.

Para S5 no hacen falta agentes con LLM: scripts de Playwright simples son más baratos y generan más carga.

## Hallazgos previos (revisión del código)

La revisión del código ya muestra 8 brechas antes de correr agentes; las dos primeras son de seguridad y conviene corregirlas antes de exponer el staging por Cloudflare.

| # | Hallazgo | Impacto | Persona que lo evidencia |
| --- | --- | --- | --- |
| 1 | La clave son los 6 primeros dígitos del RUT, sin rate limiting ni CAPTCHA | Seguridad: quien conozca un RUT puede entrar | AD2 |
| 2 | Los fixtures usan RUT reales | Privacidad de datos de menores | — |
| 3 | El desplazado no recibe aviso en la app; el correo falla en silencio si cae el SMTP | El estudiante no sabe que perdió su cupo | E2, A1 |
| 4 | No hay lista de espera; al subir cupos nadie entra | Los desplazados deben volver a postular por su cuenta | E2, P2, AD1 |
| 5 | No hay recuperación de contraseña | Apoderados bloqueados dependen del colegio | A1 |
| 6 | Sin límite de electivos por estudiante ni por nivel | Un estudiante con buen promedio puede acaparar cupos | E1 |
| 7 | Asistencia guardada como texto libre, sin enum | Riesgo de estados inconsistentes en reportes | P1 |
| 8 | Corrección del 5 nov: el administrador también edita el período de inscripción (lo comprobó AD1), así que este hallazgo no aplica | El colegio depende del equipo ElectivoIA | AD1, SA1 |

Para el hallazgo 6, agrega a E1 una tarea extra: intentar inscribirse en 5 electivos. Para el seed, usa RUT ficticios con dígito verificador válido, y recuerda que `doctrine:fixtures:load` borra la base: córrelo solo en el contenedor de staging.

## Hallazgos del piloto (E1, 4 nov 2026)

Las corridas del 4 y 5 de noviembre (E1, el escenario S1 y dos lotes con las 8 personas restantes), revisadas en sus JSON, dejan 47 hallazgos. La revisión descartó dos bugs aparentes (Química no guardada y sesión sin cerrar), que eran acciones de los propios agentes. El desplazamiento funciona de punta a punta: E1 entró a Física, E2 salió, ambos correos llegaron a Mailpit y un reingreso con promedio insuficiente se bloquea con un mensaje claro. Catalina no entendió por qué pudo entrar a un curso lleno.

| # | Hallazgo | Tipo | Impacto | Prioridad | Estado |
| --- | --- | --- | --- | --- | --- |
| P1 | El contador muestra 0/N en todos los cursos (`array_column` en `StudentController`) | Bug, producción | Nadie ve si un curso está lleno ni si al inscribirse desplazará a alguien | Alta | Corregido (3ª corrida muestra 30/30) |
| P2 | Las fechas se muestran en UTC, en el correo y en la app ("inscrito 20:47" a las 17:47) | Bug | Si el período se evalúa en UTC, abre y cierra 3 horas desfasado | Alta | Confirmado en correo y app |
| P3 | El login muestra una pista de que la clave son los dígitos del RUT | Seguridad | Junto a la falta de rate limiting (hallazgo previo 1), cualquiera puede entrar a la cuenta de otro estudiante | Alta | Confirmado |
| P4 | En un curso lleno, el botón "Inscribirse" se ve igual que con cupos y no explica que se puede desplazar a alguien | UX | E1 y E2 no sabían que iban a desplazar; E2 desplazó a otro sin querer | Alta | Confirmado en S1 |
| P5 | Inscribirse no pide confirmación, pero dar de baja sí | UX | La acción con consecuencias para otro estudiante es la que no se confirma | Alta | Confirmado en S1 |
| P6 | El desplazado solo recibe un correo, sin aviso en la app. El correo explica la causa, pero no trae enlace ni alternativas y habla de "próximas inscripciones" con el período abierto | Contenido | E2: "¿próximas? ¡Si el período es ahora!" | Alta | Confirmado por E2 |
| P7 | La confirmación dice "registrada exitosamente" sin advertir que el cupo puede perderse | Contenido | Falsa seguridad y reclamos al ser desplazado | Media | Pendiente |
| P8 | Las etiquetas Área A, B y C no tienen leyenda | UX | Todos los perfiles preguntaron qué significan | Media | Confirmado en S1 |
| P9 | "Mis inscripciones" no muestra el horario de los cursos | UX | El reclamo más repetido por los estudiantes | Alta | Confirmado en S1 |
| P10 | El mensaje de éxito desaparece y la tarjeta no muestra un estado persistente | UX | Duda sobre si la inscripción quedó | Media | Mejoró: la tarjeta muestra "Inscrito" |
| P11 | Sin límite de electivos: E1 se inscribió en los 7 cursos, desplazando en los llenos (ver hallazgo previo 6) | Diseño de producto | Pocos estudiantes con buen promedio pueden acaparar cupos y sacar a muchos compañeros | Alta | Confirmado en S1 |
| P12 | Menores: el inicio es el catálogo y no el dashboard, hay dos botones de cerrar sesión y un emoji fuera de estilo | UX | Bajo | Baja | Pendiente |
| P13 | El mensaje de éxito nombra al desplazado: "Reemplazaste a Matías Fuentes" | Privacidad | Revela a un compañero quién perdió el cupo y que tiene menor promedio. E1: "¿Matías va a saber que yo lo saqué?". No agregar promedios ajenos al correo | Alta | Confirmado en S1 |
| P14 | Rechazar una inscripción en un curso lleno respondía HTTP 500 por una excepción no capturada en la consulta de desplazamiento (COALESCE) | Bug | Errores en logs y respuestas inconsistentes al estudiante. Corregido: ahora el rechazo redirige con un mensaje de error y la interfaz no muestra éxito | Media | Corregido |
| P15 | Asistencia de E2: el resumen marca 0 en todos los estados y el detalle muestra las 20 sesiones como "Justificado" | Bug | Causa: el seed guardó los estados en español y la vista espera inglés. Como el estado es texto libre (hallazgo previo 7) y la vista muestra cualquier valor desconocido como "Justificado", el error queda oculto | Alta | Corregido en 679d737 (seed y validación de estados) |
| P16 | Dar de baja después de desplazar no devuelve el cupo al desplazado: Química quedó en 29/30 y el estudiante sacado, fuera | Diseño de producto | Un estudiante puede desplazar a otros y luego retirarse, dejándolos sin cupo | Alta | Confirmado (E2) |
| P17 | La página de login se muestra con la sesión anterior activa | UX | El agente anterior no cerró sesión: es un artefacto de la prueba; el caso de dispositivo compartido sigue sin probarse | Baja | Artefacto |
| P18 | El portal del apoderado tiene una sola página, sin alertas, historial ni aviso de desplazamiento | Alcance | Rosa no puede seguir a su hijo ni saber que lo sacaron de Física; A2 no encontró porcentaje de asistencia, regla de priorización ni canal de contacto | Alta | Confirmado por A1 |
| P19 | No se detectan choques de horario al inscribirse | UX | Inscripciones incompatibles que se descubren tarde | Media | Confirmado por E1 |
| P20 | Vista docente: promedio y asistencia en páginas separadas, tabla sin orden ni filtro, sin marca para baja asistencia, y reporte solo con totales | UX | Diego no identifica rápido a los estudiantes en riesgo, su tarea principal | Media | Confirmado por P2 |
| P21 | Los avatares se piden a un servicio externo (`avatar.iran.liara.run`) con el RUT del estudiante en la URL, y fallan con error 502 | Privacidad | Se envían RUT de menores a un tercero, sin necesidad | Alta | Confirmado por P2 |
| P22 | El reporte docente recomienda un umbral de asistencia de 75%, pero la normativa chilena de promoción exige 85% | Dominio | Profesores y apoderados podrían no ver a tiempo a estudiantes en riesgo de repetir | Media | Por verificar |
| P23 | "Atraso" se guarda como `late` y el reporte lo muestra aparte, pero no suma al porcentaje de asistencia | Bug posible | Confirmado por P1: una alumna con solo un atraso aparece con 0/0/0 y 0% en rojo | Media | Por verificar |
| P24 | Catalina aparecía como "1M": el nivel estaba fijo en el código del seed | Bug o seed | Artefacto del seed, no del producto. Corregido en 679d737 | Media | Corregido |
| P25 | La asistencia solo se registra para hoy: sin fechas pasadas ni aviso de que ya se pasó lista | UX | No se pueden corregir sesiones pasadas; posibles duplicados | Media | Confirmado por P2 |
| P26 | El rechazo "No hay cupo y tu promedio no es suficiente" no orienta sobre qué hacer | Contenido | E2 no sabe qué alternativas tiene | Media | Confirmado (E2, T7) |
| P27 | El porcentaje de asistencia suma los justificados como presencia: E2 tiene 15 de 20 (75%) y el sistema muestra 85% | Dominio | Un estudiante en riesgo de repetir aparece justo en el umbral; en la normativa chilena, una inasistencia justificada sigue siendo inasistencia | Alta | Por validar con UTP |
| P28 | El Panel IA (/admin/ai) responde HTTP 500: usa JSON\_EXTRACT, una función de MySQL, en una app con PostgreSQL | Bug, producción | La coordinación no puede usar el panel de IA | Alta | Corregido y verificado (9 nov) |
| P29 | Importador CSV: en el lote A rechazaba los RUT con puntos (el formato en que el sistema los guarda) con un mismo mensaje para todo error; en el lote B da HTTP 500 con cualquier archivo. No hay plantilla descargable | Bug, regresión | La carga masiva de alumnos no funciona; riesgo de duplicados por formato de RUT | Alta | Fix desplegándose; por verificar |
| P30 | El Asistente IA solo aparecería después de la primera inscripción | Diseño de producto | En el lote B, E3 lo vio sin inscripciones tras completar su perfil: la condición real puede ser tener perfil o inscripción | Media | Por verificar en el código |
| P31 | Sin perfil de intereses no hay recomendaciones ni invitación a completarlo | UX | La mayoría de los estudiantes vería un catálogo genérico | Media | Confirmado por E4 y E5 |
| P32 | Las recomendaciones incluyen cursos llenos (Ciencias de la Salud 30/30) sin advertir que inscribirse desplazaría a alguien | UX | La IA puede llevar a desplazar a un compañero sin saberlo | Media | Confirmado por E3 |
| P33 | El botón "Suspender" un colegio no pide confirmación | UX | En el lote B, SA1 comprobó que sí pide confirmación | — | Descartado |
| P34 | Tres colegios de pruebas antiguas (Colegio Test Email, Colegio Test Email 3 y San Miguel) siguen en producción con inscripciones abiertas | Datos | Inflan los totales del panel global y se ven en demostraciones | Media | Confirmar antes de borrar |
| P35 | La inscripción masiva no respeta el cupo: Cálculo quedó en 85/30 y el resultado informó "0 sin cupo". También inscribe a estudiantes sin promedio | Bug, integridad | Sobrecupo y se salta el motor de priorización. Verificado el 9 nov: ya respeta el cupo (29 inscritos, 50 sin cupo); falta verificar que elija por promedio y excluya a quienes no tienen | Alta | Confirmado por AD1 |
| P36 | No hay restricción por nivel: una estudiante de 1M se inscribió en un electivo de 3° y 4° medio | Dominio | El plan diferenciado es solo para 3° y 4° medio | Media | Confirmado por E5 |
| P37 | Portal del apoderado: la asistencia sin datos aparece como "—" sin explicación, y muestra el horario que el estudiante no ve | UX | Confusión e inconsistencia entre portales | Baja | Confirmado por A2 |
| P38 | El Asistente IA informa los cupos con una diferencia de 1 | Bug menor | Información de cupos poco confiable en el chat | Baja | Confirmado por E3 |
| P39 | Dos usuarios con ROLE\_ADMIN ven paneles distintos (EasyAdmin completo y uno simple) | Por verificar | Permisos inconsistentes o roles mal cargados en el seed | Media | Por verificar |
| P40 | Ningún formulario del panel admin (EasyAdmin) se puede guardar: responde "El token CSRF no es válido" | Bug, producción | No se pueden editar cupos, crear inscripciones manuales ni cambiar roles. Causa probable: el JS de protección CSRF no se descarga (importmap con Webpack Encore) y falla el respaldo por encabezado Origin | Alta | Corregido: guarda cupos (9 nov); cambio de rol por verificar |
| P41 | Exportar Excel del reporte de asistencia docente da HTTP 500 | Bug, regresión | En el lote A la exportación funcionaba | Alta | Corregido y verificado (9 nov) |
| P42 | El chatbot no respondió el primer mensaje en 78 segundos y luego tardó 10 a 15; no hay aviso de error ni reintento, y el envío queda bloqueado | Bug y UX | El estudiante abandona el asistente. Mitigado el 9 nov: la interfaz ya no se bloquea, pero 1 de 3 mensajes siguió sin respuesta. Causa sin identificar | Alta | Confirmado por E3 |
| P43 | No existe forma de vincular un apoderado con su pupilo desde el panel admin | Alcance | Un colegio no puede habilitar el portal de apoderados sin ayuda técnica | Alta | Confirmado por AD2 |
| P44 | Crear un colegio con un correo de administrador ya existente da HTTP 500 | Bug | El super admin no sabe qué falló | Media | Confirmado por SA1 |
| P45 | Accesibilidad: el botón de menú y el de buscar no tienen nombre accesible, y el filtro de área no tiene etiqueta. La inscripción sí se completa con teclado y el mensaje de éxito se anuncia | Accesibilidad | Un lector de pantalla anuncia "botón" sin decir para qué sirve. Que el filtro no responda a las flechas falta comprobarlo a mano | Media | Confirmado por E4 |
| P46 | Asistencia: la lista se ordena por RUT y no por apellido, "Pasar lista" lleva a la lista de alumnos y no al formulario, y editar una sesión se ve igual que crearla | UX | Patricia tarda más en su tarea diaria y no sabe si está corrigiendo o creando | Media | Confirmado por P1 |
| P47 | Restos de las pruebas en producción: "Colegio Prueba Aislamiento" (ID 26, suspendido) con un admin de RUT 99999999-9, y Cálculo en 85/30 | Datos | El reset no los limpia; se suman a los colegios de P34 | Media | Pendiente de limpieza |
| P48 | El admin de un colegio que abre por ID un curso o usuario de otro colegio en el panel recibe HTTP 500 en vez de 403 | Seguridad | En lectura no se ven datos, pero falta probar la edición y las rutas propias de profesor y estudiante, que no pasan por EasyAdmin | Alta | Confirmado en lectura; resto por probar |

No son hallazgos del producto: "Profesor Relleno", las descripciones vacías y los nombres "Relleno 31.500.041" de los estudiantes de relleno (artefactos del seed) ni el mensaje "No puedes inscribirte sin promedio registrado" que vio E1, que es correcto para un estudiante sin promedio. Ese mensaje sí debe evaluarse con E5 (Valentina): hoy no le dice a quién acudir.

## Métricas y límites

El pre-test sintético puede medir parte de O1, O2, O3 y O4, pero no O5, que depende de personas reales.

| Objetivo | Qué registrar en el pre-test | Personas | ¿Válido con agentes? |
| --- | --- | --- | --- |
| O1 Digitalización | Tareas completadas sin ayuda / tareas intentadas | Todas | Parcial: muestra bloqueos, no adopción |
| O2 < 3 min por inscripción | Tiempo y número de pasos por inscripción | E1 a E5 | Solo pasos; el tiempo de un agente no es humano |
| O3 > 70% aceptación IA | Recomendaciones aceptadas / ofrecidas | E3, E1 | Parcial: valida coherencia, no preferencia real |
| O4 > 99% uptime | Errores, latencia y duplicados bajo carga | S5 | Sí |
| O5 > 4/5 satisfacción | — | — | No: requiere encuesta a usuarios reales |

En cada sesión registra además: errores de consola y red, elementos sin nombre accesible, términos que la persona no entendió y momentos en que volvió atrás.

Límites del enfoque:

- Los agentes no sienten frustración ni injusticia; el impacto emocional del desplazamiento debe validarse con estudiantes y apoderados reales.
- Los agentes son más persistentes y leen mejor que un adolescente promedio, así que los hallazgos son un piso, no un techo.
- Las respuestas del chatbot evaluadas por otro LLM tienden a parecer aceptables; revisa una muestra a mano.

## Instrucciones para Claude Code

Esta sección es para Claude Code y reemplaza las fases anteriores (actualizada el 9 nov 2026). Nada destructivo en producción sin confirmación del usuario.

**Estado actual**

- Seed y reset: `php bin/console app:usability:seed` y `php bin/console app:usability:reset`, que solo corren con `USABILITY_SEED_ENABLED=1`. Credenciales en `staging/test-credentials.json`.
- Un subagente por persona en `.claude/agents/`.
- Los hallazgos viven en las tablas "Hallazgos previos" y "Hallazgos del piloto" de este documento.

**Reglas para cada corrida**

1. Las tareas son las de este documento, palabra por palabra, con su criterio de éxito. No reemplazarlas por verificaciones ni por criterios de historias de usuario.
2. Cada persona empieza con el navegador limpio (sin sesión, cookies ni consola de la anterior) y cierra sesión al terminar.
3. Viewport según la persona: E2, A1 y A2 en 360x740; E3 y E5 en 390x844; E1, E4 y P1 en notebook 1366x768 (E4 solo con teclado); P2 en tablet 1180x820; AD1, AD2 y SA1 en 1440x900.
4. Prohibido restaurar o corregir el estado: si algo pasa sin querer, se anota y se sigue.
5. Solo AD2 puede usar `browser_evaluate`.
6. Antes de reportar un bug, verificarlo en la base de datos y descartar artefactos de la prueba: datos del seed, orden de ejecución o navegador compartido.
7. El JSON va en español, con: viewport, tareas, éxito o fracaso según el criterio, pasos, tiempo, dudas, frases de la persona, términos no entendidos y errores de consola y red.
8. Una persona a la vez.

**Orden entre personas**

- Reset antes de cada lote.
- S1 en orden: E2, E1, E2, A1, P2.
- E3 antes de A2, porque A2 revisa las inscripciones de Josefa.
- AD1 (inscripción masiva) y AD2 (importación CSV) al final y seguidos de un reset, porque alteran los datos de las demás personas.

**Limpieza (confirmada por el usuario el 9 nov)**

1. Respaldar la base de datos antes de borrar.
2. Listar todo lo que se borrará: colegios ID 1 (San Miguel), 3 (Colegio Test Email), 4 (Colegio Test Email 3) y 26 (Colegio Prueba Aislamiento), con sus usuarios (incluido el admin de RUT 99999999-9), cursos, inscripciones y asistencia.
3. Mostrar ese listado al usuario y borrar solo después de su confirmación.

**Próxima ronda: verificar los fixes**

- AD1: cambiar el cupo de Cálculo de 30 a 35 en el panel, y hacer la inscripción masiva de 4M en un curso con pocos cupos. Debe respetar el cupo, asignar por promedio y dejar fuera a quienes no tienen promedio (P35, P40).
- AD2: importar el CSV sin puntos, que debe mostrar los 3 errores por fila; comprobar que un RUT con y sin puntos no crea duplicados; cambiar un rol y buscar cómo vincular un apoderado (P29, P40, P43).
- P1: exportar el Excel del reporte de asistencia (P41).
- E3: tres mensajes al chatbot, registrando el tiempo de cada respuesta (P42).
- AD1: abrir `/admin/ai` (P28).
- Aislamiento (P48), con usuarios del Colegio Aislamiento contra IDs del Liceo: el admin abre y guarda `/admin/course/<id>/edit` y `/admin/user/<id>/edit`; el profesor abre `/teacher/report/<id>`, `/teacher/attendance/<id>` y la exportación de alumnos; el estudiante hace POST a `/student/enroll/<id>`. Todo debe responder 403 o 404 sin mostrar ni modificar datos.

Después de verificar los fixes, correr las 12 personas completas como prueba de regresión y actualizar las tablas de hallazgos.
