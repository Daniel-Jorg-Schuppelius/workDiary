---
title: "Soberanía de datos"
topic: admin.data-ownership
version: 1
audience:
    - admin
related:
    - admin.tenants
    - finance.transfers
---

Esta página define por organización qué sistema es el **rector** de
cada área de datos, de modo que nunca haya dos sistemas sobrescribiendo
los mismos datos uno contra otro.

**La matriz:** Para cada área de datos (p. ej. tareas, tickets,
existencias, calendario, documentos, clientes) rige exactamente **un
sistema rector**: o bien el propio WorkDiary («nativo», el estándar) o
una integración activada. La doble rectoría queda estructuralmente
excluida.

**Efecto de la rectoría:** Si WorkDiary es el rector, las importaciones
desde integraciones siguen permitidas como de costumbre a través de la
bandeja de entrada. Si una integración rige un área, solo ella puede
escribir en ella: los intentos de escritura de otras integraciones
acaban como conflicto en la bandeja de entrada en lugar de modificar
datos. Cada cambio de rectoría queda auditado.

**Soberanía de facturación:** Para la facturación rige el mismo
principio: exactamente un programa lleva las facturas — WorkDiary,
Lexoffice o DATEV. La vía de facturación se configura como **estándar
por organización** y puede sobrescribirse **por cliente**. Se aplica la
cascada: la configuración del cliente prevalece sobre el estándar de la
organización; sin ninguna de las dos, WorkDiary factura localmente.

**Consecuencias de la soberanía externa:** Si un programa externo lleva
la facturación de un cliente, la **creación local de facturas para ese
cliente queda bloqueada**. Los tiempos y materiales facturables se
envían en su lugar como **comprobante de traspaso** al programa rector:
los traspasos nacen primero como borrador, se confirman y solo con el
traspaso efectivo las partidas de origen se consumen como facturadas —
así nada puede facturarse dos veces. La asignación vinculante de
números de factura permanece por completo en el programa rector.

**Cambio en producción:** Un cambio de la vía de facturación solo
afecta a operaciones futuras; los documentos ya emitidos permanecen sin
cambios. Antes del cambio, aclare qué partidas abiertas deben cerrarse
todavía por la vía antigua.

**Recomendación:** Mantenga la matriz deliberadamente reducida:
transfiera la rectoría a una integración solo allí donde el sistema
externo sea realmente la fuente de datos determinante.
