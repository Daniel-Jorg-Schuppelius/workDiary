---
title: "Facturas recibidas"
topic: finance.incoming-invoices
version: 3
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
    - entrada duplicada
    - factura duplicada
    - carpeta de facturas
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

**Buzón de facturas:** En Administración → Datos e interfaces → Recepción de
correo, abra el buzón con «Editar» (o cree uno nuevo) y active en el diálogo,
bajo «Uso», el interruptor «Buzón de facturas». La lista de buzones muestra
después la etiqueta «Facturas». Cada correo se evalúa por facturas, no por
adjuntos:

- Una XRechnung (XML) es el original. Un PDF de la misma factura enviado con
  ella se adjunta como archivo complementario.
- Otros adjuntos, como condiciones generales o albaranes, pasan a ser
  archivos complementarios.
- Los logotipos incrustados y las imágenes de firma no se procesan.
- Si un correo no contiene ninguna factura reconocible, el adjunto pasa a ser
  un **documento no reconocido**: el original se conserva y usted introduce
  los valores.
- Si al servidor le faltaba el reconocimiento de texto cuando llegó el correo,
  la acción masiva «Reconocer de nuevo» en la lista de documentos por asignar
  repite el reconocimiento de los documentos no reconocidos seleccionados a
  partir del original conservado. Los valores introducidos y los documentos
  transferidos o rechazados no cambian.
- Si el XML y la factura en PDF de la misma factura llegan en un correo, el
  XML sigue siendo el original y el PDF pasa a ser archivo complementario,
  aunque el XML lleve un número más largo. Si el XML no indica vendedor,
  NIF-IVA ni datos bancarios, el PDF aporta esos valores; la página de detalle
  nombra los campos completados.
- «Comprobar de nuevo» repite la comprobación de esquema y KoSIT de las
  facturas electrónicas sobre el original conservado, por ejemplo si el
  validador faltaba en la recepción. Los valores, la asignación y la
  transferencia no cambian.
- Los correos sin ningún adjunto de factura (por ejemplo, solo con un enlace
  de descarga) van a la bandeja de asignación de la Administración, marcados
  como buzón de facturas.
- Un correo reenviado como adjunto se abre; la factura que contiene cuenta
  como un adjunto directo.

**Consulta y correos existentes:** Los correos se consultan a partir del día
indicado en «Consultar desde», según su estado — incluidos los que alguien ya
ha leído. Elija preferiblemente una carpeta propia en la que una regla
clasifique las facturas (subcarpetas separadas con «/», p. ej. Bandeja de
entrada/Facturas). «Recuperar correos anteriores» en la administración de
buzones recupera facturas más antiguas; la misma factura no se crea dos veces.
Si un correo con factura está en otro buzón, «Pasar a factura recibida» en la
bandeja de asignación incorpora sus adjuntos.

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

Con «Tratar igual también los otros documentos abiertos» resuelve todos los
documentos abiertos de la misma contraparte en un paso, tanto al asignar como
al rechazar. En «No es una factura» decide qué ocurre con los futuros
documentos de esta contraparte: «archivar automáticamente como no es una
factura» o «no incorporar (descartar)». Descartar elimina el documento, su
archivo y los adjuntos; queda la entrada de auditoría con suma de
verificación, número, vendedor, remitente y motivo. WorkDiary crea para ello
una regla de bandeja de entrada.

Las **reglas de bandeja de entrada** son reglas de automatización del
desencadenante «Factura recibida». Las ve en «Reglas», en el menú de la
página, junto con las reglas de remitente; las desactiva, activa y elimina;
los administradores editan condiciones y acciones en la página de
automatizaciones. Las condiciones comprueban, entre otros, el vendedor
(`seller_name_key`, normalizado), el NIF-IVA (`seller_vat_id`), el remitente
(`sender_email`, `sender_domain`), el asunto (`mail_subject`), los nombres de
los adjuntos (`attachment_names`), el nombre de archivo (`file_name`), el país
(`country`), el importe bruto (`amount_gross`) y el reconocimiento
(`recognition`); los operadores incluyen «es», «contiene», «empieza por»,
«termina en» y «coincide con» (expresión regular). Acciones: descartar,
archivar como no es una factura, asignar a una parte. Gana la primera regla
que coincide; las reglas se aplican en la recogida, en la subida y en el
nuevo reconocimiento.

Los documentos rechazados figuran en la pestaña «Rechazado», donde puede
eliminarlos definitivamente. Tras el plazo configurado (30 días por defecto),
la conservación propone su destrucción; solo se eliminan tras la aprobación.

**Reconocimiento de PDF y escaneos:** WorkDiary lee etiquetas y valores
incluso cuando están en columnas separadas o en una fila de encabezado sobre
la fila de valores. Si falta la fecha de factura, cuenta «Lugar, fecha» del
membrete o una simple «Fecha»; una fecha de entrega solo se sugiere y se
señala en el diálogo «Introducir valores». Recordatorios de pago, albaranes,
confirmaciones de pedido, presupuestos y etiquetas de envío se reconocen como
tales: la lista muestra «Parece», el diálogo de asignación preselecciona «No es
una factura», y los recordatorios nombran las facturas afectadas con enlace.
Los administradores miden el reconocimiento con
`incoming-invoices:measure-recognition` sobre los textos guardados.

**Plantillas de documento:** Mediante «Plantilla» en la página del documento
traza sobre el documento renderizado rectángulos para número de factura,
fecha, importes, NIF-IVA y vendedor; el valor reconocido aparece al instante.
La plantilla se guarda con una huella del encabezado y del pie de la primera
página, de modo que coincide con cualquier otro documento del mismo proveedor,
a petición también con los abiertos. Cada corrección en «Introducir valores»
aprende: el valor introducido se localiza en el documento y se recuerda como
zona; tras la segunda coincidencia, la zona aprendida se considera confirmada
y prevalece sobre la heurística. Las plantillas figuran en «Reglas» y pueden
eliminarse allí. Las zonas solo existen para documentos PDF.

**Corregir asignaciones y acción masiva:** La acción masiva exige una
elección; «Nuevo a partir del documento (por contraparte)» crea una ficha por
contraparte a partir del primer documento o usa una parte existente con el
mismo NIF-IVA o nombre; el contacto colectivo va al final y pide confirmación
con los grupos de la selección. Elimine una asignación en el diálogo mediante
«Eliminar asignación» o como acción masiva mientras el documento no se haya
transferido; mover es volver a asignar, también en las pestañas «Por revisar»
y «Todos». En «Reglas», «Aparcado en el contacto colectivo» muestra los
documentos del proveedor o cliente colectivo por contraparte; «Crear y mover»
crea o usa la parte y mueve los documentos abiertos. Si hay un programa de
contabilidad conectado (Lexware Office, easybill, sevDesk, orgaMAX), el
diálogo busca allí por correo y nombre y ofrece las coincidencias como
«Adoptar de la contabilidad»: la ficha se crea a partir del contacto y se
vincula junto con el número de contacto. sevDesk no devuelve correo ni
dirección; los proveedores de orgaMAX no tienen NIF-IVA. Antes de «Nuevo a
partir del documento», el diálogo avisa de fichas similares; una parte
existente con el mismo NIF-IVA bloquea la creación. Si la contabilidad lleva
los datos maestros, el diálogo indica que la nueva ficha no se transfiere.

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

**Posible entrada duplicada:** Si la misma factura llega una segunda vez como
otro archivo —por ejemplo, como PDF por correo y como XRechnung por Peppol—,
la recepción de facturas lo reconoce por el número de factura junto con la
identificación fiscal del emisor o la parte asignada. La entrada posterior no
se entrega a la contabilidad hasta que usted confirme «No es duplicado» o la
rechace. Un número solo no basta: «1001» existe en muchos proveedores.

**Validación y consistencia:** Cada factura electrónica se valida con el
esquema XML y, si está configurado, con las reglas KoSIT (EN 16931); se indica
si las comprobaciones estaban disponibles. Además, la comprobación de
desviaciones avisa de forma visible —nunca en silencio— de totales
contradictorios (neto + impuesto ≠ bruto) y de impuesto indicado sin
identificación fiscal del emisor.

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
- Si falta el número de factura, el traspaso busca en Lexware por contacto,
  fecha del comprobante e importe. Una coincidencia no se vincula
  automáticamente: usted elige «Vincular con este comprobante» u «Otro
  comprobante – crear nuevo».
- A través de la interfaz no se pueden buscar comprobantes existentes en
  DATEV. Por eso no envíe las mismas facturas además por el correo de carga de
  DATEV; allí la protección contra duplicados recae solo en la recepción de
  facturas.
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
