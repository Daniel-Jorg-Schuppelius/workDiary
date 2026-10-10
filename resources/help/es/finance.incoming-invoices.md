---
title: "Facturas recibidas"
topic: finance.incoming-invoices
version: 2
keywords:
    - factura de proveedor
    - factura de compra
    - facturas entrantes
    - buzón de facturas
    - facturas recibidas
    - recibir XRechnung
    - ZUGFeRD
    - Factur-X
    - validar factura electrónica
    - asignar factura
    - proveedor colectivo
    - cliente colectivo
    - documento no reconocido
    - aprobación de facturas
    - cuentas por pagar
    - liberación de pago
    - EN 16931
    - traspaso a Lexware
    - categoría contable
audience: []
modules:
    - module.vertrieb
related:
    - invoices.manage
    - finance.datev-bookings
---

**Facturas recibidas** (menú Facturación → Facturas recibidas) recibe
facturas, las asigna a proveedores o clientes y las lleva por la revisión y
la liberación de pago, sin afectar a la soberanía de facturación de su
programa de contabilidad o facturación principal.

**Canales de entrada:** Las facturas llegan por el buzón de facturas, la
carga de archivos, Peppol o el almacenamiento en la nube. Todos los canales
siguen el mismo proceso: control de duplicados, control de seguridad, lectura
de la factura electrónica o reconocimiento a partir de PDF o imagen,
validación y desviaciones. El original sin cambios se guarda como documento
de tipo factura en el DMS.

**Buzón de facturas:** En Administración → Recepción de correo, un buzón
pasa a formar parte de las facturas recibidas con el interruptor «Buzón de
facturas». Cada correo se evalúa por facturas, no por adjuntos:

- Una XRechnung (XML) es el original. Un PDF de la misma factura enviado con
  ella se adjunta como archivo complementario.
- Otros adjuntos, como condiciones generales o albaranes, pasan a ser
  archivos complementarios.
- Los logotipos incrustados y las imágenes de firma no se procesan.
- Si un correo no contiene ninguna factura reconocible, el adjunto pasa a ser
  un **documento no reconocido**: el original se conserva y usted introduce
  los valores.
- Los correos sin ningún adjunto de factura (por ejemplo, solo con un enlace
  de descarga) van a la bandeja de asignación de la Administración, marcados
  como buzón de facturas.

**Reconocimiento:** Una factura electrónica (XRechnung o ZUGFeRD/Factur-X)
aporta valores vinculantes. En PDF y foto, los valores se reconocen y son una
propuesta que usted comprueba con el original. Si una factura del tráfico B2B
nacional no es una factura electrónica, aparece un aviso: solo se admite de
forma transitoria, hasta finales de 2026 o, para emisores pequeños, hasta
finales de 2027. El aviso no bloquea nada; las facturas de pequeño importe de
hasta 250 € están exentas.

**Dirección:** Si usted es el comprador, se trata de un documento de entrada
con un proveedor como contraparte. Si es el vendedor —por ejemplo, con copias
de facturas de una tienda o caja, o con abonos en el procedimiento de
autofacturación—, se trata de un documento de salida con un cliente como
contraparte. Los documentos de salida nunca aparecen en propuestas de pago,
remesas de pago ni retenciones. Si una factura indica un comprador ajeno, la
comprobación avisa «no dirigida a nosotros»; también se avisa de la copia de
una factura propia ya registrada.

**Lista de trabajo:** Las pestañas «Por asignar» y «Por revisar» muestran
todos los documentos abiertos; «Todos», el período seleccionado. La entrada
del menú cuenta los documentos por asignar. Contabilidad recibe una
notificación por la mañana mientras quede algo por asignar.

**Asignación:** Un documento se asigna automáticamente solo si exactamente
una parte coincide con un identificador exacto del documento: NIF-IVA,
número fiscal o IBAN. Si coinciden varias, el documento queda por asignar y
la comprobación indica los candidatos. Los identificadores propios de su
organización nunca cuentan como coincidencia. Con «Asignar» usted elige:

- una parte existente (las sugerencias aparecen primero),
- el proveedor colectivo o el cliente colectivo,
- una parte nueva, rellenada a partir de los datos del documento,
- «No es una factura»: el documento se rechaza con un motivo.

Ahí también puede corregir la dirección. Con «Recordar remitente», el sistema
asigna los correos futuros de ese remitente a la misma parte, siempre que el
propio documento no indique otra parte. En documentos no reconocidos y
reconocidos, introduzca número, fecha e importes con «Introducir valores».
Asigne juntos en la lista varios documentos de la misma parte.

**Proveedor colectivo y cliente colectivo:** Para proveedores y clientes
ocasionales, cada organización tiene un contacto colectivo. El nombre de la
parte real permanece en el documento. Un contacto colectivo nunca se
transfiere a un sistema contable como contacto propio, no se puede fusionar,
no recibe acceso al portal ni factura propia. En la inversión del sujeto
pasivo (§ 13b), en operaciones intracomunitarias y con países terceros está
bloqueado: ahí se necesita un contacto de empresa real.

**Comprobación del IBAN:** Si el IBAN de la factura difiere de todos los datos
bancarios registrados del proveedor, la página lo indica y el pago requiere
una confirmación. Las facturas al proveedor colectivo requieren siempre esta
confirmación. Una factura nunca modifica los datos maestros.

**Duplicados:** Un contenido de archivo idéntico se registra una sola vez por
organización, también entre canales (una carga tras una entrada por correo
sigue siendo un duplicado).

**Validación y consistencia:** Cada factura electrónica se valida con el
esquema XML y, si está configurado, con las reglas KoSIT (EN 16931); se
indica si las comprobaciones estaban disponibles. Además, la comprobación de
desviaciones avisa de forma visible —nunca en silencio— de un número de
factura ya registrado del mismo emisor, de totales contradictorios (neto +
impuesto ≠ bruto) y de impuesto indicado sin identificación fiscal del
emisor.

**Flujo de revisión:** Un documento se aprueba, se somete a consulta o se
rechaza (el rechazo solo con motivo). La liberación de pago solo es posible
tras la aprobación. Cada decisión se registra con persona y momento.

**Traspaso a la contabilidad:** Si Lexware Office o DATEV Unternehmen online
está conectado y el traspaso está activado allí, un documento se traspasa en
cuanto se conoce su contraparte. La aprobación sigue siendo requisito del
pago, no del traspaso. Antes, el sistema comprueba: ningún documento no
reconocido sin valores, no rechazado, dirigido a nosotros, no es copia de una
factura propia, totales coherentes y ningún contacto colectivo en la inversión
del sujeto pasivo, operaciones intracomunitarias o países terceros.

- Lexware Office recibe el comprobante como «por revisar» con el original y,
  en una XRechnung, también con el PDF enviado. Si allí ya existe un
  comprobante con el mismo número para el mismo contacto, solo se vincula.
- Los importes solo se envían con una categoría contable —en el proveedor o
  cliente, o como predeterminada en la configuración del plugin—, en euros y
  con los tipos impositivos 0, 5, 7, 16 o 19 %. Si no, el comprobante se
  envía sin importes y contabilidad los completa en Lexware.
- DATEV Unternehmen online recibe el original como imagen del comprobante.
- Un comprobante creado allí ya no se puede eliminar por la interfaz.
- No envíe las mismas facturas también a la dirección de correo de
  comprobantes de Lexware: los comprobantes reconocidos allí no tienen número
  al principio y escapan a la comprobación de duplicados.

El estado por destino figura en la página de detalle; las pestañas «Traspaso
pendiente» y «Traspaso fallido» reúnen lo que está atascado. El sistema
reintenta los traspasos fallidos cada hora hasta cinco veces; «Reintentar» los
inicia en cualquier momento. Sin un sistema contable conectado, «Entregar a
contabilidad» registra el traspaso como prueba tras la aprobación; una segunda
llamada no cambia nada.

**Descarga de XML:** El XML de la factura puede extraerse del original en
cualquier momento (en ZUGFeRD, del adjunto PDF). Cada descarga se registra
con una suma de verificación como prueba.
