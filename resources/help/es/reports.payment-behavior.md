---
title: "Comportamiento de pago"
topic: reports.payment-behavior
version: 2
audience: []
related:
    - reports.economics
    - reports.customer-value
---

Vista de comportamiento y tendencia de las **facturas gestionadas
localmente** — el informe de facturación muestra el inventario (estado,
antigüedad), este informe el comportamiento subyacente. La fecha de
referencia es siempre el **fin del período** (informes reproducibles).

## DSO con ejemplo numérico

**DSO** (days sales outstanding) = cuentas por cobrar abiertas a fin de
mes ÷ ingresos de los últimos 90 días × 90. Ejemplo: 12.000 € abiertos
con 48.000 € de ingresos en 90 días → 12.000 ÷ 48.000 × 90 = **22,5
días** de inmovilización media del capital. Una curva ascendente
significa que el negocio inmoviliza cada vez más liquidez.

## Plazo de pago vs. retraso

- **Plazo de pago** = días desde la emisión hasta el pago (independiente
  del vencimiento) — como tendencia mensual y distribución por cliente.
- **Retraso** = días **tras el vencimiento**; quien paga antes cuenta 0.
  La lista muestra los clientes con mayor retraso medio.

Leer el diagrama de caja: línea = mediana, caja = mitad central, bigotes
= rango. Un cliente con mediana de 40 días con vencimiento a 14 paga
tarde sistemáticamente — es un tema de condiciones, no un caso aislado.

## Qué hacer con ello

- **DSO en aumento** → revisar reclamaciones, acortar vencimientos,
  considerar descuento por pronto pago.
- **Clientes con retraso medio alto** → renegociar plazos, anticipo/
  pagos parciales en pedidos nuevos, límite de crédito interno.
- **Facturas abiertas vencidas** (tabla inferior) → saltar directamente
  a la factura o a las facturas abiertas del cliente.

Un clic en un cliente en el diagrama de caja o en el top de retrasos
filtra este informe a ese cliente; si Lexoffice gestiona las facturas, entran a través del
espejo de comprobantes del plugin — la sincronización también carga
los datos de pago. Sin ninguna fuente, el informe lo indica
abiertamente.
