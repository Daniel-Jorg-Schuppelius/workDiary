---
title: "Registro de tiempo basado en ubicación"
topic: location.overview
version: 1
audience: []
related:
    - time-entries.start
    - attendance.manage
---

El registro de tiempo basado en ubicación propone automáticamente
registros de tiempo cuando un dispositivo entra en una ubicación de
cliente registrada y vuelve a salir de ella. Complementa el registro
manual — nunca se contabiliza automáticamente, sino solo tras una
confirmación expresa.

**Geocercas por ubicación de cliente:** Para cada ubicación de cliente
relevante se define un perímetro a partir de un punto central y un
radio. Solo dentro de estas zonas se generan estancias; los movimientos
fuera de ellas carecen de relevancia funcional.

**Fuentes de datos:** Las notificaciones de posición proceden, a
elección, de las apps OwnTracks o Traccar a través de un acceso personal
de dispositivo, directamente del navegador o, a posteriori, mediante la
importación de un archivo del historial de ubicaciones de Google. Cada
dispositivo se registra de forma deliberada, y el registro presupone el
consentimiento documentado de la persona afectada.

**De la señal a la propuesta:** Los puntos entrantes se condensan en
estancias: la entrada y la salida de una geocerca dan lugar a una visita
con inicio y fin. Las visitas finalizadas aparecen como propuestas en
una bandeja personal de revisión — con el cliente, en su caso el
proyecto y el período registrado.

**Revisar en lugar de automatizar:** Solo la confirmación de una
propuesta genera un registro de tiempo real; las propuestas
inadecuadas pueden descartarse. Entre la señal de ubicación y la
contabilización media así siempre una decisión consciente de la propia
persona afectada.

**Protección de datos:** Se evalúan los eventos de entrada y salida en
las ubicaciones de cliente registradas — no tiene lugar una vigilancia
permanente de la ubicación. Cada persona ve exclusivamente su propio
rastro de movimiento y sus propias propuestas; tampoco los
administradores tienen acceso a ellos. Los puntos de ubicación en bruto
se almacenan cifrados y se eliminan automáticamente al vencer un plazo
de conservación (por defecto 90 días). Los registros de tiempo
confirmados y los análisis derivados de ellos no se ven afectados —
solo desaparece el rastro en bruto, no el tiempo de trabajo.
