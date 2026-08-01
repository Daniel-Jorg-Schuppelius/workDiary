---
title: "Transporte de pasajeros (taxi/VTC)"
topic: passenger.overview
version: 1
audience: []
related:
    - claims.overview
---

El perfil sectorial taxi/VTC gestiona cada transporte de pasajeros en un
expediente de viaje propio: aceptación con modo de operación congelado,
despacho con controles obligatorios (permiso de transporte de personas,
licencia, justificantes del vehículo), inicio con tarifa o precio fijo
congelado y cierre con el valor del taxímetro, la decisión fiscal y el
método de pago.

**Viajes:** Los viajes nuevos se crean con «Nuevo viaje». VTC y transporte a
demanda agrupado exigen una recepción de pedido documentada en la sede; solo
el taxi admite destinos abiertos. El despacho verifica conductor, perfil del
vehículo y licencia — los obstáculos se muestran como errores de validación.

**Datos maestros:** Las tarifas están versionadas (periodo de validez,
precio base, precio por km y por minuto, suplementos, corredor de precio
fijo). Las licencias y perfiles de vehículo con vencimientos de calibración,
BOKraft e inspección están al lado; justificantes vencidos bloquean el
despacho.

**Liquidación de turno:** Los ingresos del taxímetro y los métodos de pago
(efectivo, tarjeta, vale, factura, intermediario) se llevan por separado;
las propinas no cuentan contra el total del taxímetro. Las diferencias
quedan abiertas hasta aclararse con motivo.

WorkDiary no sustituye ni el taxímetro/cuentakilómetros ni la TSE — esos
sistemas siguen siendo soberanos; sus valores se documentan y concilian.
