---
title: "Cuentas de tiempo"
topic: time-accounts.overview
version: 2
keywords:
    - cuenta adicional
    - cuenta de tiempo libre
    - descanso compensatorio
    - contador de turnos nocturnos
    - horas de pluses
    - saldo de la cuenta
    - semáforo
    - diario de movimientos
    - contraasiento
    - exportar cuentas
    - trabajo adicional
    - comparación de períodos
audience: []
related:
    - time-accounts.flex
---

Las cuentas de tiempo adicionales llevan magnitudes de tiempo
seleccionadas como cuentas propias: por ejemplo un contador de turnos
nocturnos realizados, una cuenta de tiempo libre por trabajo adicional u
horas de pluses acumuladas. El horario flexible y las vacaciones siguen en
la cuenta de tiempo de trabajo.

El resumen muestra por cuenta el saldo actual con semáforo (los umbrales
los define la organización), el promedio mensual y una tendencia simple.
«Ver diario» muestra cada asiento con fecha, cantidad, fuente y nota — las
correcciones aparecen como contraasientos, nada se sobrescribe.

La evaluación (para roles directivos) compara saldo inicial, movimiento y
saldo final por empleado en un periodo y se exporta como CSV o PDF.

## Comparación de períodos

La comparación de períodos muestra lado a lado los asientos de una cuenta de
tiempo por semana del calendario o por mes. La encontrará en **Análisis** →
**Equipo** → **Comparación de períodos**.

- En la barra de filtros elige la **Cuenta** (todas las cuentas de tiempo
  activas) y la **Granularidad**: **Semana del calendario** (por defecto) o
  **Mes**. La selección se aplica de inmediato.
- El período sigue el filtro de fechas de la cabecera. Se muestran como
  máximo 53 columnas, es decir, un año en semanas.
- Para cada empleado, la tabla muestra el **Saldo inicial** (suma de todos los
  asientos anteriores al período), la suma por semana o por mes, el
  **Movimiento** del período y el **Saldo final**. El saldo final lleva el color del semáforo de la cuenta. Todos los
  valores se muestran en la unidad de la cuenta.
- No aparecen las personas cuyo saldo inicial y movimiento son ambos cero. Si
  en el período no hay ningún valor, la página indica «Sin asientos en el
  periodo seleccionado.»

**Exportación:** **PDF** y, en **Exportación**, los formatos **CSV** y
**Excel**. El PDF y el CSV contienen la cuenta elegida. Excel entrega todas las
cuentas activas, cada una en una hoja del mismo libro.

**Visibilidad:** el rol **Administrador** ve a todos los empleados de la
organización; las demás personas solo ven su propia fila. Si no hay cuentas de
tiempo activas configuradas, la página muestra el aviso «No hay cuentas de
tiempo configuradas».
