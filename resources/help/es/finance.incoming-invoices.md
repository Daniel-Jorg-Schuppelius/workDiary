---
title: "Recepción de facturas electrónicas"
topic: finance.incoming-invoices
version: 1
audience: []
modules:
    - module.vertrieb
related:
    - invoices.manage
    - finance.datev-bookings
---

Esta área recibe facturas electrónicas entrantes, las verifica y las
conduce por un proceso de aprobación documentado — sin tocar la
soberanía de facturación del programa de contabilidad o facturación
rector.

**Entrada:** Las facturas electrónicas llegan por carga de archivo o a
través de la entrada de correo electrónico — como XRechnung (XML) o
ZUGFeRD/Factur-X (PDF con XML incrustado). Todos los canales pasan por
exactamente el mismo procesamiento. El documento se archiva en el DMS
como documento de tipo factura; el original inalterado sigue siendo la
única fuente, y la página de detalle lo vuelve a leer en cada acceso. En
este proceso no se crea ninguna factura local.

**Duplicados:** Un contenido de archivo idéntico se registra una sola
vez por organización — también entre canales (una carga tras una
recepción previa por correo sigue siendo un duplicado).

**Validación y consistencia:** Cada entrada se valida contra el esquema
XML y, si está configurado, contra las reglas de verificación KoSIT
(EN 16931); se indica de forma transparente si las comprobaciones
estuvieron disponibles. Además, la verificación de desviaciones advierte
de forma visible — nunca en silencio — cuando el número de factura del
mismo emisor ya está registrado, cuando los importes son contradictorios
(neto + impuesto ≠ bruto) y cuando hay desglose de impuestos sin
identificación fiscal del emisor.

**Propuestas:** Para la asignación, el sistema propone proveedores (por
NIF-IVA o similitud de nombre), pedidos (por la referencia de pedido) y
proyectos (por la referencia de proyecto/comprador) — como candidatos
fundamentados. La aceptación queda en manos del revisor; los datos
maestros nunca se crean ni se modifican automáticamente.

**Flujo de revisión:** Una entrada se aprueba, se marca con una consulta
o se rechaza (el rechazo solo con justificación). Solo tras la
aprobación técnica es posible la autorización de pago. Cada decisión se
audita con persona y momento.

**Traspaso a la contabilidad:** Solo se traspasan las entradas aprobadas
o con pago autorizado. El traspaso es idempotente — una segunda
invocación no cambia nada y no genera una constancia duplicada.

**Descarga de XML:** El XML de la factura puede extraerse en cualquier
momento de forma determinista a partir del original (en el caso de
ZUGFeRD, del adjunto del PDF). Cada descarga se registra con una suma de
verificación como constancia.
