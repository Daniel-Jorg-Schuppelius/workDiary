---
title: "Mis análisis"
topic: reports.my-reports
version: 1
keywords:
    - Mi mes
    - Mi año
    - balance de trabajo
    - horas propias
    - resumen de horas
    - resumen mensual
    - resumen anual
    - comprobar horas extra
    - comparación previsto real
    - imprimir parte de horas
    - saldo
audience: []
related:
    - reports.overview
    - reports.attendance
    - time-accounts.flex
    - attendance.manage
    - time-entries.edit
---

En **Análisis** → **Personal** cada persona dispone de tres análisis de su
propio tiempo: **Mi mes**, **Mi año** y **Balance de trabajo**. Muestran
exclusivamente sus propios registros. Se basan en sus registros de tiempo; el
balance de trabajo utiliza además sus fichajes y su modelo de jornada laboral.
Los análisis no son una fuente de datos propia: si una cifra no es correcta,
corrija el registro de tiempo o el fichaje; el análisis vuelve a calcularse la
próxima vez que lo abra.

## Elegir el período

Las tres páginas se rigen por el período seleccionado en la cabecera. Haga clic
en el icono del calendario (**Seleccionar período**) y elija en **Selección
rápida**, por ejemplo, **Este mes**, **Mes pasado** o **Este año**. Las flechas
**Período anterior** y **Próximo período** retroceden o avanzan un período; en
pantallas grandes también puede introducir en la cabecera una fecha de inicio y
de fin propias y confirmar con **Aplicar**. El período activo se indica en la
barra de filtros de la página.

- **Mi mes** muestra siempre el mes natural en el que empieza el período.
- **Mi año** muestra el año natural en el que empieza el período.
- **Balance de trabajo** evalúa el período exactamente del primer al último día.

## Mi mes

**Análisis** → **Personal** → **Mi mes** enumera día a día todos sus registros
de tiempo del mes.

- Cada día empieza con una fila de cabecera con la fecha, la duración total y
  los ingresos totales del día; los domingos se resaltan en rojo.
- Debajo aparecen los registros con las columnas **Tiempo** (inicio y fin),
  **Tipo**, **Cliente / proyecto**, **Actividad / descripción** (tarea y
  descripción), **Duración** e **Ingresos**. Los ingresos son el importe
  calculado para el registro; el tiempo no facturable figura con 0 €.
- Encima de la tabla están los totales mensuales de horas e ingresos y, al
  final, la fila **Total**.
- Dos gráficos: **Horas por día** como evolución a lo largo del mes y **Horas
  por semana según el tipo**, apiladas por tipo para cada semana natural.

Filtros: **Cliente**, **Proyecto** y **Tipo** con **Todos**, **Trabajo**,
**Viaje** y **Disponibilidad**. Una selección se aplica de inmediato;
**Restablecer** elimina todos los filtros.

Exportación: el botón **PDF** genera la lista diaria con los totales y un
gráfico de las horas por día. El menú **Exportación** ofrece **CSV** y **Excel**
con una fila por registro: fecha, inicio, fin, tipo, cliente, proyecto, tarea,
descripción, minutos e ingresos. Todas las exportaciones aplican los filtros
establecidos.

## Mi año

**Análisis** → **Personal** → **Mi año** muestra sus horas a lo largo de todo el
año natural.

- El recuadro **Total anual** indica las horas del año.
- El mapa de calor **Horas por día** tiene una fila por mes y una columna por
  día (del 1 al 31). Cuanto más intenso es el color de una celda, más horas;
  la escala de color se basa en el valor diario más alto del año. Al pasar el
  cursor aparecen la fecha y las horas, y los domingos están marcados en rojo.
- El gráfico de barras **Horas por mes** muestra los totales mensuales.
- Un clic en el nombre de un mes del mapa de calor o en una barra abre **Mi
  mes** exactamente para ese mes, con los mismos filtros.

Filtros: **Cliente**, **Proyecto** y **Tipo** como en **Mi mes**. Esta página no
ofrece exportación.

## Balance de trabajo

**Análisis** → **Personal** → **Balance de trabajo** compara para el período
elegido el tiempo previsto, la asistencia y el tiempo registrado. Los recuadros
de arriba:

- **Previsto**: tiempo previsto según su modelo de jornada laboral. Los días
  festivos y los días de vacaciones aprobadas no tienen tiempo previsto.
- **Asistencia**: sus fichajes menos las pausas. Los fichajes anulados no
  cuentan; un fichaje todavía en curso se cuenta hasta el momento actual.
- **Registrado**: sus registros de tiempo de los tipos trabajo y viaje. La
  disponibilidad y los registros con la actividad **Pausa** o **Ausencia** no
  cuentan.
- **Sin distribuir**: asistencia que aún no está cubierta por registros de
  tiempo (asistencia menos tiempo registrado, nunca negativa).
- **Saldo**: tiempo registrado menos tiempo previsto; verde si es positivo,
  rojo si es negativo.

Debajo están los gráficos **Horas reales y previstas por día** (para períodos de
más de 62 días **Horas reales y previstas por semana natural**) y **Horas reales
y previstas por mes**, cada uno con una línea de mediana. El bloque
**Distribución por actividad** indica las horas registradas por actividad. La
tabla muestra por día **Fecha**, **Previsto**, **Asistencia**, **Pausa**,
**Registrado**, **Sin distribuir** y **Saldo**, además de la fila **Suma**; se
omiten los días sin tiempo previsto, asistencia ni registros. Un clic en el
encabezado de una columna ordena la tabla.

Exportación: **PDF** con los indicadores y la tabla diaria.

## Quién ve qué

- **Mi mes** y **Mi año** muestran siempre solo sus propios registros, también
  para los administradores.
- **Balance de trabajo** muestra por defecto su propio balance. Solo los
  administradores ven una barra de filtros con **Empleados** y **Equipo** y
  pueden abrir con ella el balance de otra persona de la misma organización;
  **Equipo** solo limita la lista de empleados seleccionables.
- El balance de trabajo solo calcula el período elegido. No muestra el saldo
  acumulado de su cuenta de tiempo de trabajo; para ello consulte «Cuenta de
  tiempo de trabajo y aprobación mensual».
