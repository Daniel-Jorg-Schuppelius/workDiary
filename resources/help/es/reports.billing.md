---
title: "Facturación, gastos, pagos e ingresos"
topic: reports.billing
version: 1
keywords:
    - cuentas por cobrar
    - antigüedad de saldos
    - tiempo no facturado
    - facturación por cliente
    - niveles de reclamación
    - tasa de aceptación de presupuestos
    - resumen de gastos
    - honorarios de externos
    - pagar a autónomos
    - prever recargos
    - ingresos por artículo
    - ingresos por categoría
audience:
    - admin
    - geschaeftsfuehrung
    - buchhaltung
    - personalverwaltung
    - teamleitung
related:
    - invoices.manage
    - finance.dunning
    - finance.incoming-invoices
    - travel-expenses.manage
    - org.members
    - admin.surcharge-rules
    - articles.master
    - reports.economics
---

Estos análisis reúnen el dinero relacionado con los servicios y el
personal: estado de las facturas y de las cuentas por cobrar, tiempo aún no
facturado, gastos, pagos al personal externo, recargos previstos según el
plan de turnos e ingresos por artículo. Encontrará la mayoría de las páginas
en **Análisis** → **Finanzas y auditoría** y la **Previsión de recargos** en
**Análisis** → **Equipo**.

## Período y exportación

- El período se elige con el selector de período de la cabecera. La
  **Previsión de recargos** mira en cambio hacia adelante a partir del mes en
  curso.
- **PDF** descarga una versión para imprimir; **CSV** y **Excel** están en
  **Exportación**; los formatos disponibles se indican en cada análisis. Las
  exportaciones conservan los filtros establecidos. Las exportaciones PDF y
  CSV se registran en el registro de auditoría.

## Facturación

**Análisis** → **Finanzas y auditoría** → **Facturación** abre el **Análisis
de facturación**. Se abre para los administradores y los roles con el
permiso **Ver todos los registros de tiempo**; sin este permiso se deniega el
acceso.

- Mosaicos:
  - **Emitido + pagado (Σ bruto)**: total bruto de las facturas con el estado
    **Emitida** o **Pagada** cuya fecha de factura cae en el período (sin
    fecha de factura cuenta la fecha de creación).
  - **Cuentas por cobrar**: total bruto de todas las facturas con el estado
    **Emitida**, con independencia del período. El mosaico se vuelve rojo en
    cuanto una de ellas lleva más de 30 días vencida; la indicación da su
    número.
  - **Tiempo no facturado**: registros de tiempo facturables del período que
    aún no ha consumido ninguna vía de facturación, con el número de
    registros y los ingresos previstos según los importes guardados.
- Gráficos: horas facturables y no facturables a lo largo del tiempo y
  **Facturación por cliente (top 15)** a partir de las facturas locales y de
  los comprobantes reflejados del programa de contabilidad. Un clic en un
  cliente abre **Clientes y proyectos** para ese cliente.
- **Facturas por estado**: **Cantidad**, **Neto** y **Bruto** por estado.
- **Antigüedad – partidas pendientes**: las facturas abiertas según los días
  transcurridos desde el vencimiento (sin vencimiento, desde la fecha de
  factura) en los tramos **Actual**, 1–7, 8–14, 15–30 y más de 30 días, con
  **Total abierto**.
- **Principales clientes (emitido + pagado en el período)**: **Cliente**,
  **Facturas** y **Bruto**; si hay importes del programa de contabilidad,
  aparece una columna adicional **de ello programa contable**.
- **Facturas electrónicas entrantes (en el período)**: entradas por estado
  con número e importe bruto, así como el número de las entregadas a
  contabilidad.
- **Validación de entrada & niveles de reclamación**: **Validación
  comprobada**, **Validación superada**, **Validación fallida** y las facturas
  abiertas por nivel de reclamación de 1 a 3.
- **Presupuestos y cadena documental (en el período)**: presupuestos por
  estado, **Tasa de aceptación**, **Mediana creación → decisión** en días,
  **Presupuesto → factura**, **Proforma → factura**, **Anulaciones / abonos**
  y **Tasa de corrección**.

Filtros: **Cliente**, **Proyecto**, **Empleados** e **Incluir clientes
ocultos**. Cliente y proyecto actúan sobre facturas, presupuestos y tiempos;
el empleado solo sobre los tiempos. Las facturas entrantes y los niveles de
reclamación se refieren siempre a toda la organización. Con un proyecto
seleccionado se excluyen los importes del programa de contabilidad, porque
esos comprobantes no tienen proyecto. Exportación en PDF, CSV y Excel.

## Gastos

**Análisis** → **Finanzas y auditoría** → **Gastos** abre el **Informe de
gastos**: gastos por empleado y categoría durante el período, calculados con
importes brutos según la fecha del gasto.

- Gráficos: gastos por mes (o semana o día) por categoría, con las cuatro
  categorías más grandes por separado y el resto agrupado, y **Principales
  generadores (top 15)**.
- Mosaicos: **Suma (bruto)**, **Empleados**, **Categorías** y **Meses**.
- Tabla con una fila por **Empleados** y **Categoría**, una columna por mes y
  la **Suma**, seguida de **Principales categorías**.

Filtros: **Área** (**Solo propios** u **Organización completa**, solo para
administradores), **Empleados**, **Equipo**, **Proyecto** y **Estado**. Sin
derechos de administrador solo ve sus propios gastos. La página no ofrece
exportación.

## Pagos externos

**Análisis** → **Finanzas y auditoría** → **Pagos externos** calcula los
importes que deben pagarse al personal externo en el período. La entrada de
menú aparece con el permiso **Gestionar los datos de personal y nómina**.

Se tienen en cuenta los empleados cuyo **Modelo de retribución** está
configurado como **Tarifa plana** o **Por tiempo**:

- **Tarifa plana** con el intervalo **Mensual**: **Importe a tanto alzado
  (€)** por el número de meses del período.
- **Tarifa plana** con el intervalo **Por intervención**: importe a tanto
  alzado por el número de días con registros de tiempo.
- **Tarifa plana** con el intervalo **Único**: el importe a tanto alzado una
  sola vez.
- **Por tiempo**: tiempo registrado por **Tarifa de retribución (€/h)**.

La tabla muestra **Empleados**, **Modelo**, **Base de cálculo** e **Importe**
con un total general. Los gráficos muestran los pagos a lo largo del tiempo y
**Pagos por externo (top 15)**. Todos los importes son brutos, sin impuestos
ni seguridad social. Filtro: **Empleados**. La página no ofrece exportación.

## Previsión de recargos

**Análisis** → **Equipo** → **Previsión de recargos** estima los minutos de
recargo por mes y tipo salarial a partir de los turnos planificados en el
**Plan de turnos**. La entrada de menú aparece para los administradores y con
el permiso **Ver los informes**.

- **Meses**: 3, 6 o 12 meses a partir del mes en curso.
- **Empleado**: todos los empleados activos o una persona.
- Tabla: **Tipo salarial**, **Regla**, una columna por mes y **Total**, con
  una fila de totales.

El cálculo utiliza las **Reglas de recargo** activas; los turnos cancelados
no cuentan. Es solo una vista previa sin contexto de ubicación: las reglas
que dependen de la ubicación solo se aplican al fichar. La liquidación se
realiza exclusivamente mediante la exportación de tiempos. Exportación en CSV
y Excel.

## Ingresos por producto

**Análisis** → **Finanzas y auditoría** → **Ingresos por producto** muestra
la cantidad, los ingresos netos y la proporción por artículo. La entrada de
menú aparece para los administradores y con el permiso **Ver todos los
registros de tiempo**.

- Base de datos: líneas de facturas locales, facturas de anticipo y facturas
  finales cuya fecha de factura cae en el período y cuyo estado es
  **Emitida**, **Pagada parcialmente** o **Pagada**; los abonos y los
  comprobantes de anulación restan con una cantidad negativa. Se añaden las
  facturas y abonos reflejados del programa de contabilidad. Los
  comprobantes transferidos desde una factura local cuentan solo una vez; los
  borradores y comprobantes anulados, en absoluto.
- Mosaicos: **Ingresos netos totales**, **de ello del programa contable**,
  **Artículos con ingresos** y **Proporción sin referencia de artículo**
  (líneas sin artículo seleccionado).
- Gráfico de los artículos con más ingresos; cuántos muestra lo decide con
  **Top N en el gráfico** (de 3 a 50, por defecto 10). Un clic abre el
  artículo.
- **Ingresos por categoría**: **Categoría**, **Artículo**, **Ingresos netos**
  y **Proporción**.
- Tabla: **Número de artículo**, **Artículo**, **Cantidad**, **Unidad**,
  **Ingresos netos**, **Proporción**, **Comprobantes** y **Fuente**
  (**local** o el nombre de la contabilidad conectada). Las líneas sin
  artículo se agrupan en **sin referencia de artículo**.

Exportación en PDF, CSV y Excel.
