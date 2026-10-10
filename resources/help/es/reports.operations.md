---
title: "Operaciones: reparto del tiempo, procedimientos, material, guardias"
topic: reports.operations
version: 6
keywords:
    - análisis de operaciones
    - órdenes de servicio
    - análisis por centro de coste
    - desviaciones de procedimiento
    - procedimientos bloqueados
    - consumo de material
    - guardias
    - tasa de defectos
    - horas de proyecto
    - archivar proyectos
    - clasificación faltante
    - análisis de rutas
audience:
    - admin
    - geschaeftsfuehrung
    - teamleitung
    - buchhaltung
    - user
    - aussendienst
related:
    - reports.overview
    - reports.drilldown
    - procedures.run
    - materials.manage
    - duties.overview
    - projects.manage
    - admin.time-dimensions
    - admin.classifications
---

Estos análisis muestran lo que ocurre en la operación diaria: órdenes de
servicio, tareas y rutas, el reparto del tiempo de trabajo entre proyectos,
centros de coste y otras dimensiones, desviaciones y bloqueos en los
procedimientos, el material consumido, los servicios de guardia, los defectos
en los productos y las horas y fases de inactividad de cada proyecto.
Encontrará la mayoría de las páginas en **Análisis** → **Proyectos y
clientes** y **Análisis** → **Recursos**.

## Período, filtros y exportación

- El período se elige con el selector de período de la cabecera. La barra de
  filtros solo lo muestra como indicación; las excepciones se describen en el
  análisis correspondiente.
- Los filtros se aplican en cuanto se seleccionan. El interruptor **Incluir
  clientes ocultos** solo aparece si hay clientes marcados con **Ocultar en
  las evaluaciones**; sin él, sus datos quedan fuera.
- Algunos análisis tienen el campo **Área**. Solo aparece para los
  administradores, que con él cambian entre sus propios datos y todo el
  equipo. Todos los demás ven siempre sus propios datos.
- **PDF** descarga una versión para imprimir; **CSV** y **Excel** están en
  **Exportación**. Las exportaciones conservan los filtros establecidos. Cada
  exportación se registra en el registro de auditoría.
- Las exportaciones requieren el permiso **Exportar los informes**, también en
  **Ejecuciones de procedimiento bloqueadas**; sin él no aparecen los botones
  de exportación. Los administradores pueden exportar siempre. Siguen libres
  las exportaciones que solo contienen sus propios datos; véase «Usar los
  informes».

## Operaciones

**Análisis** → **Proyectos y clientes** → **Operaciones** abre el **Análisis
de operaciones**: órdenes de servicio (órdenes del tipo de orden Service),
tareas y rutas del período.

- Mosaicos: **Órdenes de servicio** con la tasa de cierre (**Cierre**),
  **Tiempo de servicio Σ**, **Tareas** con el número de tareas vencidas y su
  tasa de cierre (el mosaico cambia de color en cuanto una tarea está
  vencida) y **Rutas** con los kilómetros y la duración planificados.
- Gráficos: **Órdenes de servicio: creadas vs. completadas por semana** y
  **Backlog por cliente (top 15)** con las órdenes de servicio aún abiertas
  por cliente. Con el permiso **Ver los informes**, un clic en una barra abre
  los puntos abiertos del cliente; sin este permiso, las barras no se pueden
  pulsar.
- Tablas: **Órdenes de servicio – estado**, **Órdenes de servicio –
  prioridad**, **Tareas – estado**, **Tareas – prioridad** y **Rutas – por
  empleado** (rutas, **Km planificados**, **Duración planificada**).

Las órdenes de servicio se agrupan en cuatro grupos: **Abierto** (planificada
o aceptada), **En curso**, **Problema** (a la espera de respuesta o de
material) y **Hecho** (completada, recepcionada o facturada). Las órdenes
canceladas cuentan en el total, pero ni en un grupo ni en la tasa de cierre.
Lo determinante es la fecha planificada de la orden. Las tareas cuentan si se
crearon, modificaron o vencieron en el período; las tareas archivadas quedan
fuera.

Filtros: **Área**, **Cliente**, **Proyecto**, **Empleado**, **Estado del
pedido** e **Incluir clientes ocultos**. Cliente y proyecto actúan sobre
órdenes de servicio y tareas, el empleado sobre los tres ámbitos; las rutas
no conocen ni cliente ni proyecto. El **Estado del pedido** solo restringe
los mosaicos y tablas de las órdenes de servicio.

Sin derechos de administrador, usted ve las órdenes de servicio que tiene
asignadas, las tareas que tiene asignadas o que ha creado y sus propias
rutas. Exportación en PDF, CSV y Excel.

## Reparto del tiempo

**Análisis** → **Proyectos y clientes** → **Reparto del tiempo** abre la
página **Reparto del tiempo por dimensión**. Muestra cómo se distribuyen los
registros de tiempo repartidos del período entre **Tareas**, **Activos**,
**Proyectos**, **Centros de coste**, **Ubicaciones**, **Vehículos**,
**Actividades** y las dimensiones libres de **Dimensiones de tiempo**.

- Mosaicos: **Tiempo repartido** y el número de **Dimensiones**.
- Una tarjeta por dimensión con **Destino**, **Minutos** (mostrados en horas y
  minutos) y **Registros** (número de registros de tiempo), en orden
  descendente de tiempo.
- La base de datos son exclusivamente las partes del reparto. El tiempo no
  repartido aparece en los demás análisis de tiempo.

La página muestra toda la organización y no tiene más filtros. Aparece en el
menú para los administradores y para los roles con el permiso **Ver los
informes**. Exportación en PDF, CSV y Excel.

## Desviaciones de procedimiento

**Análisis** → **Proyectos y clientes** → **Desviaciones de procedimiento**
evalúa las desviaciones registradas durante la ejecución de procedimientos en
el período. La entrada de menú aparece con el permiso **Ver las desviaciones
de procedimiento**.

- Mosaicos: **Desviaciones**, **Críticas**, **Cuota con seguimiento** (parte
  con un punto abierto o una orden de seguimiento) y **Ø horas hasta la
  decisión** (desde la creación hasta la aceptación del riesgo, solo
  desviaciones decididas).
- Gráficos: **Desviaciones por tipo**, las desviaciones a lo largo del tiempo
  por gravedad y **Procedimientos con más desviaciones (top 10)**.
- Lista: **Fecha**, **Procedimiento**, **Paso**, **Tipo**, **Gravedad**,
  **Seguimiento** (**Punto abierto** u **Orden de seguimiento**), **Riesgo
  aceptado el** y **Horas hasta decisión**. El icono al final de la fila abre
  la ejecución del procedimiento.

Filtros: **Procedimiento**, **Tipo**, **Gravedad**, **Riesgo aceptado**
(**Solo aceptadas** o **Solo abiertas**) y **Medida de seguimiento** (**Con
punto abierto/orden de seguimiento** o **Sin seguimiento**). Exportación en
PDF, CSV y Excel; CSV y Excel contienen además la acción propuesta y el
motivo.

## Ejecuciones de procedimiento bloqueadas

**Análisis** → **Proyectos y clientes** → **Ejecuciones de procedimiento
bloqueadas** muestra las ejecuciones que esperan un tiempo de espera, una
segunda persona o una decisión de riesgo. La entrada de menú aparece con el
permiso **Ver las ejecuciones de procedimiento**.

- **Bloqueadas actualmente**: **Procedimiento**, **Motivo del bloqueo**,
  **Bloqueada desde** y **Horas**, con acceso directo a la ejecución. Los
  motivos de bloqueo son una desviación crítica sin decisión de riesgo, un
  tiempo de espera que aún no ha transcurrido y una segunda persona que
  falta. Al abrir la página se liberan los tiempos de espera transcurridos.
- **Bloqueos finalizados en el periodo**: por motivo de bloqueo y
  procedimiento **Cantidad**, **Horas medias** y **Más largo (h)**.

Aquí el período se ajusta en el campo desde–hasta de la barra de filtros; sin
indicación propia rige el selector de período de la cabecera. Exportación en
CSV y Excel; contiene los bloqueos finalizados.

## Materiales

**Análisis** → **Recursos** → **Materiales** abre la página **Consumo de
material**. Se basa en las líneas de material de las hojas de horas cuyo día
de trabajo cae en el período.

- Gráficos: **Valor de consumo por material (top 20)** y los costes de
  material a lo largo del tiempo.
- Mosaicos: **Materiales**, **Usos** y **Neto Σ**.
- Tabla **Consumo por material**: **SKU**, **Material**, **Unidad**,
  **Cantidad**, **Usos** y **Neto**, en orden descendente de importe neto. El
  mismo material en unidades distintas aparece en filas separadas; las líneas
  sin ficha de material aparecen con su descripción.

Filtros: **Área**, **Cliente**, **Proyecto** e **Incluir clientes ocultos**;
el cliente actúa a través del proyecto de la hoja de horas. Sin derechos de
administrador solo ve sus propias hojas de horas. Exportación en PDF, CSV y
Excel.

## Servicio de urgencia

**Análisis** → **Recursos** → **Servicio de urgencia** abre el **Análisis de
servicio de urgencia** con los turnos de guardia y las intervenciones reales
por empleado, tal como se gestionan en la **Lista de trabajo**. Los tiempos
que superan el período solo cuentan de forma proporcional; las entradas
archivadas no cuentan.

- Mosaicos: **Empleados**, **Disponibilidad** (con el número de turnos),
  **Intervenciones activas** (tiempo de intervención con el número de
  intervenciones) y **Proporción activa** (tiempo de intervención en relación
  con el tiempo de guardia).
- Gráficos: **Guardia por empleado y semana** como mapa de calor y las
  intervenciones a lo largo del tiempo.
- Tabla por empleado: **Turnos**, **Disponibilidad**, **Intervenciones**,
  **Tiempo de intervención** y **Proporción activa** con una fila de totales.

Filtros: **Área** (**Solo mi disponibilidad** o **Equipo completo**, solo
para administradores), **Empleado** y **Equipo**. Exportación en PDF (con el
mapa de calor), CSV y Excel.

## Análisis de productos

**Análisis** → **Proyectos y clientes** → **Análisis de productos** muestra
defectos, puntos abiertos y esfuerzo por activo, grupo de productos o modelo.
La entrada de menú aparece para los administradores y con el permiso **Ver
los informes**.

- **Nivel**: **Por activo**, **Por grupo de productos** o **Por modelo**;
  otros filtros son **Grupo de productos**, **Fabricante**, **Cliente** e
  **Incluir clientes ocultos**.
- Columnas: **Activos**, **Órdenes** (órdenes del activo creadas en el
  período), **Puntos abiertos** (abiertos actualmente, con independencia del
  período), **Escalado** (puntos abiertos con el estado **Bloqueado**),
  **Defectos** (actas de defecto de esas órdenes en el período), **Tasa de
  defectos %** (defectos en relación con las órdenes) y **Último incidente**.
- Si en el período hay registros de tiempo de sesiones de mantenimiento
  remoto para los equipos, se añaden **Sesiones de mantenimiento** y
  **Tiempo de mantenimiento** y un gráfico del tiempo de mantenimiento.
- Gráficos: **Defectos en el período (top 20)** y **Tasa de defectos (top
  15)**. Las cifras de puntos abiertos, escalados y defectos y las barras
  llevan a las listas de detalle correspondientes.

Exportación en PDF, CSV y Excel.

## Detalles del proyecto

**Análisis** → **Proyectos y clientes** → **Detalles del proyecto** muestra
las horas y los ingresos de un solo proyecto por mes. El análisis abarca el
año natural en el que comienza el período elegido.

- Elija **Cliente** y **Proyecto**. Sin selección aparece el primer proyecto
  de la lista. El filtro **Empleado** solo existe con una vista de tiempos de
  toda la organización.
- La tarjeta del proyecto indica los totales anuales **Σ h** y **Σ €** y
  enumera **Mes**, **Horas** e **Ingresos**; a continuación figura la
  **Distribución por empleado**. Los ingresos son la suma de los importes
  guardados con los registros de tiempo.
- Gráficos: **Evolución de horas en el período**, **Horas reales y planificadas
  por mes** (plan a partir del campo **Duración prevista (HH:MM)** de las
  órdenes del proyecto según su inicio, a falta de él de la duración del servicio de una orden planificada; si no, de la duración de
  la franja horaria o de la cita; con un empleado seleccionado, solo las órdenes
  asignadas a esa persona; sin vista de tiempos de toda la organización, solo
  las asignadas a usted; sin datos de plan, una línea muestra la mediana de los
  meses reales) y **Horas por tipo de pedido y mes**.

Los administradores y los roles con **Ver todos los registros de tiempo** ven
todos los proyectos y todas las horas. Todos los demás solo ven los proyectos
en los que han registrado tiempo personalmente, y allí solo sus propias
horas. Exportación en PDF, CSV y Excel en cuanto hay un proyecto
seleccionado.

## Proyectos inactivos

**Análisis** → **Proyectos y clientes** → **Proyectos inactivos** enumera
todos los proyectos no archivados de la organización en los que no se
registró tiempo en el período.

- Gráfico **Proyectos por duración de inactividad**: **≤ 3 meses**, **3–6
  meses**, **6–12 meses**, **> 12 meses** y **Sin anotaciones**, medido desde
  el último registro de tiempo hasta el final del período.
- Tabla: **Proyecto**, **Cliente**, **Estado** y **Última actividad** (el
  registro de tiempo más reciente de todos).
- Filtro: **Cliente**.

Para poner orden, marque proyectos y elija **Archivar seleccionados**;
confirme la pregunta con **Archivar**. Solo se archivan los proyectos que
usted mismo ha creado; los administradores pueden archivarlos todos. El
mensaje indica el número de proyectos archivados realmente. Exportación en
CSV y Excel.

## Calidad de los datos

**Análisis** → **Proyectos y clientes** → **Calidad de los datos** abre la
página **Calidad de datos: clasificaciones obligatorias**. Enumera las órdenes
del período a las que les faltan datos exigidos por las reglas obligatorias de
**Clasificaciones**. La entrada de menú y la página exigen el permiso **Ver
los informes**.

- Mosaicos: **Órdenes con huecos**, **Lagunas bloqueantes** (reglas
  bloqueantes) y **Brechas leves** (avisos).
- Gráficos: las órdenes con huecos de clasificación a lo largo del tiempo y
  **Clasificaciones faltantes por cliente (top 15)**.
- **Por dominio** y **Por fase**: dónde están los huecos y a partir de qué
  fase se exige el dato (al crear, antes de completar o antes de firmar).
- **Órdenes afectadas**: **Orden**, **Fecha** y **Clasificaciones faltantes**
  (rojo = bloqueante, amarillo = leve). **Registrar a posteriori** abre la
  orden.

Filtros: **Cliente**, **Proyecto**, **Tipo de orden** e **Incluir clientes
ocultos**. Se comprueban como máximo las 1.000 órdenes no archivadas más
recientes del período. La página no modifica nada y no ofrece exportación.
