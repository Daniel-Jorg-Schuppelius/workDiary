---
title: "Contabilizar y bandeja"
topic: accounting.posting
version: 10
keywords:
    - asiento contable
    - contabilizar documentos
    - imputación contable
    - propuesta de asiento
    - reglas contables
    - anular asiento
    - contraasiento
    - principio de cuatro ojos
    - aprobación
    - moneda extranjera
    - tipo de cambio
    - libro diario
    - partidas abiertas
    - asiento recurrente
audience:
    - admin
    - geschaeftsfuehrung
    - buchhaltung
modules:
    - module.finance
related:
    - accounting.overview
    - accounting.closing
---

La **bandeja contable** es el punto de entrada: muestra documentos, gastos,
movimientos de caja y pagos del periodo con su estado. Lo bloqueado va primero.

**Propuesta antes del asiento.** Incluso una propuesta inequívoca solo se
convierte en borrador revisado. Con el doble control activo, quien prepara no
contabiliza.

**Doble control en los asientos directos.** Algunas operaciones crean su propio
asiento: **Descuento** y **Baja** mediante **Compensar** en las partidas
abiertas, **Contabilizar en cuenta puente** en la conciliación bancaria, el
**Traspaso interno**, **Importar saldos iniciales**, **Contabilizar el pago
anticipado** y **Anular** en el diario. Sin doble control se contabilizan de
inmediato. Si está activo, se crea un borrador revisado: un mensaje y un aviso
en el diálogo lo indican, y la bandeja contable lo muestra con su tipo
(**Descuento/baja**, **Asiento en suspenso**, **Traspaso interno**, **Saldos
iniciales**, **Pago anticipado especial**, **Anulación**) y el estado **Listo**
– con independencia del periodo de la cabecera, mientras esté elegido **Todos
los orígenes**. La operación solo surte efecto cuando una segunda persona la
contabiliza con **Contabilizar** o **Aceptar y contabilizar todo**: solo
entonces la partida abierta queda compensada, el movimiento bancario
contabilizado y el pago anticipado especial imputado en la última
autoliquidación del año. En el diario, **Contabilizar de inmediato** sigue
bloqueado para la persona que crea el asiento.

**Descartar borrador.** Cualquier borrador – de una de estas operaciones, de
una propuesta de la bandeja contable, de una plantilla de asiento o registrado
a mano – se elimina con **Descartar borrador** en la bandeja contable o en su
página de detalle (con confirmación). Los borradores de las operaciones citadas
los descarta quien puede **Contabilizar asientos**; para todos los demás basta
**Preparar asientos**. El paso queda registrado y la operación vuelve a estar
abierta: la partida puede compensarse de nuevo, el movimiento bancario, los
saldos iniciales y el pago anticipado especial pueden contabilizarse otra vez,
el asiento de una anulación descartada puede anularse de nuevo, un traspaso interno desaparece junto
con el vínculo de sus documentos, un movimiento bancario apartado mediante
**Contabilizar en cuenta contable** vuelve a estar abierto y el justificante de
una propuesta vuelve a aparecer sin contabilizar en la bandeja. La operación de
una plantilla de asiento se considera **Omitida**; el periodo siguiente se
ejecuta con normalidad. Los asientos contabilizados no pueden descartarse.

**Editar borrador.** Mientras un asiento no esté contabilizado, se modifica en
su página de detalle con **Editar borrador**: fecha de asiento y de documento,
texto, justificante, contraparte y las líneas con cuenta, importe, código de
impuesto, centro de coste y texto de línea. Esto vale para asientos manuales,
borradores de plantillas y borradores de propuestas. Quien modifica pasa a ser
quien prepara – con el principio de los cuatro ojos contabiliza otra persona.
El cambio queda registrado. Los borradores de descuento, baja, aclaración,
traspaso, movimiento bancario sin justificante, saldos iniciales, pago
anticipado especial y anulación siguen a su operación y no se pueden
reescribir; en cualquier borrador, sin embargo, **Centros de coste** permite
completar el centro de coste por línea – por ejemplo, cuando una cuenta con
**Centro de coste obligatorio** impide contabilizar. Los asientos
contabilizados no se modifican.

**Bloquear en vez de adivinar.** Si falta una regla, la propuesta nombra rol y
criterios. Una cuenta por defecto adivinada solo se vería en los informes.

**Corrección solo con contraasiento.** Un asiento contabilizado es inmutable;
la anulación crea un contraasiento con motivo obligatorio.

**Documentos en moneda extranjera.** Las facturas emitidas y recibidas y los
gastos en moneda extranjera se convierten al tipo mensual de su mes
(§ 16 apdo. 6 UStG). Gestione los tipos en «Tipos de cambio» (junto a las
reglas de contabilización), uno a uno o por líneas, por ejemplo a partir de la
publicación del Ministerio de Hacienda. Sin tipo, el documento permanece en la
bandeja con una indicación. El tipo y el importe original constan en el
justificante del asiento. Los pagos, la caja y los activos en moneda extranjera
siguen sin contabilizarse; las diferencias de cambio en la liquidación se
registran a mano.

**Facturas recibidas por tipo.** Si una factura recibida lleva varios tipos
impositivos – en el desglose de la factura electrónica o como filas en el
diálogo **Introducir valores** –, la propuesta contabiliza gasto e IVA soportado
por tipo, cada uno con el código de IVA soportado de ese tipo. Así la casilla
66 y el descuento por tipo son correctos. Si el desglose no da el bruto, queda
una sola línea con los valores de cabecera.

**Inversión del sujeto pasivo.** Si las facturas recibidas indican como **caso
fiscal** el § 13b UStG o una adquisición intracomunitaria, la factura es neta.
La propuesta contabiliza el gasto contra la deuda y además el IVA repercutido
autoliquidado en el haber y el mismo IVA soportado en el debe – con el código
de ese caso fiscal: casillas 46/47 para servicios de otro país de la UE, 84/85
para los demás casos del § 13b, 89 o 93 para la adquisición, el IVA soportado
en 67 o 61. El tipo es el del justificante, al 0 % el tipo general. Si el
justificante muestra aun así impuesto o falta el código, queda bloqueado con
un aviso. Un descuento sobre una factura así es neto: reduce la base
imponible, y el IVA repercutido y el soportado bajan en el mismo importe (§ 17,
apdo. 1, frase 5 UStG); si la cuenta de descuentos es una cuenta automática de
DATEV, el descuento reduce la cuenta de gasto del justificante. Las facturas
recibidas contabilizadas cuyo justificante indica inversión del sujeto pasivo
pero cuyo asiento no lleva impuesto aparecen en el informe **Calidad contable**; no se contabiliza nada automáticamente.

**Facturas emitidas según el § 13b.** Si un cliente nacional figura como
destinatario según el § 13b (p. ej. obras de construcción para empresas
constructoras), la factura sale sin IVA y con la mención «Inversión del sujeto
pasivo». El asiento lleva el código de la casilla 60, con la plantilla de
plan de cuentas en la cuenta de ingresos 8337 (SKR03) o 4337 (SKR04); las
entregas intracomunitarias van a 8125 o 4125.

## Diario

El **Libro diario** se abre en **Ventas y facturación** → **Contabilidad** →
**Diario**. Muestra todos los asientos preparados y contabilizados del periodo
elegido en la cabecera. Las entradas **Diario**, **Partidas abiertas** y
**Recurrentes** aparecen en cuanto su organización lleva o ha llevado la
contabilidad local; si otro sistema lleva actualmente el libro mayor, un aviso
sobre la lista lo indica.

- **Lista:** **N.º**, **Fecha de asiento**, **Concepto**, **Cuentas**,
  **Importe** y **Estado** (**Borrador**, **Revisado**, **Contabilizado**,
  **Anulado**). La búsqueda encuentra concepto y documento; el filtro de estado
  muestra un solo estado. WorkDiary asigna el número de diario solo al
  contabilizar, de forma correlativa y sin huecos.
- **Registrar asiento:** el diálogo contabiliza tantas líneas como haga falta.
  Son obligatorios la **Fecha de asiento**, el **Texto** y al menos dos líneas
  con **Cuenta** (número de cuenta, con lista de sugerencias) y un importe en
  el **Debe** o en el **Haber**; **Fecha de documento**, **Justificante**,
  **Contraparte**, **Código de impuesto**, **Centro de coste** y **Texto de
  línea** son opcionales. Las líneas vacías se ignoran. Con **Código de
  impuesto** se indica el importe neto; la línea de impuesto en la cuenta de
  impuesto del código se crea al guardar, y el asiento aparece en las casillas
  de la declaración. La **Contraparte** (cliente o proveedor) se aplica a las
  líneas en cuentas con partidas abiertas y a las líneas con código de
  impuesto – así la partida abierta lleva el cliente. Solo se ofrecen cuentas
  activas y códigos de impuesto válidos en la fecha del asiento. Sin
  **Contabilizar de inmediato** se crea un borrador.
- **Ver un asiento:** la página de detalle muestra **Cabecera** y **Líneas**
  con las sumas del Debe y del Haber, además de avisos sobre una anulación y
  sobre presupuestos mensuales superados (sin bloqueo). Los borradores y los
  asientos revisados se contabilizan aquí con **Contabilizar**.
- **Anular:** un asiento contabilizado se corrige con **Anular**: el
  **Motivo** es obligatorio, la **Fecha del contraasiento** es opcional. Si
  queda vacía, vale el día original mientras su periodo siga abierto; si no, la
  fecha de hoy. **Crear contraasiento** contabiliza el asiento inverso y
  revierte también las partidas abiertas que surgieron del original. Con el
  doble control activo, el contraasiento se crea como borrador: el asiento
  sigue contabilizado y las partidas abiertas sin cambios hasta que una
  segunda persona contabiliza la anulación. Hasta entonces no es posible una
  segunda anulación del mismo asiento; su página de detalle remite al borrador
  con **Ver borrador pendiente**. La anulación automática al deshacer una
  asignación en la conciliación bancaria se contabiliza siempre de inmediato.

Al contabilizar, WorkDiary comprueba: existe un periodo abierto para la fecha
del asiento y la contabilidad local lleva el libro mayor ese día; Debe y Haber
son iguales; todas las cuentas están activas y una cuenta con **Centro de
coste obligatorio** tiene centro de coste. Con el principio de cuatro ojos
activo, quien creó el asiento no puede contabilizarlo – tampoco mediante
**Contabilizar de inmediato**.

**Permiso:** ver con **Consultar la contabilidad**, registrar con **Preparar
asientos**, contabilizar y anular con **Contabilizar asientos**.

## Partidas abiertas

**Ventas y facturación** → **Contabilidad** → **Partidas abiertas** muestra los
derechos de cobro y las obligaciones de pago de asientos contabilizados que aún
no están compensados – con independencia del periodo de la cabecera. Una
partida abierta nace cuando se contabiliza un asiento en una cuenta con la
característica **Partidas abiertas**; los pagos la compensan a través de la
conciliación bancaria.

- Las pestañas **Derecho de cobro** y **Obligación de pago** separan ambos
  sentidos.
- Los mosaicos suman los importes abiertos por antigüedad desde el
  vencimiento: **No vencido**, **1–30 días**, **31–60 días**, **61–90 días** y
  **más de 90 días**.
- La lista, ordenada por vencimiento, muestra **Documento**, **Contraparte**,
  **Fecha del documento**, **Vencimiento** (con el aviso «… días de
  retraso»), **Original**, **Abierto** y **Estado** (**Abierta**,
  **Parcialmente compensada**, **En disputa**). **Ver asiento** abre el
  asiento de origen.
- **Compensar** registra una deducción sin pago: **Descuento**, **Retención**
  o **Baja**, con **Importe** y **Nota** opcional. El importe no puede superar
  el resto abierto. Para descuento y baja, WorkDiary contabiliza a la vez un
  contraasiento en el diario – en la **cuenta de descuento** o la **cuenta de
  baja** de los ajustes de DATEV (vacío = valor predeterminado del plan de cuentas: 8736 y 2400 en SKR03, 4736
  y 6930 en SKR04). Una deuda usa sus propias cuentas: descuentos obtenidos
  (3736 o 5736) y, para la baja, otros ingresos (2709 o 4839).
  El descuento reduce también el impuesto (§ 17 UStG): WorkDiary divide el
  importe según el tipo del justificante en neto (cuenta de descuento, con
  código de impuesto) y parte de impuesto (cuenta de IVA repercutido o
  soportado) – de forma proporcional si hay varios tipos, cada uno en la
  cuenta de descuento de su tipo mientras no se haya configurado una cuenta
  propia. Las casillas de la declaración y la vista previa del IVA muestran la
  reducción. Si el asiento de la partida no lleva impuesto (pequeño
  empresario, exento, saldo inicial), el
  descuento queda sin dividir; el diálogo indica qué se aplica. Lo mismo vale
  para el descuento de la conciliación de pagos. Los descuentos contabilizados
  antes sin corrección de impuesto se indican en el informe **Calidad de los
  asientos** con su parte de impuesto calculada; no se contabiliza nada
  automáticamente. Si esa cuenta falta en
  el plan de cuentas, la compensación se rechaza con un mensaje y la partida
  sigue abierta; el diálogo lo advierte antes. Una retención no crea ningún
  asiento. Con el principio de los cuatro ojos, el contraasiento se crea como
  borrador en la bandeja contable y la partida sigue abierta hasta que una
  segunda persona lo contabilice. Hasta entonces la lista muestra **Borrador
  pendiente de aprobación**, **Mostrar borrador pendiente** lleva al asiento,
  y cualquier otra compensación de la partida – también una retención – se
  rechaza. Si la partida se compensa de otro modo antes de la aprobación, la
  contabilización falla porque el importe supera el resto abierto. Las
  compensaciones por descuento o baja anteriores que se cerraron sin asiento
  se indican en el informe **Calidad de los asientos**; deben contabilizarse
  manualmente.
- **Marcar como en disputa** señala una partida abierta como **En disputa**,
  con motivo obligatorio. Sigue abierta y puede compensarse, también tras una
  compensación parcial. En la factura emitida WorkDiary establece un bloqueo
  de reclamación si aún no existe – la partida no se reclama ni
  individualmente ni en el ciclo de reclamaciones – y, en el resumen y la
  previsión de liquidez, un crédito en disputa no cuenta como cobro esperado. La factura recibida de
  una deuda en disputa queda bloqueada en la propuesta de pago (**Partida
  abierta en disputa**) hasta que se retire la marca.
  **Retirar la disputa** quita la marca y el bloqueo de reclamación que creó;
  un bloqueo establecido manualmente se mantiene. El filtro sobre la lista
  muestra **Abierta**, **Parcialmente compensada** o **En disputa**. Ambos
  pasos constan en el registro de cambios.

**Permiso:** ver con **Consultar la contabilidad**, compensar con
**Contabilizar asientos**.

## Recurrentes

En **Ventas y facturación** → **Contabilidad** → **Recurrentes** (página
**Operaciones recurrentes**) usted planifica lo que se repite con regularidad.
Hay dos tipos de plantilla:

- **Expectativa de documento:** para un documento que debe llegar con
  regularidad, como un alquiler o un leasing. No crea documento ni asiento,
  sino al vencimiento una operación abierta con el estado **Documento
  esperado**; así queda visible que el original aún falta.
- **Plantilla de asiento:** al vencimiento crea un borrador de asiento con
  las líneas de la plantilla, fechado el día de vencimiento. Nunca contabiliza por sí misma; usted lo hace a mano en la
  bandeja contable o en el diario.

La página se divide en **Operaciones abiertas** (**Plantilla**, **Periodo**,
**Vencimiento**, **Esperado**, **Estado**; en **Bloqueado** el motivo aparece
debajo, en **Borrador creado** **Ver asiento** lleva al borrador, en
**Documento esperado** **Asignar documento** asigna el original),
**Plantillas** (**Denominación**, **Tipo**, **Ritmo**, **Próximo vencimiento**,
**Responsable**, **Estado** con número de versión) y **Planes de
facturación**: planes de facturación activos solo como referencia, que se
editan con **Abrir los planes**.

**Crear plantilla:** **Tipo**, **Denominación**, **Ritmo** (**Mensual**,
**Trimestral**, **Semestral**, **Anual**), **Día de vencimiento** (1–28, para
que todos los meses tengan ese día), **Esperado**, **Inicio** y opcionalmente
**Fin**; en las plantillas de asiento además las líneas del asiento, y
**Responsable** y una **Nota**. Una plantilla de asiento sin al menos dos
líneas con cuenta e importe no se guarda. Al editar, las líneas guardadas
aparecen rellenadas
y el diálogo muestra los próximos vencimientos; cada cambio guarda una nueva
versión y las operaciones ya creadas no cambian.

**Proceso y reglas:**

- Un proceso diario crea las operaciones vencidas mientras la contabilidad
  local lleve el libro mayor en la fecha de referencia. Por plantilla y periodo
  se crea como máximo una operación.
- **Ejecutar ahora** crea de inmediato la operación del próximo vencimiento,
  sin esperar al proceso diario.
- Si no se puede crear un borrador, por ejemplo porque no existe un periodo
  para la fecha, la operación aparece como **Bloqueado** con su motivo.
- **Asignar documento** cumple una expectativa de documento: usted elige la
  factura recibida en el campo **Factura electrónica entrante**. Se ofrecen
  las facturas de **Facturas electrónicas entrantes** que usted puede ver, que
  no están rechazadas y que aún no están asignadas a ninguna operación; una
  factura cumple como máximo una operación. Después la operación pasa al
  estado **Cumplido**.
- Cuando se contabiliza el borrador de una plantilla de asiento, su operación
  también pasa al estado **Cumplido**.
- Si una operación con **Documento esperado** o **Borrador creado** está
  vencida, WorkDiary lo avisa una sola vez mediante las notificaciones, de
  fábrica a contabilidad y a la persona indicada en **Responsable**.
- **Pausar** detiene una plantilla; **Reanudar** continúa con el próximo
  vencimiento a partir de hoy, sin recuperar los perdidos. **Finalizar**
  detiene la plantilla de forma definitiva; las operaciones ya creadas se
  conservan.

**Líneas de la plantilla.** Una plantilla de asiento admite las mismas líneas
que el asiento manual: varias líneas, **Código de impuesto**, **Centro de
coste** y **Contraparte**. Debe y haber tienen que cuadrar; de lo contrario, la
plantilla no se guarda. Si **Esperado** queda vacío, se aplica el total del
asiento. Si cambia el tipo de un código de impuesto, guarde de nuevo la
plantilla. Si descarta el borrador de una plantilla, su operación se considera
**Omitida**.

**Permiso:** ver con **Consultar la contabilidad**; crear, editar, pausar,
reanudar y finalizar plantillas con **Configurar la contabilidad**; **Ejecutar
ahora** y **Asignar documento** con **Preparar asientos**.

## Ventas sin impuesto y gastos

Las plantillas del plan de cuentas tienen cuentas de ingresos propias para los
códigos RC21 (casilla 21), AUSF (casilla 43) y NSTB (casilla 45); los planes creados
desde la plantilla las reciben con la actualización. Los **gastos** contabilizan el
IVA soportado al tipo del justificante. Si no hay código de IVA soportado para ese
tipo —normalmente IVA extranjero, que no es deducible—, la propuesta se detiene:
registre el gasto en bruto sin tipo impositivo. Las facturas con inversión del
sujeto pasivo van a la entrada de facturas, que conoce el supuesto fiscal.
