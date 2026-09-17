---
title: "Resumen de la gestión de protección de datos"
topic: privacy.overview
version: 1
audience: []
modules:
    - module.datenschutz
related:
    - documents.manage
    - isms.overview
    - glossary.core
    - privacy.portal
---

El módulo de protección de datos cubre el registro de actividades de
tratamiento (art. 30 RGPD) con versiones inmutables tras la aprobación,
encargados y contratos (art. 28), solicitudes de interesados
(art. 15–21) con plazo de 30 días, medidas técnicas y organizativas y
brechas de seguridad con vista al plazo de 72 horas; la notificación a la autoridad y la comunicación a los
interesados (art. 34) se registran por separado. Los contenidos de
las solicitudes se guardan **cifrados** con una clave propia por caso y
no existe acceso automático para administradores: los derechos se
asignan expresamente. Tras el plazo de conservación, la clave del caso
puede destruirse (crypto-shredding) y el contenido queda
**irrecuperable**; las evidencias se gestionan en el módulo
**Documentos**.

**Conservación, borrado y retención legal:** En **Conservación y borrado**, el
concepto de borrado propone datos vencidos; nada se elimina ni anonimiza sin una
confirmación en dos pasos. Si hay un procedimiento en curso de un interesado o
judicial, estableces en **Retención legal** un bloqueo sobre la persona o el
cliente, con motivo obligatorio y referencia de expediente opcional. Mientras
esté activo no se generan propuestas de borrado, se rechazan los borrados
confirmados, la anonimización y la eliminación de cuentas o clientes, y se
conservan los puntos de ubicación de la persona. Al fusionar clientes, el bloqueo
pasa al cliente de destino. Solo se levanta con motivo; ambos quedan visibles en
el registro de la persona o del cliente.
