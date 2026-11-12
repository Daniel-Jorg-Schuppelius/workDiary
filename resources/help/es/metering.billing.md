---
title: "Facturación por contador"
topic: metering.billing
version: 1
keywords:
    - facturación por consumo
    - pago por uso
    - coste por copia
    - precio por clic
    - facturación de copias
    - franquicia incluida
    - precios escalonados
    - cuota base
    - horas de funcionamiento
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

## Escala, estado y proyecto

**Escala por tramos:** el diálogo del acuerdo ofrece filas con **desde
cantidad** y **precio por unidad**. El primer tramo empieza en 0; las filas
vacías se ignoran. Con escala, en cada tramo se aplica su precio en lugar del
precio unitario, y el cálculo es progresivo sobre el consumo una vez
descontada la cantidad gratuita: una unidad más nunca abarata la factura. La
lista muestra entonces «Escala» en lugar del precio unitario.

**Estado:** *Activo* se factura; el proceso omite *En pausa* y *Finalizado*, y
la facturación inmediata solo se ofrece para acuerdos activos. Tras una pausa,
el proceso recupera desde la fecha de **próxima facturación**; si el tiempo en
pausa no debe facturarse, adelante la fecha antes de reactivar.

**Proyecto:** el proyecto elegido se asigna a los borradores de factura del
proceso.
