---
title: "Condiciones especiales & cuenta de cliente"
topic: customers.billing
version: 1
audience: []
related:
    - contacts.manage
    - invoices.manage
    - customer-portal.billing
---

En la ficha del cliente se pueden definir **condiciones especiales**:
tarifas horarias propias por actividad y tipo de día (laborable/fin de
semana, definido mediante «días laborables por semana») y el método de
liquidación — **cuenta de cliente** sin facturas con saldo corriente o
**factura mensual**.

En modo cuenta, cada mes tiene un bloque de liquidación: total (horas ×
tarifa), liquidado (pagos), mes anterior (arrastre) y pendiente (saldo).
El saldo pasa automáticamente al mes siguiente. Los meses se **cierran**
cronológicamente (bloqueo + instantánea, los tiempos cuentan como
liquidados) y pueden reabrirse en orden inverso.

Los pagos se registran manualmente en el panel o mediante la conciliación
bancaria (la cuenta de cliente es un destino de asignación). Los
registros tardíos en meses cerrados se señalan — reabra el mes o cambie
la fecha.

El cliente ve asistencias y saldo en el portal de clientes en
«Facturación» y puede descargar allí el registro de asistencia en PDF.
