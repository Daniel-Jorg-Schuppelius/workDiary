---
title: "Asistencia, plan/real y cobertura"
topic: reports.attendance
version: 1
keywords:
    - informe de asistencia
    - analizar fichajes
    - comparación plan real
    - dotación prevista y real
    - falta de personal
    - cobertura de turnos
    - dotación mínima
    - retraso
    - horario fijo
    - informe mensual del equipo
    - horas por empleado
    - días-persona
audience:
    - admin
    - geschaeftsfuehrung
    - personalverwaltung
    - teamleitung
related:
    - reports.overview
    - reports.my-reports
    - attendance.manage
    - planning.shifts
    - reports.utilization
    - reports.presence-emergency
---

Estos análisis comparan quién estuvo presente y cuándo con lo que estaba
previsto: los fichajes con el modelo de jornada laboral, los turnos con la
dotación prevista y el tiempo planificado de las órdenes con el tiempo
registrado. Abarcan **Asistencia** y **Plan/real** en el área de menú
**Análisis** → **Personal**, así como **Cobertura** y **Mes por empleado** en
**Análisis** → **Equipo**. Todas las páginas muestran solo datos de la
organización activa. Las correcciones se hacen en el fichaje, en el registro de
tiempo, en el modelo de jornada o en el plan de turnos; el análisis no es una
fuente de datos propia.

## Asistencia

**Análisis** → **Personal** → **Asistencia** abre el **Análisis de asistencia**
para el período seleccionado en la cabecera (icono del calendario
**Seleccionar período**).

La tabla tiene una fila por persona y las columnas:

- **Días laborables** y **Previsto**: días y tiempo previsto según el modelo de
  jornada laboral por día de la semana. Aquí los festivos y las vacaciones no
  reducen el tiempo previsto, a diferencia de **Balance de trabajo**. Sin
  modelo de jornada ambos valores son 0.
- **Presente**: fichajes cerrados tras descontar las pausas; los fichajes en
  curso y los anulados no cuentan.
- **Registrado**: todos los registros de tiempo del período, de cualquier tipo.
- **Saldo**: presente menos previsto, en rojo si es negativo y en verde si es
  positivo.

La fila **Total** y los recuadros **Previsto**, **Presente**, **Registrado** y
**Saldo** resumen a todas las personas mostradas. El mapa de calor **Presencia
por empleado y día de la semana** muestra qué días de la semana estuvo presente
cada persona y durante cuánto tiempo; **Presencia a lo largo del tiempo**
muestra el total por día, o por semana natural en períodos de más de 62 días.
Un clic en el encabezado de una columna ordena la tabla.

Los filtros solo están disponibles para los administradores: **Área** con
**Solo propios** o **Equipo completo** (todas las personas de la organización),
además de **Empleados** y **Equipo**. Todas las demás personas solo ven su
propia fila.

Exportación: **PDF** con la tabla y el mapa de calor; en el menú
**Exportación**, **CSV** y **Excel** con días laborables, previsto, presente,
reservado y saldo en minutos por persona y una fila de total.

## Plan/real

**Análisis** → **Personal** → **Plan/real** compara lo previsto y lo real en
varias vistas, que se cambian con las pestañas de la parte superior de la
página: **Asistencia**, **Equipo**, **Organización**, **Turnos**,
**Proyectos** y **Ubicaciones**. Solo ve las pestañas para las que tiene
permiso.

Aquí el período no se toma de la cabecera: ajústelo en la barra de filtros con
**Desde** y **Hasta**. Sin indicación se aplica el mes en curso; el período se
mantiene al cambiar de pestaña. Estas páginas no ofrecen exportación.

### Pestaña Asistencia

La página **Asistencia plan/real** muestra sus propios días con los recuadros
**Plan**, **Real**, **Δ** y **Advertencias** y, por día, las columnas:

- **Plan**: tiempo previsto según el modelo de jornada para el día de la
  semana; «—» en días sin modelo de jornada o sin jornada laboral.
- **Real**: la asistencia fichada del día.
- **Δ**: real menos plan, valores negativos en rojo.
- **Inicio P/R**: inicio del horario fijo según el modelo de jornada y primer
  fichaje del día, seguidos de la desviación en minutos.
- **Advertencias**: inicio tardío, cuando el primer fichaje es más de 15
  minutos posterior al inicio del horario fijo, y desviación de horas, cuando
  lo real difiere del plan en más de un 10 %. Los días sin plan no reciben
  advertencias.

Si abre la página para otra persona desde la pestaña **Equipo** u
**Organización**, arriba aparece la indicación **Vista para** con su nombre.

### Pestañas Equipo y Organización

**Equipo** muestra los miembros de uno de sus equipos; si tiene varios, elija
uno en el campo **Equipo**. Los administradores y las personas con el permiso
de organización pueden elegir cualquier equipo no archivado. **Organización**
muestra a todas las personas de la organización en **Todos los empleados**.

Ambas vistas suman por persona **Plan (h)**, **Real (h)**, **Diferencia (h)** y
**Advertencias** con la misma lógica diaria que la pestaña **Asistencia**. La
lupa **Detalles** abre la vista diaria de la persona para el mismo período. Con
el permiso de equipo esto solo es posible para miembros de sus propios equipos.

### Pestañas Turnos, Proyectos y Ubicaciones

- **Turnos**: lo previsto son los turnos publicados y confirmados del plan de
  turnos con la duración de su franja horaria (incluidos los turnos de noche
  que pasan de medianoche); lo real es la coincidencia de los fichajes de la
  persona asignada con esa franja. Recuadros **Plan**, **Real**,
  **Diferencia** y **Cobertura** (real respecto a lo previsto; resaltada por
  debajo del 100 %). Con **Agrupación** elige **Diario** o **Semanal** para el
  gráfico **Plan vs. real por día** o **Plan vs. real por semana**. La tabla
  **Por tipo de turno** indica **Turnos**, **Plan (h)**, **Real (h)**,
  **Diferencia (h)** y **Cobertura**. Los turnos sin franja horaria están
  marcados con **sin franja horaria**: no tienen previsto y como real cuenta la
  asistencia diaria de la persona.
- **Proyectos**: lo previsto es la suma de los minutos planificados de las
  órdenes cuyo período toca el período elegido; lo real es el tiempo registrado
  por proyecto. Las órdenes reciben minutos planificados, por ejemplo, al
  confirmar citas de Calendly. Recuadros **Plan**, **Real**, **Diferencia** y
  **Facturable (real)**, el gráfico **Proyectos principales: plan vs. real**
  (los doce proyectos con más horas reales) y la tabla **Por proyecto** con
  **Proyecto**, **Cliente**, **Órdenes (planificadas)**, **Plan (h)**, **Real
  (h)**, **Facturable (h)** y **Diferencia (h)**. Los proyectos sin órdenes
  planificadas llevan la indicación **sin datos previstos**; no es una alarma.
  El tiempo sin proyecto aparece en la fila **Sin proyecto**. Las comparaciones
  con presupuestos de tiempo y de dinero las ofrece **Rentabilidad**.
- **Ubicaciones**: para las ubicaciones no hay datos previstos; la vista solo
  muestra la distribución real del tiempo procedente del registro de tiempo
  basado en la ubicación. Recuadros **Real**, **Visitas al sitio** y
  **Personas**, gráfico **Tiempos reales por ubicación** y tabla **Por
  ubicación** con **Ubicación**, **Cliente**, **Visitas al sitio**,
  **Personas**, **Real (h)** y **Proporción**. Las visitas a geocercas sin
  ubicación asignada figuran en **Sin asignación de ubicación** con la
  indicación **Geocerca sin ubicación**.

Las tablas largas de las pestañas **Proyectos** y **Ubicaciones** se reparten en
páginas de 50 filas cada una.

## Cobertura

**Análisis** → **Equipo** → **Cobertura** compara la dotación prevista con la
real: ¿cumplen los turnos planificados la dotación prevista?

- Lo previsto es el valor mínimo (**Mín**) de la **Dotación prevista** que usted
  define en el plan de servicio por tipo de turno. Una entrada para una fecha
  concreta prevalece sobre una entrada para el día de la semana, y esta sobre
  una entrada general. Los tipos de turno sin dotación prevista no aparecen.
- Lo real es el número de turnos planificados por tipo de turno y día; cuentan
  todos los turnos no anulados, incluidos los borradores.
- Se cuenta en días-persona: un turno de una persona en un día equivale a un
  día-persona.

Recuadros: **Tipos de turno** (con el número de días evaluados), **Previsto
(días-persona)**, **Real (días-persona)** con la diferencia, **Cumplimiento**
(real respecto a lo previsto) y **Días con déficit de cobertura**. El mapa de
calor **Grado de cobertura por tipo de turno y día de la semana** muestra en
cada celda real/previsto y el porcentaje; **Días-persona faltantes por semana**
muestra los huecos por semana natural. La tabla **Por tipo de turno** indica
**Previsto**, **Real**, **Diferencia**, **Cumplimiento** y **Días por debajo**;
debajo, **Días con déficit de cobertura** enumera cada día afectado con
**Fecha**, **Tipo de turno**, **Previsto**, **Real** y **Hueco**.

El período es el de la cabecera, como máximo 400 días; un período más largo se
corta tras 400 días. Filtro **Equipo**: solo cuenta los turnos de los miembros
del equipo; lo previsto no cambia. Exportación: **PDF** con el mapa de calor y
los días con déficit, **CSV** y **Excel** con los valores por tipo de turno.

## Mes por empleado

**Análisis** → **Equipo** → **Mes por empleado** (título de la página **Informe
mensual del equipo**) muestra las horas registradas de todas las personas a lo
largo de un año natural: el año en el que empieza el período de la cabecera.

- La tabla tiene una fila por persona con registros de tiempo en el año, una
  columna por mes, el total anual de horas y el total de ingresos en euros; la
  última fila suma cada mes.
- Encima de la tabla están los totales anuales de horas e ingresos.
- El gráfico **Horas por empleado** muestra el total anual de cada persona con
  una línea de mediana; el mapa de calor **Horas por empleado y mes**, la
  distribución a lo largo del año.
- Cuentan todos los registros de tiempo, de cualquier tipo.

Filtros: **Empleados** y **Equipo**. Exportación: **PDF** en horizontal con mapa
de calor, **CSV** y **Excel**.

## Quién ve qué

- **Asistencia**: cada persona ve su propia fila; los administradores, toda la
  organización.
- **Plan/real**: todas las personas ven la pestaña **Asistencia** con sus
  propios días. La pestaña **Equipo** requiere el permiso **Ver informe de
  presencia (equipo)**; las pestañas **Organización**, **Turnos**,
  **Proyectos** y **Ubicaciones**, el permiso **Ver informe de presencia
  (organización)**. Los administradores ven todas las pestañas. En la
  asignación estándar, el rol Jefe de equipo tiene el permiso de equipo, la
  Dirección el de organización y la Administración de personal ambos.
- **Cobertura** y **Mes por empleado** están reservados a los administradores.
  Las demás personas ven las entradas del menú, pero al abrirlas reciben un
  aviso de falta de permiso.
- El área de menú **Equipo** solo existe si está contratado el módulo adicional
  de informes de equipo.
