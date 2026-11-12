---
title: "Reclamaciones y garantía"
topic: claims.overview
version: 4
keywords:
    - queja de cliente
    - reclamación de cliente
    - garantía legal
    - cortesía comercial
    - devolución
    - RMA
    - gestión de devoluciones
    - defecto de producto
    - denuncia de defectos
    - reclamación al proveedor
    - posventa
audience: []
modules:
    - module.claims
related:
    - documents.manage
---

El módulo gestiona reclamaciones, casos de garantía, decisiones de
cortesía comercial y devoluciones como expedientes trazables — desde la
entrada, pasando por la evaluación y la decisión, hasta las
consecuencias en almacén, servicio y facturación.

**Expediente:** Cada reclamación recibe su propio número (REK-…),
plazos, responsables y vínculos con encargo, proyecto, activo, artículo,
número de serie, factura y proveedor. Los módulos especializados siguen
siendo rectores — el expediente vincula, no sobrescribe nada.

**Vincular los objetos afectados:** En el diálogo «Nueva reclamación» y
mediante «Editar» en el expediente se eligen la factura, la orden, el
proyecto, el ticket de servicio, el activo, el acta y el proveedor, y con
el módulo de almacén también el artículo y el pedido. Con un cliente
seleccionado, las listas muestran solo sus objetos. Un número de serie
conocido en el almacén se vincula automáticamente al expediente. En la
factura, la orden, el activo y el ticket de servicio, «Abrir reclamación»
abre el diálogo con el objeto y el cliente ya rellenados.

**Evaluación y decisión:** Tipo de derecho (garantía comercial, garantía
legal o contractual, cortesía comercial, daño de transporte, uso
indebido, error del proveedor) con justificación obligatoria. La base
fáctica (verificación de números de serie, plazos, fecha de denuncia de
defectos según § 377 HGB en el caso B2B) se congela como snapshot. Las
decisiones requieren una evaluación activa y son auditables;
deliberadamente no existe una decisión automática sobre el derecho.

**Devoluciones (RMA):** Las devoluciones reciben un número RMA, la
entrada de mercancía queda en cuarentena (stock bloqueado/de
inspección) y la inspección documenta el dictamen y el cotejo de números
de serie. La decisión de destino (reincorporación al stock, reparación,
devolución al proveedor, desguace, eliminación) se contabiliza de forma
idempotente a través del diario de almacén.

**Referencia de almacén de la devolución:** El aviso y la entrada de
mercancía recogen la variante de artículo, la cantidad, el almacén, el
número de serie y el lote, rellenados a partir del artículo y del número
de serie del expediente. Con variante, almacén y cantidad, la entrada de
mercancía contabiliza las existencias en cuarentena; la decisión de uso
las traspasa o las da de baja. Sin variante o sin módulo de almacén, la
devolución solo se documenta y el mensaje indica «registrada sin asiento
de existencias». Las devoluciones del portal de clientes traen la variante
y el número de serie; el almacén se completa en la entrada de mercancía.

**Medidas y orden de seguimiento:** El retrabajo, la reparación, la
sustitución o la intervención se crean en «Medidas» con plazo y persona
responsable. Cada medida puede indicar su orden de seguimiento: una orden del
cliente del expediente que usted puede abrir. La intervención pasa por la
planificación y las rutas a través de esa orden; el expediente la enlaza, y
puede cambiarla o quitarla junto con el estado de la medida.

**Consecuencias comerciales:** La reducción de precio, el abono, la
anulación, la corrección, la factura sustitutiva o el reembolso se
proponen, se aprueban según el principio de los cuatro ojos y solo
entonces se ejecutan. Los documentos se generan en el módulo de
facturación (abono/anulación como borrador) con un indicador de motivo
estructurado — no existe un tipo de documento propio.

**Factura de la consecuencia:** En el diálogo «Proponer consecuencia» se
elige la factura, rellenada con la del expediente. Si usted emite las
facturas, toda consecuencia con documento necesita esta factura: la
anulación requiere una factura emitida e impagada; el abono, una pagada.
Si un sistema externo gestiona las facturas, el documento rectificativo se
crea allí y usted anota después su número.

**Regreso contra el proveedor:** Derecho propio frente al proveedor
inicial, con referencia al pedido y a la factura de entrada, plazo de
respuesta y retorno de costes.

El diálogo «Crear regreso» recoge el proveedor, el pedido, la factura
recibida, el artículo, el número de serie, las condiciones de garantía, el
importe reclamado y un plazo de respuesta propio en días. Sin indicación
se aplica el plazo estándar; corre desde el estado «presentado».

**Análisis:** El informe de calidad muestra la tasa, las causas, los
artículos afectados, los proveedores, los costes, la duración de
tramitación y los defectos recurrentes; los estados del informe pueden
congelarse como constancia.

**Portal de clientes:** Los clientes ven el estado de sus propios casos
y pueden remitir documentación adicional — las evaluaciones internas y
los importes permanecen invisibles.

**Lote en el expediente:** para artículos gestionados por lotes, elija el lote afectado en el diálogo de reclamación. Sin artículo elegido, el expediente toma el artículo del lote; la detección de patrones «mismo lote» evalúa el campo.

## Rechazar una propuesta

Quien aprueba las consecuencias comerciales también puede **rechazar** una propuesta. Termina sin documento y no se puede aprobar ni ejecutar; sigue siendo posible una nueva propuesta.
