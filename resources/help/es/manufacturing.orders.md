---
title: "Órdenes de fabricación"
topic: manufacturing.orders
version: 2
keywords:
    - orden de producción
    - lista de materiales
    - receta
    - necesidades de material
    - MRP
    - declaración de producción
    - rechazos
    - subcontratación
    - documentos aduaneros
    - factura proforma
    - factura comercial
    - albarán
    - etiqueta de envío
    - estado del envío
    - anular envío
audience: []
modules:
    - module.lager
related:
    - manufacturing.work-centers
    - procurement.orders
    - articles.master
    - inventory.stock
---

Las órdenes de fabricación representan la producción de un artículo a
partir de su lista de materiales o receta; solo son seleccionables los
artículos marcados como fabricables, y el sistema deriva la necesidad de
material de la cantidad objetivo, la variante y la lista de materiales.
Al liberar la orden se guarda una instantánea de la lista de materiales,
de modo que cambios posteriores no afectan a la orden en curso. El flujo
sigue una máquina de estados (borrador, liberada, en curso, en espera,
bloqueada, completada, anulada): el material se bloquea contra el stock
con **«Reservar»**, las notificaciones parciales registran cantidades
producidas, buenas, de desecho y de retrabajo, y con **«Entregar»** el
producto terminado se contabiliza como stock. Desde la página de detalle
la orden puede asignarse a un puesto de trabajo o subcontratarse a un
proveedor (genera un pedido); anular es irreversible y crear, notificar
y entregar requieren el permiso **Registrar movimientos de stock**.

## Envío en la entrega

Con una conexión de envío activa (consulte «Conexiones de envío DHL, UPS y
FedEx») crea en una entrega con cliente, mediante **Enviar**, una orden de envío
con su etiqueta. A continuación la entrega muestra el estado, por ejemplo
**Envío: Etiqueta creada**, con la empresa de paquetería y el número de
seguimiento. Al pasar el ratón sobre el estado ve cuándo se comprobó por última
vez con la empresa de paquetería. Junto al estado encontrará:

- **Descargar etiqueta**: vuelve a descargar la etiqueta de envío.
- **Consultar estado del envío**: consulta de inmediato la situación actual a
  la empresa de paquetería; ya no aparece con **Entregado** o **Cancelado**.
  Además, WorkDiary comprueba por sí mismo con regularidad los envíos abiertos.
- **Anular envío**: solo en el estado **Borrador** o **Etiqueta creada** y tras
  una confirmación. La etiqueta deja de ser válida. Después puede crear una
  nueva orden de envío, y los bultos de la entrega vuelven a ser editables.

Crear una orden de envío, consultar su estado y anular el envío requieren el
permiso **Registrar movimientos de stock**.

## Documentos aduaneros para envíos fuera de la UE

En cada entrega con destinatario, «Documentos aduaneros» crea una factura
comercial (en caso de venta) o una factura proforma (regalo, muestra,
devolución, reparación y otros motivos) en PDF. El diálogo muestra si el
destino está fuera de la UE y guarda el motivo elegido en la entrega. El
documento incluye la descripción de la mercancía, el código arancelario, el
país de origen, la cantidad, el peso neto y el valor; el peso bruto y el
número de bultos proceden de los bultos registrados. Mantenga el código
arancelario, el país de origen y el peso neto en el artículo, y el número
EORI del remitente en la configuración de la organización. Si falta algún
dato, el diálogo lo indica y no crea ningún documento. Los documentos
aduaneros no sustituyen una declaración de exportación electrónica.

La página **Albaranes** muestra todas las entregas del periodo elegido, con PDF
del albarán, envío por correo electrónico, documentos aduaneros y estado del
envío, sin pasar por cada orden de fabricación. El filtro «Sin envío» muestra lo
que aún espera una etiqueta.
