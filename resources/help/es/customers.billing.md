---
title: "Condiciones especiales & cuenta de cliente"
topic: customers.billing
version: 5
keywords:
    - precios especiales
    - tarifa horaria del cliente
    - tarifa de fin de semana
    - tarifa festivos
    - gastos de desplazamiento
    - tarifa de desplazamiento
    - saldo
    - cuota mensual
    - iguala mensual
    - Lexoffice
    - sevDesk
    - easybill
    - cierre mensual
audience: []
modules:
    - module.vertrieb
related:
    - contacts.manage
    - invoices.manage
    - customer-portal.billing
---

En la ficha del cliente se pueden definir **condiciones especiales**:
tarifas horarias propias por actividad y tipo de día (laborable/fin de
semana, definido mediante «días laborables por semana») y el método de
liquidación — **cuenta de cliente** sin facturas con saldo corriente,
**factura mensual** o **cuota fija** a través del programa contable (Lexoffice, sevDesk o easybill).

Las condiciones incluyen también una **tarifa fija de desplazamiento**:
cada registro de tiempo facturable aporta entonces x minutos
adicionales, valorados con la tarifa del registro — opcionalmente solo
para determinadas actividades. El tiempo de trabajo registrado no cambia,
así que la cuenta de horas y el horario flexible quedan intactos; el
justificante y el PDF muestran el desplazamiento en una columna propia mientras **Mostrar el desplazamiento por separado** esté activado; sin la marca, el desplazamiento queda incluido en la duración en el portal y en el justificante, el importe no cambia.
En el registro de tiempo el valor puede sobrescribirse caso por caso
(también a 0). Los tiempos de viaje o guardia y los registros a precio
fijo no reciben desplazamiento.

Lo que cuenta como fin de semana lo determina «días laborables por
semana» (6 = solo domingo). Opcionalmente los **festivos** cuentan
también como fin de semana, según el calendario de festivos de la
organización. Decide el día natural del inicio: un registro que pasa de
la medianoche pertenece por completo a su día de inicio.

En modo cuenta, cada mes tiene un bloque de liquidación: total (horas ×
tarifa), liquidado (pagos), mes anterior (arrastre) y pendiente (saldo).
El saldo pasa automáticamente al mes siguiente. Los meses se **cierran**
cronológicamente (bloqueo + instantánea, los tiempos cuentan como
liquidados) y pueden reabrirse en orden inverso.

Los pagos se registran manualmente en el panel o mediante la conciliación
bancaria (la cuenta de cliente es un destino de asignación). Los
registros tardíos en meses cerrados se señalan — reabra el mes o cambie
la fecha.

En **modo de cuota fija**, el programa contable de la soberanía de
facturación del cliente gestiona el documento y el pago: Lexoffice,
sevDesk o easybill. La cuota mensual se indica sin IVA («anticipo mensual
previsto»); el saldo local enfrenta horas × tarifa con la cuota pagada.
Hay dos vías para el documento:

- **Enviar cuota fija** crea la factura en el programa (también cada mes
  y de forma automática para el mes anterior). sevDesk asigna el número de
  factura y después la lleva como enviada y abierta; easybill la finaliza
  y la numera.
- **Vincular documento** engancha al mes una factura que ya creó en el
  programa. Si exactamente una factura del cliente coincide por mes e
  importe neto, esto ocurre automáticamente durante la sincronización de
  documentos.

En cuanto hay un documento enganchado, «Enviar cuota fija» desaparece —
de lo contrario surgiría un segundo documento en el programa. El estado
de pago vuelve con la sincronización y se registra **en neto** (los
programas llevan el importe pendiente en bruto); los pagos parciales
cuentan de forma proporcional. En sevDesk y easybill el estado de pago
procede del espejo de documentos, es decir, de la consulta periódica de
documentos; un documento anulado o eliminado en el programa revierte el
pago registrado. Si el envío a sevDesk o easybill se
interrumpe, la factura queda como borrador; un nuevo envío la continúa en lugar de crear un segundo
documento en el programa. Los pagos procedentes del programa no se pueden
anular a mano en el panel: eso se hace mediante el documento en el
programa.

Si las condiciones especiales se crearon después, los tiempos más
antiguos aparecen al principio con 0,00 € en «total». **Recalcular** los
valora con las tarifas configuradas; las tarifas forzadas manualmente
quedan intactas.

El cliente ve asistencias y saldo en el portal de clientes en
«Facturación» y puede descargar allí el registro de asistencia en PDF.
