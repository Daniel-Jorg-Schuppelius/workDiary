---
title: "Condiciones especiales & cuenta de cliente"
topic: customers.billing
version: 2
audience: []
related:
    - contacts.manage
    - invoices.manage
    - customer-portal.billing
---

En la ficha del cliente se pueden definir **condiciones especiales**:
tarifas horarias propias por actividad y tipo de día (laborable/fin de
semana, definido mediante «días laborables por semana») y el método de
liquidación — **cuenta de cliente** sin facturas con saldo corriente,
**factura mensual** o **cuota fija (Lexoffice)**.

En modo cuenta, cada mes tiene un bloque de liquidación: total (horas ×
tarifa), liquidado (pagos), mes anterior (arrastre) y pendiente (saldo).
El saldo pasa automáticamente al mes siguiente. Los meses se **cierran**
cronológicamente (bloqueo + instantánea, los tiempos cuentan como
liquidados) y pueden reabrirse en orden inverso.

Los pagos se registran manualmente en el panel o mediante la conciliación
bancaria (la cuenta de cliente es un destino de asignación). Los
registros tardíos en meses cerrados se señalan — reabra el mes o cambie
la fecha.

En **modo de cuota fija**, Lexoffice gestiona el documento y el pago. La
cuota mensual se indica sin IVA («anticipo mensual previsto»); el saldo
local enfrenta horas × tarifa con la cuota pagada. Hay dos vías para el
documento:

- **Enviar cuota fija** crea la factura en Lexoffice (también cada mes y
  de forma automática para el mes anterior).
- **Vincular documento** engancha al mes una factura que ya creó en
  Lexoffice. Si exactamente una factura del cliente coincide por mes e
  importe neto, esto ocurre automáticamente durante la sincronización de
  documentos.

En cuanto hay un documento enganchado, «Enviar cuota fija» desaparece —
de lo contrario surgiría un segundo documento en Lexoffice. El estado de
pago vuelve con la sincronización y se registra **en neto** (Lexoffice
trabaja en bruto).

Si las condiciones especiales se crearon después, los tiempos más
antiguos aparecen al principio con 0,00 € en «total». **Recalcular** los
valora con las tarifas configuradas; las tarifas forzadas manualmente
quedan intactas.

El cliente ve asistencias y saldo en el portal de clientes en
«Facturación» y puede descargar allí el registro de asistencia en PDF.
