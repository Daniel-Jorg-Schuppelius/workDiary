---
title: "Eliminación y justificantes"
topic: disposal.overview
version: 1
audience: []
modules:
    - module.entsorgung
related:
    - assets.fleet
    - customer-portal.overview
---

El expediente de eliminación gestiona la retirada de equipos antiguos como
un proceso trazable: recogida en el cliente, lista de equipos con códigos
de residuo (AVV/LER), tratamiento de soportes de datos según la norma
DIN 66399, entrega a la empresa gestora de residuos certificada con
justificantes y cierre con justificante del cliente en el portal.

**Cadena de estados:** Creado → Recogido → En tratamiento → Entregado al
gestor de residuos → Cerrado. El paso de tratamiento puede omitirse si no
hay soportes de datos implicados. La anulación es posible hasta el cierre;
es definitiva y se registra con motivo en la cadena de trazabilidad.

**Lista de equipos:** Cada posición recoge categoría, fabricante/modelo,
número de serie, cantidad, peso y el código de residuo (AVV/LER). La
clasificación «peligroso» se deriva automáticamente del asterisco del
código de residuo — nunca se establece a mano. Las posiciones solo pueden
modificarse hasta la entrega al gestor de residuos.

**Tratamiento de soportes de datos:** Para cada equipo que contiene
soportes de datos se documenta el tratamiento — tipo de soporte,
procedimiento (p. ej. borrado por software, desmagnetización, triturado o
extracción para su destrucción), categoría de material DIN 66399 con nivel
de seguridad, además del ejecutante y una referencia de justificante. La
categoría de material se rellena previamente según el tipo de soporte.

**Entrega al gestor de residuos:** Las entregas a la empresa gestora de
residuos certificada se registran con tipo de justificante (p. ej. albarán
de recepción, documento de acompañamiento, justificante de eliminación),
número de documento, fecha de entrega y referencia del certificado EfbV.
Un documento subido se archiva como documento DMS.

**Cierre:** La comprobación de cierre del expediente exige cuatro
condiciones — al menos una posición de equipo, la firma de recepción del
cliente, un tratamiento documentado por cada equipo con soportes de datos
y, para residuos peligrosos, un justificante del gestor de residuos. Al
cerrar, el justificante del cliente se genera como PDF, se publica en el
portal del cliente y los activos vinculados se dan de baja. El cierre y la
anulación requieren el permiso «Cerrar y anular expedientes de
eliminación».

**Informe:** El informe de eliminación evalúa los expedientes cerrados en
el periodo elegido — cantidades eliminadas por cliente, por mes y por
código de residuo (AVV/LER), cada una con la parte peligrosa.
