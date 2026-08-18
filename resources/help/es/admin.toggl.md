---
title: "Importación de Toggl"
topic: admin.toggl
version: 2
audience:
    - admin
related:
    - admin.plugins
    - admin.import
    - admin.openproject
---

La importación de Toggl trae los registros de tiempo de Toggl Track a
WorkDiary. De forma predeterminada solo lee; opcionalmente se pueden
reescribir correcciones y transferir tiempos registrados localmente
(ajustes del plugin). Hay dos vías: la **importación por API** (token de
API y rango de fechas) y la importación de archivos (informe detallado
CSV o archivo de exporte completo del workspace). Los
clientes/proyectos de Toggl sin asignación automática se acumulan en
la bandeja de entrada, donde los asignas a clientes/proyectos
existentes, creas nuevos o los descartas; los futuros imports usan las
asignaciones guardadas, que puedes modificar o eliminar. Importar de
nuevo el mismo periodo puede generar duplicados si los datos de origen
cambiaron, y el descarte de entradas es definitivo.

Asignación de usuarios (MVP-509): cada entrada de Toggl se asigna al
usuario de WorkDiary correspondiente mediante el correo del usuario del
workspace: primero las asignaciones guardadas («Gestionar
asignaciones»), luego la igualdad de correo. Los usuarios de Toggl
desconocidos o no consultables nunca se contabilizan en silencio en el
usuario principal: quedan como caso abierto en la bandeja de
asignación, donde eliges el usuario; la elección se recuerda. Solo en
el modo de usuario único activado expresamente (ajuste del plugin) se
contabilizan las entradas sin señal de usuario en el usuario
predeterminado. Las importaciones antiguas mal asignadas se reparan con
`toggl:repair-entry-users` (primero simulación, escribir con
`--apply`); los tiempos facturados o firmados nunca se modifican
automáticamente.

Para la importación única de workspace (carpeta/ZIP o API) eliges
explícitamente la asignación de usuarios: asignar solo a usuarios
existentes (las entradas desconocidas quedan visiblemente sin
contabilizar y se listan por correo), crear los usuarios que falten por
correo, o modo de usuario único (todo al usuario predeterminado
configurado — indicado claramente en la vista previa y el resultado).
Las direcciones Toggl individuales pueden además asignarse
explícitamente; la importación es idempotente y puede simplemente
repetirse una vez mantenidas las asignaciones.
