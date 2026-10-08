---
title: "Contabilizar y bandeja"
topic: accounting.posting
version: 2
keywords:
    - asiento contable
    - contabilizar documentos
    - imputación contable
    - propuesta de asiento
    - reglas contables
    - anular asiento
    - contraasiento
    - principio de cuatro ojos
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
- **Nuevo asiento:** el diálogo asienta un importe de una cuenta del **Debe** a
  una del **Haber**. Son obligatorios **Fecha de asiento**, **Concepto**, ambas
  cuentas e **Importe**; **Fecha del documento**, **Documento** y – si hay
  centros de coste – un **Centro de coste** para ambas líneas son opcionales.
  Solo se pueden elegir cuentas activas. Sin **Contabilizar de inmediato** se
  crea un borrador.
- **Ver un asiento:** la página de detalle muestra **Cabecera** y **Líneas**
  con las sumas del Debe y del Haber, además de avisos sobre una anulación y
  sobre presupuestos mensuales superados (sin bloqueo). Los borradores y los
  asientos revisados se contabilizan aquí con **Contabilizar**.
- **Anular:** un asiento contabilizado se corrige con **Anular**: el
  **Motivo** es obligatorio, la **Fecha del contraasiento** es opcional. Si
  queda vacía, vale el día original mientras su periodo siga abierto; si no, la
  fecha de hoy. **Crear contraasiento** contabiliza de inmediato el asiento
  inverso y revierte también las partidas abiertas que surgieron del original.

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
  o **Baja**, con **Importe** y una **Nota** opcional. El importe no puede
  superar el resto abierto. Para descuento y baja, WorkDiary contabiliza a la
  vez un contraasiento en el diario – en la cuenta de descuentos o de bajas de
  la configuración DATEV, siempre que esa cuenta exista en el plan contable.
  Una retención no genera asiento.

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
  cuenta del Debe, cuenta del Haber e importe esperado, fechado el día de
  vencimiento. Nunca contabiliza por sí misma; usted lo hace a mano en la
  bandeja contable o en el diario.

La página se divide en **Operaciones abiertas** (**Plantilla**, **Periodo**,
**Vencimiento**, **Esperado**, **Estado**; en **Bloqueado** el motivo aparece
debajo, en **Borrador creado** **Ver asiento** lleva al borrador),
**Plantillas** (**Denominación**, **Tipo**, **Ritmo**, **Próximo vencimiento**,
**Responsable**, **Estado** con número de versión) y **Planes de
facturación**: planes de facturación activos solo como referencia, que se
editan con **Abrir los planes**.

**Crear plantilla:** **Tipo**, **Denominación**, **Ritmo** (**Mensual**,
**Trimestral**, **Semestral**, **Anual**), **Día de vencimiento** (1–28, para
que todos los meses tengan ese día), **Esperado**, **Inicio** y opcionalmente
**Fin**; en las plantillas de asiento además **Debe** y **Haber**, y una
**Nota**. Una plantilla de asiento sin ambas cuentas e importe no se guarda. Al
editar, el diálogo muestra los próximos vencimientos; cada cambio guarda una
nueva versión y las operaciones ya creadas no cambian.

**Proceso y reglas:**

- Un proceso diario crea las operaciones vencidas mientras la contabilidad
  local lleve el libro mayor en la fecha de referencia. Por plantilla y periodo
  se crea como máximo una operación.
- **Ejecutar ahora** crea de inmediato la operación del próximo vencimiento,
  sin esperar al proceso diario.
- Si no se puede crear un borrador, por ejemplo porque no existe un periodo
  para la fecha, la operación aparece como **Bloqueado** con su motivo.
- Si una operación con **Documento esperado** o **Borrador creado** está
  vencida, WorkDiary lo avisa una sola vez mediante las notificaciones.
- **Pausar** detiene una plantilla; **Reanudar** continúa con el próximo
  vencimiento a partir de hoy, sin recuperar los perdidos. **Finalizar**
  detiene la plantilla de forma definitiva; las operaciones ya creadas se
  conservan.

**Permiso:** ver con **Consultar la contabilidad**; crear, editar, pausar,
reanudar y finalizar plantillas con **Configurar la contabilidad**; **Ejecutar
ahora** con **Preparar asientos**.
