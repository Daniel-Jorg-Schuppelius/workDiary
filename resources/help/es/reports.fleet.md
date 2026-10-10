---
title: "Flota, libro de viajes y tiempos de conducción"
topic: reports.fleet
version: 3
keywords:
    - análisis de vehículos
    - kilometraje
    - costes de combustible
    - coste por kilómetro
    - libro de ruta fiscal
    - viajes privados
    - retribución en especie
    - regla del 1 por ciento
    - coche de empresa
    - tiempos de conducción
    - tiempos de descanso
    - pausa de conducción
audience:
    - admin
    - geschaeftsfuehrung
    - teamleitung
    - buchhaltung
    - user
    - aussendienst
related:
    - assets.fleet
    - travel-expenses.manage
    - fleet.license-checks
    - reports.arbzg-compliance
    - admin.organization-settings
    - reports.overview
---

Estos análisis se refieren a vehículos y viajes: kilómetros y costes de
energía por vehículo, el libro de viajes fiscal de un vehículo, la comparación
entre el método del libro de viajes y la regla del 1 % y el justificante de los
tiempos de conducción y descanso. Se basan en los viajes del **Libro de
viajes** (**Viajes y gastos** → **Libro de viajes**), los justificantes del
**Registro de repostaje y carga** (**Flota** → **Registro de repostaje y
carga**) y los datos de los vehículos en **Flota** → **Vehículos**.

## Período y exportación

- El período se elige con el selector de período de la cabecera. La
  comparación 1 % calcula en cambio con un año natural.
- **PDF** descarga una versión para imprimir; **CSV** y **Excel** están en
  **Exportación**. Las exportaciones conservan los filtros elegidos; cada
  exportación se registra en el registro de auditoría.

## Flota

**Análisis** → **Recursos** → **Flota** abre el **Análisis de flota** con
viajes, repostajes, costes de energía y reembolsos por vehículo.

- Mosaicos: **Vehículos**, **Σ km** (con el número de viajes), **Repostajes /
  cargas** (con litros y kWh), **Costes de energía** (con el total de los
  reembolsos) y **Media €/km**.
- Gráficos: **Kilómetros por vehículo (top 15)** y los kilómetros a lo largo
  del tiempo.
- Tabla por vehículo: **Vehículo**, **Propulsión**, **Viajes**, **km**,
  **Reembolso**, **Repostajes**, **Litros**, **kWh**, **Costes de energía**,
  **€/km** y **Kilometraje**, con una fila de totales.

Así se obtienen los valores:

- **Viajes**, **km** y **Reembolso** proceden de los viajes del libro de
  viajes que tienen un vehículo asignado y cuya fecha cae en el período.
- **Repostajes**, **Litros**, **kWh** y **Costes de energía** proceden de los
  justificantes de repostaje y carga iniciados en el período.
- **€/km** divide los costes de energía entre los kilómetros; sin kilómetros
  o sin costes el campo queda vacío.
- **Kilometraje** es la última lectura del cuentakilómetros de los
  justificantes de repostaje y carga del período; si no la hay, la lectura
  guardada en el vehículo.

Filtros: **Área** (**Solo mis viajes** o **Flota completa**, solo para
administradores) y **Empleado**. Todos los demás solo ven sus propios viajes
y justificantes. Exportación en PDF, CSV y Excel.

## Justificante libro de viajes

**Análisis** → **Recursos** → **Justificante libro de viajes** muestra el libro
de viajes fiscal de un vehículo: lecturas del cuentakilómetros, tipo de
trayecto, destino, finalidad y conductor, totales por tipo de trayecto y la
proporción privada.

- Elija el **Vehículo**. Los vehículos en **Modo libro de viajes** aparecen
  arriba y están marcados como tales. Sin derechos de administrador, la lista
  contiene los vehículos sin **Conductor predeterminado** y aquellos de los
  que usted es el conductor predeterminado; los administradores ven todos los
  vehículos.
- Si el vehículo no está en modo libro de viajes, un aviso indica que los
  viajes sin lecturas del cuentakilómetros y sin bloqueo no constituyen un
  libro de viajes a efectos fiscales. El modo se activa en el vehículo con
  **Modo libro de viajes (fiscal)**.
- Mosaicos: **Viajes** (con el número de viajes bloqueados), **Σ km**, los
  kilómetros por tipo de trayecto (**Profesional**, **Domicilio–trabajo**,
  **Privado**) y **Proporción privada** (kilómetros privados en relación con
  todos los kilómetros).
- Tabla: **Fecha**, **Km inicial**, **Km final**, **km**, **Tipo de
  trayecto**, **Destino**, **Finalidad**, **Conductor** y **Estado**
  (**bloqueado**, **abierto** o **anulado**, además de **firmado** y
  **Trayecto de anulación**). El pie de la tabla indica los kilómetros por
  tipo de trayecto.

Los kilómetros de un viaje resultan del kilometraje final menos el inicial;
si faltan las lecturas, cuenta la distancia registrada, el doble en un viaje
de ida y vuelta. La lista contiene todos los viajes del vehículo en el
período, también los de otros conductores. Los viajes originales anulados
siguen visibles tachados, pero no cuentan en ningún total.

Exportación en PDF, CSV y Excel en cuanto se elige un vehículo. CSV y Excel
contienen además la dirección de salida, las horas de bloqueo y de firma, la
marca de anulación, el viaje corregido con el motivo de la corrección, así
como los totales y la proporción privada.

## Comparación 1 %

La **Comparación 1 %** se abre con el botón del mismo nombre en la página
**Justificante libro de viajes**; no tiene entrada de menú propia. Compara para
cada vehículo la retribución en especie según el método del libro de viajes con
la regla del 1 %. Es un cálculo simplificado y no constituye asesoramiento
fiscal.

- **Año**: el año en curso y los seis anteriores; está preseleccionado el año
  anterior.
- Solo se enumeran los vehículos en modo libro de viajes, con la misma
  selección de vehículos que en el justificante del libro de viajes.
- **Meses**: meses con viajes. **km totales**, **de ellos privados** y **de
  ellos al trabajo** solo cuentan viajes con kilometraje inicial y final; los
  viajes originales anulados no cuentan.
- **Costes totales**: costes de energía de los justificantes de repostaje y
  carga del año más otros costes anuales; la información emergente muestra
  ambas partes.
- **Método del libro de viajes**: costes totales multiplicados por la
  proporción de kilómetros privados y de trayecto al trabajo sobre todos los
  kilómetros.
- **Regla del 1 %**: **Precio bruto de catálogo (€)**, redondeado a la
  centena de euros inferior, del que se toma el 1 % por mes de uso más el
  0,03 % por kilómetro de **Distancia domicilio–trabajo (km)** y mes. Para
  vehículos eléctricos e híbridos bonificados adquiridos a partir de 2019, la
  base se reduce a una cuarta parte o a la mitad según la **Fecha de
  adquisición** y el precio de catálogo; la celda muestra entonces **Base**
  con 0,25 % o 0,5 % en lugar del 1 %. Sin precio de catálogo aparece **Falta el precio de
  catálogo**.
- **Más favorable** marca el método con el valor más bajo.

El símbolo del euro abre el diálogo **Otros costes anuales** para leasing,
seguro, impuesto de circulación, mantenimiento, reparaciones y amortización,
con **Importe (€)** y **Aviso**. Para ello necesita el permiso **Gestionar
los vehículos**. La página no ofrece exportación.

## Justificante tiempos de conducción

El **Justificante tiempos de conducción** es una descarga de los tiempos de
conducción y descanso por conductor y día natural. Lo encontrará en la página
**Cumplimiento del tiempo de trabajo** (**Análisis** → **Finanzas y
auditoría**) en el menú **Exportación**. Solo aparece si en los ajustes de
cumplimiento de la organización, en **Tiempos de conducción y descanso**,
están activadas las normas de tiempos de conducción, y requiere el permiso
**Ver el cumplimiento del tiempo de trabajo**.

- Evalúa los viajes efectivos con hora de salida y de llegada en vehículos
  que tienen marcado **Aplicar las normas de tiempos de conducción y
  descanso**. No se leen datos del tacógrafo.
- Columnas: **Conductor**, **Número de personal**, **Fecha**, **Vehículos**,
  **Primera salida**, **Última llegada**, **Tiempo de conducción**,
  **Periodo de conducción más largo sin pausa**, **Pausas (min)**, **Descanso
  previo** y los **Hallazgos** del día.
- La descarga adopta el período y los filtros de empleado y de equipo de la
  página. **Justificante tiempos de conducción** entrega un archivo CSV,
  **Justificante tiempos de conducción (PDF)** los mismos datos en un PDF
  horizontal. Ambas descargas quedan registradas en el registro de
  auditoría.

Los hallazgos se basan en los límites del Reglamento (CE) 561/2006 y de la
FPersV: como máximo 9 h de conducción al día (10 h dos veces por semana),
56 h por semana y 90 h en dos semanas, una pausa de 45 minutos tras 4,5 h
(divisible en 15 y 30 minutos), 11 h de descanso diario (como máximo tres
veces por semana 9 h) y 45 h de descanso semanal (24 h con compensación). No
es asesoramiento jurídico; corresponde a la empresa aclarar qué normas se
aplican en cada caso.
