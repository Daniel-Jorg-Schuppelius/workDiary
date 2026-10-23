---
title: "Importación de Toggl"
topic: admin.toggl
version: 4
keywords:
    - Toggl Track
    - importar tiempos
    - importar registros de tiempo
    - CSV de Toggl
    - API de Toggl
    - exportación de workspace
    - migrar desde Toggl
    - asignaciones
    - mapeo de usuarios
    - informe detallado
    - nuevo Toggl
    - Toggl 2.0
    - Toggl Focus
    - sincronización en vivo
audience:
    - admin
related:
    - admin.plugins
    - admin.import
    - admin.openproject
---

La importación de Toggl trae los registros de tiempo de Toggl Track o del
nuevo Toggl a WorkDiary. De forma predeterminada solo lee; opcionalmente se pueden
reescribir correcciones y transferir tiempos registrados localmente
(ajustes del plugin). Hay dos vías: la **importación por API** (token de
API y rango de fechas) y la importación de archivos (informe detallado
CSV o archivo de exporte completo del workspace). Los
clientes/proyectos de Toggl sin asignación automática se acumulan en
la bandeja de entrada, donde los asigna a clientes/proyectos
existentes, crea nuevos o los descarta; los futuros imports usan las
asignaciones guardadas, que puede modificar o eliminar. Importar de
nuevo el mismo periodo puede generar duplicados si los datos de origen
cambiaron, y el descarte de entradas es definitivo.

Toggl Track o el nuevo Toggl:

- En **Producto Toggl**, en la configuración del plugin, elige de dónde
  proceden los tiempos: **Toggl Track** o el **nuevo Toggl** (antes Toggl
  2.0 o Toggl Focus). La lectura, la reescritura y la transferencia solo
  afectan al producto elegido.
- Para el nuevo Toggl registre su clave de API (empieza por `toggl_sk_`).
  El ID de la organización y del espacio de trabajo son opcionales; sin
  ellos, el plugin determina los espacios de trabajo a través de su cuenta
  de Toggl. Toggl solo permite una clave activa por usuario: crear una
  nueva invalida la anterior.
- Si utiliza la sincronización en vivo entre Track y el nuevo Toggl, elija
  igualmente una sola fuente: Toggl mantiene alineados ambos productos.
- Tras un cambio, la siguiente importación asigna las entradas ya
  importadas a la nueva fuente (mismo usuario, inicio y fin) en lugar de
  crearlas de nuevo; los casos abiertos de la bandeja de asignación se
  trasladan también. Esto se aplica al periodo de la consulta. Las
  entradas más antiguas conservan su vínculo actual y sus correcciones ya
  no se envían a Toggl. Si es necesario, importe una vez con un periodo
  más largo (`toggl:import --days=365`).
- La detección de eliminaciones solo tiene en cuenta las entradas de la
  fuente elegida.

Asignación de usuarios (MVP-509): cada entrada de Toggl se asigna al
usuario de WorkDiary correspondiente mediante el correo del usuario del
workspace: primero las asignaciones guardadas («Gestionar
asignaciones»), luego la igualdad de correo. Los usuarios de Toggl
desconocidos o no consultables nunca se contabilizan en silencio en el
usuario principal: quedan como caso abierto en la bandeja de
asignación, donde elige el usuario; la elección se recuerda. Solo en
el modo de usuario único activado expresamente (ajuste del plugin) se
contabilizan las entradas sin señal de usuario en el usuario
predeterminado. Las importaciones antiguas mal asignadas se reparan con
`toggl:repair-entry-users` (primero simulación, escribir con
`--apply`); los tiempos facturados o firmados nunca se modifican
automáticamente.

Para la importación única de workspace (carpeta/ZIP o API) elige
explícitamente la asignación de usuarios: asignar solo a usuarios
existentes (las entradas desconocidas quedan visiblemente sin
contabilizar y se listan por correo), crear los usuarios que falten por
correo, o modo de usuario único (todo al usuario predeterminado
configurado — indicado claramente en la vista previa y el resultado).
Las direcciones Toggl individuales pueden además asignarse
explícitamente; la importación es idempotente y puede simplemente
repetirse una vez mantenidas las asignaciones.

Webhook (opcional):

- Para recibir antes las nuevas entradas, la página de importación indica
  en la sección **Webhook (opcional)** la dirección para una suscripción de
  webhook de Toggl. Cree la suscripción para el espacio de trabajo en Toggl
  y guarde el secreto asignado en la configuración del plugin en **Secreto
  del webhook**; allí también debe figurar el **ID de espacio de trabajo**.
- El webhook solo inicia la misma importación que ejecuta la consulta
  horaria. Si falla, la consulta lo recupera.
- El nuevo Toggl no ofrece webhooks. Con la sincronización en vivo activa,
  una suscripción en Toggl Track puede adelantar igualmente la consulta;
  los datos se siguen leyendo del nuevo Toggl.
- El usuario predeterminado del modo de usuario único se elige en la
  configuración del plugin en **Imputar tiempos para el usuario**, de la
  lista de usuarios.
