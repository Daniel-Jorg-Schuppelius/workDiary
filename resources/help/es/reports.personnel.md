---
title: "Personal: vacaciones, enfermedad, cualificaciones, seguridad"
topic: reports.personnel
version: 6
keywords:
    - absentismo
    - vacaciones restantes
    - informe de vacaciones
    - días de baja
    - mantenimiento del salario
    - continuación de la enfermedad
    - parte de baja
    - matriz de cualificaciones
    - certificados por vencer
    - analizar accidentes
    - cuasiaccidente
    - necesidades de formación
    - alertas tempranas
audience:
    - admin
    - geschaeftsfuehrung
    - personalverwaltung
    - teamleitung
related:
    - reports.overview
    - absences.manage
    - reports.absence-calendar
    - time-accounts.flex
    - catalog.qualifications
    - safety.overview
    - learning.overview
---

Estos análisis resumen datos personales: vacaciones y horario flexible,
enfermedad y mantenimiento del salario, cualificaciones con fechas de
vencimiento, eventos de seguridad, así como problemas recurrentes y
necesidades de formación. Los encontrará en **Análisis** → **Equipo**
(**Vacaciones y flex**, **Enfermedades**, **Cualificaciones**, **Seguridad
laboral**) y en **Análisis** → **Proyectos y clientes** (**Problemas y
formación**). Se trata de datos especialmente sensibles: comparta las cifras
solo con las personas que las necesiten para su tarea. Las correcciones se
hacen en la solicitud de vacaciones, en la baja por enfermedad, en la
cualificación de la persona o en el evento de seguridad.

## Vacaciones y flex

**Análisis** → **Equipo** → **Vacaciones y flex** muestra ausencias y horario
flexible por persona para el período seleccionado en la cabecera. Se cuentan
días laborables: de lunes a viernes sin festivos, ajustados al período.

Columnas por persona:

- **Vacaciones**, **Especial** y **No pagado**: días laborables de solicitudes
  aprobadas del tipo correspondiente.
- **Enfermedad**: días laborables de bajas por enfermedad; las bajas anuladas no
  cuentan.
- **Pendiente**: días laborables de solicitudes aún sin decidir, resaltados en
  color.
- **Derecho** y **Restante** con el año: la cuenta de vacaciones del año en el
  que termina el período. El derecho incluye el derecho básico, las vacaciones
  adicionales y el remanente utilizable; lo restante solo descuenta los días
  aprobados y se muestra en rojo si es negativo. Sin derecho registrado aparece
  «–».
- **Flex Δ**: variación del saldo de horario flexible en los meses del período
  (real menos previsto según los cierres mensuales de la cuenta de tiempo).
- **Saldo flex**: el último cierre mensual hasta el final del período.

Recuadros: **Empleados**, **Vacaciones (días laborables)** con los días
pendientes, **Enfermedad**, **Especial / no pagado** y **Cambio flex Σ**. El
gráfico **Días de ausencia por mes por tipo** apila vacaciones, enfermedad,
especial y no pagado; según la duración del período se calcula por día, por
semana o por trimestre. **Vacaciones restantes por empleado (top 15)** muestra
los saldos restantes más altos.

Los filtros **Área** (**Solo propios** o **Equipo completo** para todas las
personas de la organización), **Empleado**, **Equipo** y **Estado** los ven
los administradores y las personas con el permiso **Ver todas las solicitudes
de vacaciones**; las demás solo ven su propia fila. Con **Estado** solo cuentan
las solicitudes **Pendiente** o **Aprobado**. Exportación: **PDF** con
gráfico, **CSV** y **Excel**.

La columna **Enfermedad**, su recuadro y su parte en el gráfico solo muestran los valores de otras personas si usted dispone además del permiso **Ver las bajas por enfermedad**; de lo contrario, no aparecen en la vista ni en la exportación.

## Enfermedades

**Análisis** → **Equipo** → **Enfermedades** abre el **Informe de enfermedad**
para el período seleccionado en la cabecera. Las bajas anuladas no cuentan.

Columnas por persona con bajas en el período:

- **Días laborables** y **Días naturales**: días de baja en el período, una vez
  sin fines de semana ni festivos y otra como días naturales.
- **Casos**: número de bajas; **Seguimiento**: de ellas, partes de confirmación.
- **Con baja**: bajas con un certificado subido, en relación con todos los
  casos.
- **Mantenimiento del salario**: días consumidos respecto al derecho como
  barra: verde, naranja a partir del 75 % y roja cuando se agota.
- **Estado**: **Agotado** con la fecha en la que termina el derecho; si no, los
  días libres restantes u **OK**. Bajo el nombre, **Cadena desde** indica el
  inicio de la cadena de enfermedad en curso.

Cálculo del mantenimiento del salario:

- En la configuración estándar el derecho es de seis semanas, es decir, 42 días
  naturales de incapacidad laboral. Solo cuentan los propios días de baja – en
  una enfermedad en curso, hasta hoy; los días trabajados entre dos bajas no
  cuentan nunca.
- Las bajas que se solapan, se suceden sin interrupción o están vinculadas como
  parte de confirmación forman un único caso de enfermedad. Esto también se
  aplica cuando durante una enfermedad en curso aparece una nueva.
- Una enfermedad nueva que solo empieza después de días trabajados parte con el
  derecho completo.
- Si la caja de seguro de enfermedad confirma la misma enfermedad
  (continuación de la enfermedad), en la nueva baja se elige la baja anterior
  en el campo **Continuación de la enfermedad del**. Los casos comparten
  entonces un único derecho. Para la misma enfermedad nace un nuevo derecho si
  la persona, en la configuración estándar, no ha estado incapacitada por esa
  enfermedad durante seis meses, o si han pasado doce meses desde el inicio de
  la primera incapacidad.
- **Cadena desde** indica el inicio del primer caso que cuenta para el derecho
  en curso.

Las columnas **Mantenimiento del salario** y **Estado** muestran la situación
de hoy, independientemente del período elegido. Los valores son orientativos y
no constituyen una comprobación jurídica ni asesoramiento legal.

Recuadros: **Empleados**, **Días laborables de baja** con los días naturales,
**Casos de enfermedad** con los partes de confirmación, **Con baja** y
**Derecho agotado**. Gráficos: **Días de baja por mes** con línea de mediana
(por día, semana o trimestre según el período) y el mapa de calor **Días de
baja por empleado y mes**.

Filtros como en **Vacaciones y flex**: **Área**, **Empleado** y **Equipo** –
aquí para los administradores y las personas con el permiso **Ver las bajas por
enfermedad**; las demás solo ven su propia fila. Esta página no ofrece
exportación.

## Cualificaciones

**Análisis** → **Equipo** → **Cualificaciones** muestra la **Matriz de
cualificaciones**: una fila por persona con al menos una cualificación activa
y una columna por cada cualificación activa del catálogo (abreviatura; nombre
completo al pasar el cursor). Las cualificaciones inactivas no aparecen ni en
la matriz ni en los mosaicos, gráficos y exportación.

- Cada celda muestra la fecha de vencimiento, o ✓ si la cualificación es válida
  sin fecha de vencimiento.
- Colores: verde **válido**, naranja **caduca en 30 días**, rojo **caducado**,
  gris **sin asignación**. La leyenda está debajo de la matriz.
- Recuadros: **Empleados**, **Cualificaciones**, **Asignaciones**, **Vencen
  (≤30 d.)** y **Caducado**.
- Gráficos: **Titulares por cualificación (top 15)** y **Asignaciones por
  cualificación según estado** para las doce cualificaciones más frecuentes.

La fecha de referencia es siempre hoy; el período de la cabecera no cambia la
matriz. Las filas de todas las personas las ven los administradores y las
personas con el permiso **Gestionar las cualificaciones**; las demás solo ven
su propia fila. Los filtros **Empleado** y **Equipo** solo están disponibles con este permiso. Exportación: **PDF** en
horizontal, **CSV** y **Excel** con una fila por persona y, por cualificación,
la fecha de vencimiento o una indicación de validez. Las cualificaciones se
gestionan en el catálogo y en la ficha de la persona, no en el análisis.

## Seguridad laboral

**Análisis** → **Equipo** → **Seguridad laboral** analiza todos los eventos
de seguridad ocurridos en el período de la cabecera.

- Recuadros: **Eventos totales**, **Abiertos** (todos los no cerrados),
  **Cerrados** y **Críticos** (gravedad crítica).
- Gráficos: **Eventos por mes** con la segunda serie **de ellos cerrados** y
  **Eventos por mes por estado**, apilados según **Notificado**, **En
  investigación**, **Medidas definidas** y **Cerrado**; por día, semana o
  trimestre según el período.
- **Por tipo** cuenta **Accidente**, **Cuasiaccidente**, **Peligro** y
  **Defecto**; **Por gravedad** cuenta **Baja**, **Media**, **Alta** y
  **Crítica**.

Filtros: **Empleado** y **Equipo**; se refieren a la persona que notificó el
evento. No hay exportación; los eventos individuales se gestionan en el
registro de eventos de seguridad.

## Problemas y formación

**Análisis** → **Proyectos y clientes** → **Problemas y formación** abre el
**Análisis de dirección**. Muestra la situación actual, sin período, filtros ni
exportación.

La tarjeta **Problemas recurrentes** reúne las alertas tempranas de los módulos
que utiliza su organización, agrupadas por tipo:

- **Retrabajos por cliente**: clientes cuya proporción de retrabajos de los
  últimos 90 días no alcanza el valor objetivo. Sin valor objetivo registrado
  (consulte «Valores objetivo (informes)») no se genera ninguna alerta.
- **Defectos recurrentes**: objetos con defectos repetidos en los últimos doce
  meses.
- **Patrones de reclamación**: reclamaciones llamativamente frecuentes.
- **Tickets recurrentes**: clientes u objetos con muchos tickets en la ventana
  de tiempo; el umbral y la ventana son ajustes de la organización, por
  defecto tres tickets en 90 días.
- **Falta de personal**: equipos cuya demanda planificada supera la capacidad
  en las próximas cuatro semanas.

Cada entrada indica el hallazgo, un detalle y una recomendación; cuando es
posible, el título lleva al cliente, objeto o informe afectado. Sin hallazgos
aparece **No hay incidencias destacadas.**

La tarjeta **Necesidades de formación** enumera por **Competencia** las
**Personas con carencia**, la **Carencia media (niveles)** y los **Cursos
adecuados** (cursos publicados que transmiten la competencia). Se basa en los
requisitos de competencias por rol de la plataforma de aprendizaje; los
certificados caducados no cuentan. Sin plataforma de aprendizaje o sin
requisitos de competencias, la tabla queda vacía.

## Quién ve qué

- **Vacaciones y flex**, **Enfermedades** y **Cualificaciones**: sin otro
  permiso, cada persona solo ve sus propios datos. La vista de todas las
  personas de la organización la tienen los administradores y, en cada
  análisis, quien tenga el permiso de la lista correspondiente: **Ver todas las
  solicitudes de vacaciones** para **Vacaciones y flex**, **Ver las bajas por
  enfermedad** para **Enfermedades** y **Gestionar las cualificaciones** para
  **Cualificaciones**.
- **Seguridad laboral**: entrada de menú y página solo con el permiso **Ver el
  registro de eventos de seguridad** o **Editar / cerrar eventos de
  seguridad**; los administradores las ven siempre. En la asignación estándar,
  Jefe de equipo y Dirección tienen el permiso de lectura.
- **Problemas y formación**: solo con el permiso **Ver los informes** o como
  administrador. En la asignación estándar lo tienen, entre otros, Dirección,
  Jefe de equipo y Administración de personal.
- **Vacaciones y flex**, **Enfermedades** y **Cualificaciones** requieren el
  módulo adicional de informes de equipo; **Seguridad laboral** y **Problemas y
  formación** están disponibles sin él.
