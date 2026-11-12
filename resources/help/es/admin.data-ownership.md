---
title: "Soberanía de datos"
topic: admin.data-ownership
version: 3
keywords:
    - sistema maestro
    - sistema de referencia
    - fuente única de verdad
    - propiedad de los datos
    - sistema de facturación
    - Lexoffice
    - DATEV
    - evitar doble facturación
    - conflicto de sincronización
    - conflicto de soberanía
    - asumir una vez
audience:
    - admin
related:
    - admin.integration-inbox
    - admin.tenants
    - finance.transfers
---

Esta página define por organización qué sistema es el **rector** de
cada área de datos, de modo que nunca haya dos sistemas sobrescribiendo
los mismos datos uno contra otro.

**La matriz:** Para cada área de datos –tareas, tickets, existencias, calendario, documentos, clientes y proveedores– rige exactamente **un sistema principal**: WorkDiary (el valor predeterminado) o una integración activada. En cada área solo se ofrecen las integraciones que realmente pueden escribirla. Las **existencias** no se cambian aquí, sino en la conexión del sistema de inventario (p. ej., JTL-Wawi → gestión de existencias); la matriz muestra ese ajuste. La doble gestión está excluida estructuralmente.

**Efecto de la gestión:** Si gestiona WorkDiary, las importaciones de integraciones siguen permitidas como siempre. Si una integración gestiona un área, solo ella puede escribir allí. Si otra integración intenta escribir –crear o cerrar un ticket de Zammad, asumir una reserva de Calendly o una cita del calendario, contabilizar existencias iniciales de JTL-Wawi, importar una tarea–, no escribe nada. En su lugar aparece en la **bandeja de integraciones** un caso «Soberanía de datos»: **Asumir una vez** escribe el estado exactamente una vez, **Descartar** lo desecha y la misma operación no vuelve. Leer y mostrar siempre está permitido.

**Cambio de gestión:** Cada cambio se audita. Los conflictos abiertos permanecen en la bandeja al cambiar; la matriz indica su número por área. Si la nueva gestión permite la vía de escritura, la próxima sincronización cierra el caso sola; si no, asúmalo o descártelo allí.

**Colas de tickets gestionadas externamente:** Si Zammad importa tickets de servicio en una cola, Zammad gestiona esa cola. Allí solo se crean tickets nuevos mediante la importación; WorkDiary no acepta en esa cola la creación manual, la reserva de correos ni las respuestas por correo. El estado y la comunicación con el cliente se gestionan en el sistema principal.

**Documentos:** Si una integración lidera los documentos, solo ella crea documentos y nuevas versiones en el DMS a través de la entrada de documentos en la nube. Los archivos de otras conexiones en la nube y los documentos obtenidos de otros programas (por ejemplo, de easybill) esperan como caso «Soberanía de datos»; **Asumir una vez** vuelve a descargar el archivo y lo crea. Sus propias cargas, protocolos y documentos siguen siendo posibles en WorkDiary: el DMS es también el almacén de pruebas de WorkDiary.

**Clientes y proveedores:** Esta fila es también la soberanía de los datos maestros frente a la contabilidad. Si elige un programa de contabilidad (Lexoffice, sevDesk, easybill), este lidera los datos maestros: WorkDiary no transfiere contactos allí y la sincronización de contactos del programa aplica nombres, datos fiscales y direcciones. Los demás programas ya no modifican clientes ni proveedores, y la migración de Toggl no crea ninguno; sus estados esperan en la bandeja de integraciones. «Contabilidad (cualquier programa que escriba clientes)» permite escribir a todo programa conectado. Los cambios locales siguen siendo posibles, pero no se transfieren de vuelta y la siguiente sincronización los sobrescribe. Los números de cliente los sigue asignando la serie de numeración, no esta fila.

**Casos aplicados:** Si la integración quiere escribir más adelante un nuevo estado de la misma operación, como una tarea modificada o una nueva revisión de archivo, el caso aplicado se vuelve a abrir. Los casos descartados siguen descartados.

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
