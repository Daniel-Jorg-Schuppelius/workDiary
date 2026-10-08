---
title: "Organización y configuración"
topic: admin.organization-settings
version: 1
keywords:
    - configuración de la empresa
    - ajustes del inquilino
    - ajustes de la organización
    - servicio de mapas
    - servicio meteorológico
    - calendario de festivos
    - festivos regionales
    - modo de mantenimiento
    - 2FA obligatoria
    - niveles de reclamación
    - geocodificación
    - cálculo de rutas
audience:
    - admin
related:
    - admin.tenants
    - admin.settings
    - admin.license
    - reports.arbzg-compliance
    - catalog.holidays
    - finance.dunning
    - invoices.manage
    - accounting.fixed-assets
    - account.ai-assistant
    - admin.notification-rules
    - dispatch.board
    - tours.manage
---

En el diálogo **Editar organización** usted gestiona los datos maestros de su
organización y todos los ajustes que se aplican a sus miembros: reglas de
jornada y etapas de aprobación, valores predeterminados para facturas y
reclamaciones de pago, servicios de mapas y de clima, la región de festivos y
el modo de mantenimiento. Lo abre desde el menú del sistema (el icono de
engranaje **Sistema** de la cabecera) en **Organización** → **Organización**.
La entrada está disponible para los administradores y abre siempre la propia
organización; la operación de la plataforma accede al mismo diálogo desde la
lista **Organizaciones**.

## Cómo funcionan los ajustes

- **Alcance:** cada valor se aplica a toda la organización. Cuando miembros,
  clientes, proyectos o ubicaciones pueden tener valores propios, la sección
  correspondiente lo indica; sus valores tienen entonces prioridad.
- **Valores predeterminados:** muchos campos están vacíos y muestran en gris
  «Predeterminado …», por ejemplo «Predeterminado 25». Un campo vacío adopta
  el valor predeterminado del sistema: un valor que el operador ha fijado en
  la configuración del sistema o, si no, el valor integrado que indica el
  texto gris. Un valor introducido solo se aplica a su organización y tiene
  prioridad sobre cualquier valor del sistema.
- **Restablecer:** si vacía un campo y guarda, su valor se elimina y vuelve a
  aplicarse el valor predeterminado.
- **Campos precargados:** las secciones sin texto gris, como los límites de
  horario de trabajo o las etapas de aprobación, muestran el valor vigente y
  lo vuelven a guardar la próxima vez que guarde.
- **Guardar:** **Guardar** registra todas las secciones y pestañas a la vez.
  Si un valor queda fuera del rango permitido, el diálogo lo indica en el
  campo y no guarda nada.
- **Eliminar y desactivar:** la desactivación, la exportación de datos y la
  eliminación definitiva de una organización están reservadas a la operación
  de la plataforma (vea «Organizaciones e inquilinos»); el diálogo no muestra
  botones para ello.

## Datos maestros

- **Nombre** (obligatorio, como máximo 255 caracteres): nombre de la
  organización. También sirve como nombre de empresa de la factura
  electrónica mientras allí no figure otro.
- **Idioma** (obligatorio): idioma de la interfaz para todos los miembros que
  no han elegido un idioma propio, e idioma de sus notificaciones y correos.
  Facturas, ofertas, reclamaciones de pago y albaranes aparecen en este idioma
  si el cliente no tiene un idioma de documentos propio.
- **Zona horaria** (obligatorio): zona horaria de visualización para los
  miembros sin zona propia. También determina los límites del día como «hoy»
  y el inicio de la semana.
- **Formato de fecha** y **Formato de hora**: valor predeterminado para todos
  los miembros que no han elegido un formato propio en su perfil. La lista
  muestra cada formato con un ejemplo; **— Predeterminado —** adopta el
  formato del sistema.
- **Fichajes olvidados**: cómo se completan los fichajes que faltan – **El
  empleado solicita – RR. HH. aprueba** (predeterminado) o **El empleado
  puede registrar por sí mismo**. Estos registros se marcan siempre como
  «manual» y permanecen visibles en la bandeja de corrección.

## Plan y estado

- **Plan**: tarifa de la organización – **Gratuito**, **Pro** o
  **Enterprise**. Aquí solo se muestra: los módulos habilitados se derivan de
  la licencia instalada y, al instalar una licencia, la organización adopta su
  plan. Solo la operación de la plataforma puede cambiarlo, porque pasar a un
  plan más pequeño inicia para los módulos que desaparecen un periodo de
  gracia de 30 días, tras el cual un proceso nocturno borra los datos de los
  módulos borrables.
- **Activo**: el bloqueo de la organización también lo cambia solo la
  operación de la plataforma. Los miembros de una organización bloqueada solo
  ven «Esta organización está desactivada. Póngase en contacto con el
  operador.»
- **Seguridad** – **Autenticación de dos factores obligatoria para todos los
  miembros**: quien aún no ha configurado un segundo factor es dirigido a la
  configuración tras iniciar sesión y solo puede seguir trabajando después;
  esto también se aplica a los accesos al portal de clientes. Mientras exista
  la obligación, el último factor no puede eliminarse y la autenticación de
  dos factores no puede desactivarse.

## Modo de cumplimiento

**Modo** determina con qué rigor reaccionan las comprobaciones de jornada
según la ley alemana de jornada laboral (ArbZG):

- **Desactivado**: ninguna comprobación – ni en la planificación de servicios
  y turnos ni en el análisis ArbZG de los tiempos registrados y sus casos por
  aclarar.
- **Advertir** (predeterminado): las infracciones se muestran, pero se guarda
  igualmente.
- **Bloquear**: un turno planificado con una infracción grave no puede
  guardarse, salvo que la persona que planifica anule la comprobación de forma
  deliberada en el diálogo del turno. Son graves el solapamiento, un descanso
  demasiado corto, la jornada diaria superada y un turno durante vacaciones
  aprobadas; las demás reglas solo advierten.

## Modelo de horario

**Tipo de horario predeterminado** es el modelo de horario de todos los
miembros que no tienen uno propio, así como la precarga de los modelos
nuevos: **Horario flexible** (predeterminado), **Horario semanal fijo**,
**Por día de la semana** u **Horario de confianza**. Un modelo propio de la
persona tiene prioridad.

## Límites de horario de trabajo

Los límites se aplican a la planificación de servicios y turnos y al análisis
ArbZG de los tiempos registrados:

- **Máx. horas/día** (1–24, predeterminado 10): jornada diaria sin pausas.
- **Descanso mín. (h)** (1–24, predeterminado 11): descanso entre dos días de
  trabajo o dos turnos.
- **Máx. horas/semana** (1–168, predeterminado 48).
- **Máx. días seguidos** (1–14, predeterminado 6): días de trabajo
  consecutivos en la planificación de turnos.
- **Horario nocturno desde (hora)** (20–23, predeterminado 23) y **Horario
  nocturno hasta (hora)** (4–7, predeterminado 6): ventana nocturna para
  comprobar el trabajo nocturno. Según la ArbZG va de las 23 a las 6 h; en
  panaderías y pastelerías, de las 22 a las 5 h.
- **Tolerancia de marco horario (min.)** (0–240, predeterminado 15): solo
  cuando los fichajes superan el marco horario del modelo de horario en más de
  estos minutos surge un caso por aclarar.
- **Semáforo de horario flexible: amarillo desde (min.)** (predeterminado
  1200, es decir, 20 horas) y **Semáforo de horario flexible: rojo desde
  (min.)** (predeterminado 2400, es decir, 40 horas): coloración del saldo de
  horario flexible en la cuenta de horas y en el panel. Las horas de más y de
  menos cuentan igual. Si el valor rojo es inferior al amarillo, el valor
  amarillo se aplica también al rojo.

## Aprobaciones

- **Etapas de aprobación de vacaciones**, **Etapas de aprobación de horas
  extra** y **Etapas de aprobación de correcciones de tiempo**: cada una
  **Una etapa (una aprobación)** (predeterminado) o **Dos etapas (principio de
  cuatro ojos)** – una solicitud necesita entonces dos aprobaciones.
- **Comercial: rol responsable**, **Técnica: rol responsable** y **RR. HH.:
  rol responsable**: las etapas de aprobación de una negociación contractual
  aparecen en la bandeja de aprobaciones en «Aprobaciones» para el rol
  asignado a su tipo de etapa. Se pueden elegir todos los roles salvo
  Cliente. Si queda vacío, se aplica el valor predeterminado:
  **Predeterminado (Contabilidad)**, **Predeterminado (Jefe de equipo)** o
  **Predeterminado (Administración de personal)**. La aprobación directamente
  desde el expediente sigue siendo posible.
- **Registrar de inmediato las ausencias solicitadas de forma provisional (el
  rechazo las retira)**: las ausencias solicitadas ya surten efecto en la
  planificación antes de la aprobación, marcadas como provisionales; un
  rechazo las retira. Solo se facturan y exportan las ausencias aprobadas.
- **Activar panel de presencia (ocupación actual)**: habilita la página
  **Ocupación actual** (desactivado de fábrica).

## Tiempos de conducción y descanso

**Aplicar las normas de tiempos de conducción (vehículos marcados «Aplicar las
normas de tiempos de conducción y descanso»)** (desactivado de fábrica)
comprueba los trayectos frente a los límites del Reglamento (CE) 561/2006 y
de la FPersV alemana: tiempo de conducción diario y semanal, pausas en la
conducción y descanso diario y semanal. Solo se comprueban los trayectos con
vehículos en los que también está marcado **Aplicar las normas de tiempos de
conducción y descanso** – ambos interruptores deben estar activados. No es
asesoramiento jurídico: qué normas se aplican en cada caso lo determina la
empresa.

## Reglas activas

Aquí usted desactiva comprobaciones concretas; de fábrica todas están
activadas. Una regla desactivada deja de comprobarse, y el modo
**Desactivado** las desactiva todas. Las ocho primeras reglas afectan a la
planificación de turnos:

- **Turnos solapados**: dos turnos de la misma persona se solapan.
- **Descanso mínimo**: el descanso entre dos turnos es demasiado corto.
- **Jornada diaria** y **Jornada semanal**: se supera el límite.
- **Días consecutivos**: más días de trabajo seguidos de lo permitido.
- **Conflicto de vacaciones**: el turno cae en vacaciones solicitadas o
  aprobadas.
- **Coincidencia de cualificación**: a la persona le falta una cualificación
  que exige la necesidad de personal del turno.
- **Reserva en día festivo**: el turno cae en un festivo gestionado en
  **Días festivos**.

Las cuatro últimas comprueban los fichajes y generan casos por aclarar en el
análisis ArbZG:

- **Fichaje de salida olvidado**: una presencia sigue abierta más allá del
  día.
- **Fichaje en día libre**: fichaje en un día libre según el modelo de
  horario o el plan de turnos.
- **Fichaje durante ausencia**: fichaje pese a una ausencia aprobada de día
  completo, como vacaciones o enfermedad.
- **Marco horario (fichajes)**: fichaje fuera del marco horario, por encima de
  la tolerancia.

## Configuración avanzada

La última sección agrupa otros valores predeterminados en pestañas:
**Listas**, **Facturación**, **Subidas de archivos**, **Límites de entrada**,
**Notificaciones**, **Interfaz**, **Enrutamiento y mapas**,
**Desplazamiento**, **Región y festivos**, **Clima** y **Mantenimiento**.
Además de los valores de facturación, la pestaña **Facturación** contiene
también las reclamaciones de pago, la factura electrónica, el inmovilizado,
el envío y la aduana, el pago en línea, los asistentes de IA, las condiciones
de alquiler, la flota, los patrones de reclamaciones, los problemas
recurrentes y la importación de tiempos. Para todas las pestañas rige la
indicación «Dejar vacío para usar el valor predeterminado del sistema.» Las
secciones siguientes siguen el orden de las pestañas.

## Listas

Cuántas entradas muestra una lista por página, cada una de 1 a 500: **Hojas
de horas**, **Planes de turnos**, **Clientes**, **Rutas**, **Vehículos**,
**Etiquetas**, **Organizaciones** (lista de la operación de la plataforma) y
las tres listas de la bandeja de mantenimiento remoto (**Bandeja de
mantenimiento remoto: equipos sin asignar**, **Bandeja de mantenimiento
remoto: equipos multicliente**, **Bandeja de mantenimiento remoto: sesiones
por tarjeta de equipo**). Los campos **Búsqueda de clientes (autocompletado)**,
**Adjuntos de cliente**, **Archivo** y **Panel: elementos recientes** no
tienen efecto por ahora; cuántas entradas recientes muestra el panel se fija
en la pestaña **Interfaz**.

## Facturación

- **Tipo impositivo predeterminado (%)**: si queda vacío, workDiary determina
  el tipo de las facturas nacionales a partir de las reglas fiscales. Un tipo
  introducido se aplica a todas las facturas nacionales creadas localmente y
  tiene prioridad sobre las reglas fiscales.
- **Moneda predeterminada (ISO-4217)**: moneda predeterminada de la
  organización; los importes de un documento permanecen en la moneda del
  cliente.
- **Unidad de tiempo para las posiciones** (hasta 8 caracteres,
  predeterminado h): unidad de las posiciones de tiempo en el traspaso.
- **Servicio estándar (artículo)**: aporta denominación, unidad, texto
  estándar y – si no se encuentra ninguna tarifa – el precio de las posiciones
  del traspaso. Las reglas de facturación del proyecto tienen prioridad. Sin
  artículos en el maestro de artículos, la lista queda vacía.
- **Plantilla: texto de introducción del traspaso** y **Plantilla:
  observación final del traspaso** (hasta 2000 caracteres cada una): se
  copian en el justificante al crear un traspaso y allí son editables.
  Marcadores: :customer, :from, :to, :channel. Sin plantilla para la
  observación final se aplica el texto de factura del cliente.
- **Tarifa horaria estándar (ingreso)**: se aplica cuando ni la entrada, ni
  la condición de cliente, ni el empleado, ni la actividad, ni el proyecto, ni
  el cliente fijan una tarifa. Si queda vacía, esos tiempos quedan en 0,00 €.
- **Tarifa de cálculo de montaje**: valora el tiempo de montaje de un
  artículo en la propuesta de precio de venta; si queda vacía, se aplica la
  tarifa horaria estándar.
- **Incremento de facturación predeterminado (minutos)** (1–1440): redondea
  hacia arriba el tiempo facturable a este incremento cuando ni el proyecto ni
  el cliente fijan uno; vacío = al minuto.
- **Intervalo de agrupación predeterminado (minutos)** (0–1440): las entradas
  separadas como máximo por este intervalo se agrupan en un bloque al
  facturar; vacío = sin agrupación.
- **Canal de facturación**: canal de facturación predeterminado de la
  organización, por ejemplo **WorkDiary (local)** o **Lexoffice dirige**; los
  clientes pueden sustituirlo individualmente. Si queda vacío, se aplica **—
  WorkDiary (predeterminado) —**. El campo solo aparece con el permiso
  «Gestionar la configuración financiera».

Cómo se crean las facturas se describe en el tema «Facturas & documentos».

## Reclamaciones

Valores por defecto por nivel para la reclamación individual y la serie de
reclamaciones de pago; el procedimiento se describe en el tema «Reclamación
de pagos».

- Para cada uno de los niveles 1 a 3: **Nivel 1: carencia (días)** y así
  sucesivamente – en el nivel 1, los días de retraso antes de que el
  recordatorio de pago sea exigible; en los niveles 2 y 3, los días desde la
  última reclamación (predeterminado 7 cada uno); **Nivel 1: recargo (EUR)** y
  así sucesivamente (predeterminado 0,00); **Nivel 1: plazo de pago (días)** y
  así sucesivamente (predeterminado 14, 10 y 7 días).
- **Calcular intereses de demora**: **Tipo fijo** (predeterminado) o **Tipo
  básico + puntos porcentuales** – el tipo básico según el § 247 BGB se
  consulta cada mes al Bundesbank.
- **Recargo (puntos porcentuales)**: solo en el modo de tipo básico.
  Referencia según el § 288 BGB: 5 puntos frente a consumidores, 9 entre
  empresas; la cuantía la decide su empresa.
- **Interés de demora (% anual)**: solo con tipo fijo; 0 = sin indicación de
  intereses.

Los intereses de demora solo aparecen en la carta de reclamación; no se
contabilizan.

## Factura electrónica (XRechnung)

Datos del vendedor para la salida XRechnung (EN 16931) de las facturas
creadas localmente: **Nombre de la empresa** (vacío = nombre de la
organización), **Calle y número**, **Código postal**, **Ciudad**, **Código de
país (ISO 3166-1)**, **NIF-IVA**, **Número fiscal**, **Contacto: nombre**,
**Contacto: correo** (dirección válida), **Contacto: teléfono**, **IBAN**,
**BIC** y **Titular de la cuenta**.

Además, tres campos influyen en todas las facturas creadas localmente:

- **Código de país (ISO 3166-1)** (dos letras, predeterminado DE): país del
  vendedor. El cálculo del impuesto lo utiliza para distinguir facturas
  nacionales, de la UE y de fuera de la UE.
- **Plazo de pago (días)** (0–365): se aplica cuando ni la factura ni el
  cliente tienen plazo de pago; vacío o 0 = 14 días.
- **Pequeña empresa (§ 19 UStG)**: las facturas no muestran IVA y llevan la
  nota «Sin IVA conforme al § 19 UStG (régimen de pequeñas empresas).»; la
  XRechnung recibe la categoría fiscal E (exenta).

## Inmovilizado: bienes de escaso valor y fondo colectivo

Límites de valor (netos) para el registro de inmovilizado. Los valores por
defecto siguen el § 6 apdo. 2/2a EStG (a 2026); compruébelos si cambia la
ley.

- **Límite bienes de escaso valor** (predeterminado 800): límite para la
  amortización inmediata de los bienes de escaso valor.
- **Fondo desde (más de)** (predeterminado 250), **Fondo hasta**
  (predeterminado 1000) y **Años del fondo** (1–20, predeterminado 5).
- **Subida de precios para la previsión de reposición (% anual)** (0–50,
  predeterminado 0).

Los detalles figuran en el tema «Registro de inmovilizado y amortización».

## Envío y aduana

**Número EORI**: número aduanero de la empresa (código de país y hasta 15
caracteres, p. ej. DE1234567). Figura como dato del remitente en las facturas
comerciales y proforma de envíos fuera de la UE.

## Pago en línea

- **Proveedor de pagos**: solo es necesario si hay varios proveedores
  activos; predeterminado **Automático (primer proveedor activo)**. Los
  proveedores (Stripe, Mollie o SumUp) se activan como plugin con sus propias
  credenciales.
- **Enlace de pago en la factura y en el correo** (activado de fábrica): el
  enlace de pago y el código QR aparecen en la factura y en el correo. Si se
  desactiva, el pago en línea sigue disponible en el portal de clientes.

## Asistentes de IA (MCP)

**Permitir asistentes de IA mediante MCP** (desactivado de fábrica): los
asistentes de IA como Claude o ChatGPT pueden conectarse con el
consentimiento de cada usuario y leer o crear borradores con sus permisos.
Desactivado, no es posible una nueva conexión; las existentes no obtienen
herramientas y no pueden renovarse. La conexión en sí se describe en
«Conectar asistente de IA».

## Condiciones de alquiler de equipos

- **Entrega solo con condiciones de alquiler firmadas**: las condiciones de
  alquiler se gestionan como acuerdo con el cliente «Condiciones de alquiler
  (alquiler de equipos)» con versión y firma.
- **Permitir la reserva directa en el portal de clientes**: los clientes
  reservan de inmediato y en firme los equipos libres habilitados para el
  portal; se notifica a la dirección.
- **Radio alrededor del lugar de uso (m)** (50–50 000, predeterminado 500): si
  la posición notificada de un equipo alquilado está más lejos de la
  ubicación del alquiler, se notifica la desviación. Sin ubicación se aplican
  las geocercas del cliente.

## Flota

**Ningún trayecto nuevo si una inspección obligatoria está vencida**
(desactivado de fábrica): si la ITV, la revisión de prevención u otra
inspección obligatoria del activo asignado está vencida o bloqueada, no se
puede registrar ningún trayecto a partir de hoy. Los trayectos pasados siguen
pudiéndose documentar.

## Patrones de reclamaciones

A partir de cuántas reclamaciones similares aparece una indicación – mismo
lote, mismo artículo con el mismo tipo de defecto o causa, o mismo
proveedor: **Umbral (casos)** (2–50, predeterminado 3) dentro de la
**Ventana (días)** (7–365, predeterminado 90).

## Problemas recurrentes

Esta alerta temprana detecta clientes y objetos para los que llega un número
llamativamente alto de tickets de soporte en el periodo elegido.

- **Tickets desde** (2–50, predeterminado 3): número mínimo de tickets para
  una alerta.
- **Ventana (días)** (7–365, predeterminado 90): periodo hasta hoy, medido
  según la fecha de notificación de los tickets.

Cómo cuenta workDiary:

- Cuentan todos los tickets con cliente, sea cual sea su estado. Los tickets
  con objeto cuentan por cliente y objeto; los tickets sin objeto, por
  cliente.
- Si un cliente o un objeto alcanza el umbral, se genera la alerta «Tickets
  recurrentes: …» con el número, el periodo, un enlace al objeto o al cliente
  y la recomendación de aclarar la causa con el cliente, revisar o sustituir
  el objeto y considerar una instrucción de trabajo o una formación. Se
  muestran como máximo los 20 casos con más tickets.
- Las alertas aparecen en **Análisis** → **Proyectos y clientes** →
  **Problemas y formación**, en el área **Problemas recurrentes** (para los
  administradores y las personas con el permiso «Ver los informes»), y en el
  mosaico **Incidencias destacadas** del panel.
- Además, la notificación «Alerta temprana de los informes» se envía una vez
  por cliente u objeto a los roles Jefe de equipo y Administrador. Los
  destinatarios y canales se cambian en **Reglas de notificación**.

## Importación de tiempos

**Asignar tiempos a proyectos por palabra clave** (activado de fábrica): se
aplica a los tiempos importados, por ejemplo desde la asistencia remota,
Toggl o Kimai. Si el texto de un tiempo importado contiene el nombre o una
palabra clave de un proyecto del mismo cliente, se registra allí en lugar del
proyecto estándar o de la bandeja de asignación. Solo se registran las
coincidencias inequívocas.

## Subidas de archivos

Límites de tamaño de las subidas en kilobytes (de 1 a 1 048 576 KB, es decir,
hasta 1 GB): **Importación CSV** (predeterminado 10 240 KB, 10 MB),
**Adjunto de cliente** (10 240 KB), **Adjuntos (general)** (25 600 KB,
25 MB) y **Datos de impresión** (262 144 KB, 256 MB). Los archivos más
grandes se rechazan al subirlos.

## Límites de entrada

Límites de caracteres y de rango para los campos de formulario, cada uno a
partir de 1:

- **Presencia**: **Nota, caracteres máx.** (predeterminado 1000), **ID de
  dispositivo, caracteres máx.** (64) y **Pausa, minutos máx.** (600).
- **Etiquetas**: **Nombre de etiqueta, caracteres máx.** (60).
- **Comentarios**: **Cuerpo del comentario, caracteres máx.** (5000).
- **Planes de turnos**: **Nota, caracteres máx.** (2000).

## Notificaciones

**Vista previa del mensaje, caracteres máx.** (20–500, predeterminado 120):
cuántos caracteres del texto del mensaje muestra una notificación push; el
resto se corta.

## Interfaz

- **Calendario** – **Duración de los espacios en minutos**: cuadrícula de la
  vista semanal; las citas sin fin reciben esta duración. Se permiten 10, 15,
  20, 30 o 60 (predeterminado 30).
- **Panel** – **Número de elementos recientes** (predeterminado 5): cuántas
  entradas usadas recientemente muestra el panel.
- **Búsqueda** – **Límite de resultados predeterminado** (predeterminado 20):
  por ahora no tiene efecto.

## Nominatim (geocodificación)

Nominatim es un servicio de geocodificación basado en OpenStreetMap:
convierte una dirección en coordenadas. workDiary lo utiliza en el **Libro de
viajes**: al salir del campo **Desde (dirección)** o **Hacia (dirección)**,
workDiary busca la dirección, y la dirección encontrada aparece como
información emergente en el campo. La dirección introducida se envía así al
servicio configurado.

- **URL base** (dirección completa, hasta 255 caracteres): dirección del
  servicio Nominatim. Si queda vacía, se aplica el valor del operador. Si una
  dirección propia está en una red interna, por ejemplo un servidor
  autoalojado, workDiary solo la consulta si el operador lo ha habilitado para
  su organización.
- **Correo de contacto**: se envía con cada solicitud. Las normas de uso de
  Nominatim exigen que la aplicación que consulta se identifique con una
  dirección de contacto.
- **Solicitudes por segundo** (1–50, predeterminado 1): workDiary espera en
  consecuencia entre dos solicitudes. El servicio público de Nominatim permite
  como máximo una solicitud por segundo; los valores más altos solo están
  pensados para un servidor propio.
- Los resultados se guardan en caché por dirección de servicio (365 días de
  fábrica); la misma dirección no se vuelve a consultar en ese tiempo.
- Si el servicio no está disponible o no encuentra nada, no aparece ninguna
  información emergente; la entrada en sí no se ve afectada.

## OSRM (enrutamiento)

OSRM calcula rutas sobre la red de carreteras. workDiary lo utiliza

- al optimizar una ruta: orden de las paradas según las distancias reales por
  carretera, trazado del recorrido en el mapa y distancia y tiempo de
  conducción previstos, a los que se suman los tiempos de estancia en las
  paradas;
- en las **Sugerencias de tiempos muertos** del **Centro de control**: tiempo
  de conducción adicional de ida y vuelta para un pedido que cabría en un
  hueco libre.

Si OSRM no está disponible, workDiary continúa con la distancia en línea
recta: las rutas se pueden seguir planificando, solo que sin trazado, y las
sugerencias llevan la indicación **estimación aproximada (línea recta)**.

- **URL base** (dirección completa, hasta 255 caracteres): dirección del
  servidor OSRM. Para una dirección propia en una red interna rige la misma
  habilitación por parte del operador que para Nominatim.
- **Perfil (p. ej. driving)** (hasta 32 caracteres, predeterminado driving):
  perfil de desplazamiento del servidor. Qué perfiles existen, por ejemplo
  para bicicleta o a pie, lo determina el servidor OSRM.
- **Tiempo de espera (segundos)** (1–120, predeterminado 10): cuánto espera
  workDiary una respuesta antes de recurrir a la línea recta.

## Teselas de mapa

Las teselas de mapa son los fragmentos de imagen que componen los mapas de
workDiary, por ejemplo en **Rutas**, en el mapa del **Centro de control** y
en la gestión de crisis. El navegador de cada usuario las carga directamente
desde el servidor de teselas configurado.

- **Plantilla de URL de tesela** (dirección completa, hasta 255 caracteres):
  dirección del servidor de teselas con los marcadores {z} para el nivel de
  zoom y {x} e {y} para la posición de la tesela. El valor predeterminado es
  el servidor de teselas de OpenStreetMap. workDiary permite automáticamente
  al navegador cargar imágenes de este servidor.
- **Zoom máximo** (1–22, predeterminado 19): ampliación máxima de los mapas.
  Elija como máximo el nivel que entrega el servidor de teselas.
- La atribución al pie del mapa la fija la configuración básica del operador;
  aquí no puede cambiarse.

## Facturación del desplazamiento

En la pestaña **Desplazamiento** usted decide si las facturas incluyen un
desplazamiento. Con **Calcular el desplazamiento automáticamente**
(desactivado de fábrica), workDiary añade a la facturación de proyecto o de
material de un cliente una posición de desplazamiento por cada ruta con
parada en ese cliente; la posición lleva la fecha de la ruta. Las rutas canceladas y los
desplazamientos ya facturados no cuentan.

- **Modo**: **Tarifa plana** o **Kilómetros**.
- **Texto de la posición** (hasta 50 caracteres, predeterminado «Anfahrt»):
  texto de la posición de factura, completado con la fecha o los kilómetros.
- **Tarifa plana (neto €)**: importe por desplazamiento en el modo **Tarifa plana**; sin
  importe no se genera ninguna posición.
- **Tarifa (€/km)**: precio por kilómetro en el modo **Kilómetros**.
- **Fuente de kilómetros**: **Siempre desde la ubicación de la empresa** –
  distancia en línea recta desde la ubicación de la empresa hasta la dirección
  del cliente – o **Según la ruta (km reales)** – los kilómetros del libro de
  viajes para ese cliente en ese día o, si no, la distancia prevista de la
  ruta.
- **Ida y vuelta (×2, solo ubicación de la empresa)**: duplica la distancia
  en línea recta.
- **Ubicación de la empresa latitud (lat)** y **Ubicación de la empresa
  longitud (lng)**: coordenadas de la ubicación de la empresa; si quedan
  vacías, se aplica el punto de partida de la ruta.

Si en el modo de kilómetros faltan las coordenadas del cliente, no se genera
ninguna posición. Para clientes concretos usted sustituye los valores en el
diálogo del cliente, en **Desplazamiento (anulación)**.

## Jurisdicción y festivos

**Región de festivos (país / estado)** determina qué festivos legales se
aplican a su organización. Puede elegir Alemania con **A nivel nacional (sin
festivos regionales)** y sus 16 estados federados, **Austria (nacional)**,
así como **Suiza (nacional)** y sus 26 cantones. Los festivos regionales como
el Corpus Christi o el Día de la Reforma solo se aplican en determinados
estados; por eso, elija la región de su empresa. Si queda vacío, se aplica el
valor predeterminado del sistema, que la primera entrada de la lista indica
como «Predeterminado …».

La región actúa en todos los lugares donde workDiary tiene en cuenta los
festivos, entre otros en:

- los recargos por festivos y las condiciones de cliente con regla de
  festivos;
- los días laborables de vacaciones y enfermedad, la cuenta de vacaciones y
  el objetivo de horario flexible;
- el análisis ArbZG, por ejemplo para el trabajo en festivos;
- las vistas calendario, vista semanal, plan de turnos, calendario de
  ausencias y **Ocupación actual**;
- los plazos SLA del soporte y los plazos de presentación de declaraciones
  fiscales, que pasan al siguiente día laborable;
- los precios de festivos en el alquiler de equipos.

Los festivos o días de descanso propios se gestionan en **Días festivos**; se
aplican además de la región. Una ubicación puede apartarse de ella en
**Ubicaciones**, en el campo **Región de festivos** (predeterminado **Regla de
festivos de la organización**); esto se aplica a los recargos de los tiempos
registrados allí.

## Consulta automática del clima

**Consultar el clima automáticamente al crear un protocolo** (desactivado de
fábrica): al crear un protocolo, workDiary obtiene en segundo plano una
instantánea del clima para el lugar y el momento del protocolo y la adjunta
como evidencia.

- El lugar lo dan las coordenadas de la ubicación a la que se refiere el
  protocolo o, si no, las del cliente – también a través del proyecto o del
  pedido del protocolo. Sin coordenadas no ocurre nada.
- Los proyectos pueden apartarse: en el proyecto, en **Consulta automática
  del clima**, usted elige activado, desactivado o **Heredar (ajuste de la
  organización)**. La elección se aplica también a los subproyectos.
- **Servicio meteorológico**: **Open-Meteo** (predeterminado) funciona en
  todo el mundo y sin registro. **Deutscher Wetterdienst (DWD)** proporciona
  datos oficiales de estaciones alemanas (licencia CC BY 4.0, atribución
  «Deutscher Wetterdienst»), solo para ubicaciones en Alemania con una
  estación al alcance.
- **DWD: distancia máxima a la estación (km)** (1–200, predeterminado 30): si
  no hay ninguna estación DWD activa dentro de esta distancia, no se crea
  ninguna instantánea – mejor ningún valor que uno erróneo.

## Avisos meteorológicos para la planificación

**Avisos meteorológicos para intervenciones planificadas** (activado de
fábrica): workDiary comprueba cada hora la previsión diaria de las
intervenciones de los próximos tres días, hoy incluido, y avisa cuando se
supera un umbral.

- Se comprueban los pedidos con fecha en ese periodo que están asignados a
  alguien o planificados y que no están ni terminados ni cancelados. Las
  coordenadas proceden de la dirección del pedido o, si no, del cliente; sin
  coordenadas no hay comprobación.
- Solo **Open-Meteo** proporciona previsiones. Si se ha elegido el DWD como
  **Servicio meteorológico**, no se generan avisos.
- Umbrales (vacío = predeterminado):
  - **Lluvia (mm/día)** – predeterminado 20; avisa a partir de este total
    diario.
  - **Rachas (km/h)** – predeterminado 60.
  - **Helada desde mínima de (°C)** – predeterminado 0; avisa cuando la
    mínima alcanza esta temperatura o baja de ella.
  - **Calor desde máxima de (°C)** – predeterminado 30.
- workDiary notifica cada superación exactamente una vez por intervención,
  día y umbral – de fábrica a la persona asignada y al rol Jefe de equipo.
  Los destinatarios y canales se fijan en **Reglas de notificación** para el
  evento «Aviso meteorológico para una intervención»; para este evento
  también es posible el SMS. Si el evento está desactivado allí, workDiary no
  consulta ninguna previsión.

## Modo de mantenimiento

En la pestaña **Mantenimiento** usted bloquea temporalmente workDiary para su
organización.

- **Activar el modo de mantenimiento**: todos los miembros que no son
  administradores ven una página de mantenimiento en lugar de la aplicación;
  iniciar y cerrar sesión sigue siendo posible. Los administradores siguen
  trabajando y ven arriba el aviso «Modo de mantenimiento activo — los no
  administradores ven actualmente una página de mantenimiento.» con el enlace
  **Configuración** para volver a este diálogo.
- **Mensaje mostrado en la página de mantenimiento** (hasta 300 caracteres).
- **Fin previsto** (opcional): después de este momento, el modo de
  mantenimiento termina automáticamente; el aviso lo muestra como «Hasta: …».
- **Pausar también las entradas de terminal/webhook** (desactivado de
  fábrica): sin esta marca, los terminales de fichaje y las entradas de
  telefonía y ubicación siguen funcionando durante el mantenimiento.

La activación y desactivación del modo de mantenimiento quedan registradas en
el registro de auditoría.
