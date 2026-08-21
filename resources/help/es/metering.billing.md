---
title: "Facturación por contador"
topic: metering.billing
version: 1
audience: []
related:
    - invoices.manage
    - assets.fleet
    - contacts.manage
---

La facturación por contador liquida contratos ligados al consumo — copias en
un equipo multifunción, horas de servicio, kWh.

**Acuerdo:** Por cada contador se fija qué cliente se factura, con qué
periodicidad, con qué cuota base, qué cupo incluido y qué precio por unidad.
Son posibles precios por tramos; el tramo se aplica al consumo del periodo.

**Consumo:** Se factura la diferencia entre la última lectura *anterior* al
periodo y la última lectura *del* periodo — no la suma de los consumos
registrados, que incluiría también el tiempo previo. Si falta una lectura,
el periodo no se factura en lugar de estimarse.

**Ejecución:** La ejecución mensual crea borradores de factura, no facturas
enviadas — la aprobación sigue siendo humana. Cada periodo se factura una
sola vez; una segunda ejecución no genera duplicados.
