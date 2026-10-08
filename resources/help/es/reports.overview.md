---
title: "Usar los informes"
topic: reports.overview
version: 3
keywords:
    - estadísticas
    - indicadores
    - KPI
    - analítica
    - exportar informe
    - ventas por producto
    - control del salario mínimo
    - tiempos de conducción y descanso
    - previsión de liquidez
    - reporting
    - evaluaciones
    - encontrar un informe
audience: []
related:
    - reports.my-reports
    - reports.attendance
    - reports.personnel
    - reports.operations
    - reports.fleet
    - reports.billing
    - reports.compliance
    - reports.customer-analysis
    - reports.entry-type-analysis
    - reports.drilldown
    - reports.saved-views
    - exports.payroll
---

El área de menú **Análisis** de la barra lateral reúne todos los informes, desde
su propio resumen mensual hasta los informes financieros y de auditoría. Los
análisis condensan datos existentes, como registros de tiempo, fichajes,
ausencias, órdenes y facturas, por período, persona, equipo, proyecto o
cliente. No son una fuente de datos propia: las correcciones se hacen en la
orden, el tiempo, la ausencia o el dato maestro originales. En los análisis
personales y financieros rige el principio de necesidad de conocer. Este tema
explica cómo están estructurados los análisis y le remite a los temas de cada
informe.

## La página Vista general

**Análisis** → **Vista general** muestra arriba sus propios indicadores para el
período de la cabecera: **Mis horas**, **Días registrados**, **Ø por día**
(referido a los días registrados) y **Proyectos activos** (proyectos en los que
ha registrado tiempo). El primer gráfico muestra sus horas: hasta 31 días como
**Horas por día**, hasta aproximadamente medio año como **Horas por semana** y,
por encima, como **Horas por mes**. **Proyectos principales por horas** indica
sus diez proyectos con más horas.

Debajo hay una tarjeta por grupo de menú con todos los análisis que puede
abrir. La selección coincide exactamente con la barra lateral.

## Menú y visibilidad

Los análisis están ordenados en grupos: **Vista general**, **Personal**,
**Equipo**, **Proyectos y clientes**, **Recursos** y **Finanzas y auditoría**.
Una entrada solo aparece si su organización usa el módulo correspondiente y
usted tiene el permiso necesario. Los grupos **Equipo**, **Proyectos y
clientes** y **Recursos** requieren el módulo adicional de informes de equipo.
Las entradas que haya ocultado mediante «Personalizar menú y Todas las
funciones» tampoco aparecen en la página de vista general.

## Período

La mayoría de los análisis se rigen por el período seleccionado en la
cabecera. Haga clic en el icono del calendario (**Seleccionar período**) y
elija en **Selección rápida**, por ejemplo, **Hoy**, **Esta semana**, **Mes
pasado**, **Este trimestre** o **Últimos 90 días**. Las flechas **Período
anterior** y **Próximo período** avanzan o retroceden; en pantallas grandes
también puede introducir en la cabecera una fecha de inicio y de fin propias y
confirmar con **Aplicar**. El período se aplica a todas las páginas hasta que
lo cambie o cierre la sesión; sin selección se aplica **Este mes**. La barra de
filtros lo muestra como indicación.

El tema de cada análisis indica las excepciones, por ejemplo:

- **Mi mes**, **Mi año** y **Mes por empleado** muestran el mes o el año en el
  que empieza el período.
- **Plan/real** tiene sus propios campos **Desde** y **Hasta**; sin indicación
  se aplica el mes en curso.
- **Cualificaciones** y **Problemas y formación** muestran la situación de hoy.

Un enlace con fechas, por ejemplo de una evaluación guardada, abre el análisis
exactamente con ese período.

## Filtros

- Según el análisis, la barra de filtros ofrece campos como **Cliente**,
  **Proyecto**, **Empleados**, **Equipo** o **Estado**. Una selección suele
  aplicarse de inmediato; **Restablecer** elimina todos los filtros.
- Algunos campos solo los ven los administradores, por ejemplo **Área** con
  **Solo propios** o **Equipo completo**. Las demás personas solo ven ahí sus
  propios datos.
- Los clientes marcados con **Ocultar en las evaluaciones** en sus datos
  maestros quedan fuera de los análisis de clientes y proyectos. El interruptor
  **Incluir clientes ocultos**, que solo aparece si existen tales clientes, los
  vuelve a incluir; si elige directamente uno de esos clientes en el filtro,
  también se muestra.
- Puede guardar un análisis configurado como vista con nombre; consulte
  «Evaluaciones guardadas».

## Exportación

- **PDF** descarga una versión imprimible con el diseño de documentos de su
  organización; el menú **Exportación** ofrece **CSV** y **Excel**. No todos los
  análisis ofrecen todos los formatos y algunos no tienen exportación.
- Las exportaciones aplican el período y los filtros de la página.
- En la configuración estándar, los archivos CSV se separan con punto y coma y
  se guardan en UTF-8. Las primeras líneas empiezan por # e indican el informe,
  el momento de creación y una huella de los filtros, de modo que un archivo
  pueda asignarse después a su estado.
- Las exportaciones quedan registradas en el registro de auditoría con el
  informe, el formato y los filtros.

## Drilldown

Muchos indicadores, puntos de gráficos y filas de tabla se pueden pulsar y
llevan a los registros subyacentes o a una vista más detallada, por ejemplo de
**Mi año** a **Mi mes** o de la pestaña **Equipo** de **Plan/real** a los días
de una persona. Más información en «Drilldown del indicador a la orden».

## Permisos

- Los análisis personales están abiertos a todos y solo muestran sus propios
  datos.
- Los análisis de toda la organización sobre clientes, ingresos y proveedores
  requieren el permiso **Ver los informes** o el rol de administrador.
- Algunos análisis tienen un permiso propio, por ejemplo **Ver informe de
  presencia (equipo)** para Plan/real o **Ver el registro de eventos de
  seguridad** para la seguridad laboral.
- Algunas vistas de equipo, como **Cobertura** o la vista de equipo de
  **Vacaciones y flex**, siguen reservadas a los administradores.
- Cada análisis muestra solo los datos de la organización activa.

## Qué análisis para qué

La siguiente vista general sigue los grupos de menú e indica para cada análisis
el tema con todos los detalles.

### Personal

**Mi mes**, **Mi año** y **Balance de trabajo** muestran su propio tiempo día a
día, a lo largo del año y en comparación con lo previsto; consulte «Mis
análisis». **Asistencia** y **Plan/real** también están en este grupo, pero
corresponden a la sección siguiente.

### Asistencia, planificación y cuentas de tiempo

- **Asistencia**, **Plan/real**, **Cobertura** y **Mes por empleado** comparan
  fichajes, turnos y horas registradas con lo previsto y la planificación;
  consulte «Asistencia, plan/real y cobertura».
- **Semana por empleado** muestra por persona las horas de cada día de la
  semana con el total semanal, como máximo doce semanas a la vez. La vista de
  todas las personas la tienen los administradores y las personas con el
  permiso **Ver todos los registros de tiempo**.
- **Utilización** relaciona el tiempo registrado, facturable y facturado;
  consulte «Ocupación & realización».
- **Presencia de emergencia** muestra quién está en el edificio, fuera o
  ausente; consulte «Lista de presencia de emergencia».
- **Previsión de recargos** estima, a partir de los turnos planificados, los
  minutos de recargo esperados por mes y concepto salarial; requiere el permiso
  **Ver los informes**; consulte «Facturación, gastos, pagos e ingresos».
- **Cuentas de tiempo** y **Comparación de períodos** se explican en «Cuentas
  de tiempo», y el **Plan de vacaciones** en «Plan de vacaciones (vista
  anual)».

### Personal y recursos humanos

**Vacaciones y flex**, **Enfermedades**, **Cualificaciones**, **Seguridad
laboral** y **Problemas y formación** se describen en «Personal: vacaciones,
enfermedad, cualificaciones, seguridad». La **Comparación de cohortes**
contrasta indicadores antes y después de una formación; consulte «Comparación
de cohortes (antes/después de la formación)». **Formación** pertenece a
«Gestión de formación» y la evaluación de cursos **Análisis**, a «Plataforma de
aprendizaje».

### Clientes y proyectos

- **Análisis de clientes** y **Clientes y proyectos** se explican en «Análisis
  de clientes»; **Valor del cliente** y **Retención de clientes** tienen temas
  propios con el mismo nombre; **Análisis de tipos de orden** se trata en
  «Análisis por tipo de orden».
- **Operaciones**, **Reparto del tiempo**, **Desviaciones de procedimiento**,
  **Ejecuciones de procedimiento bloqueadas**, **Análisis de productos**,
  **Detalles del proyecto** y **Proyectos inactivos** son análisis operativos;
  consulte «Operaciones: reparto del tiempo, procedimientos, material, guardias». Allí se describe también la **Calidad de los
  datos**.
- **SLA** y **Contratos SLA** se describen en «SLA, contratos y niveles de
  servicio».

### Recursos

- **Flota** muestra por vehículo los kilómetros, el consumo, los costes de
  combustible y de carga y el coste por kilómetro. El **Justificante libro de
  ruta** proporciona el libro de ruta fiscal por vehículo y período con los
  tipos de trayecto y la parte privada, además de la **Comparación 1 %**. El
  **Justificante tiempos de conducción** documenta los tiempos de conducción y
  descanso por conductor. Los tres se explican en «Flota, libro de ruta y tiempos de conducción».
- **Materiales** y **Servicio de urgencia** pertenecen a «Operaciones: reparto del tiempo, procedimientos, material, guardias».
- **Entrada de documentos en la nube** se explica en el tema del mismo nombre.

### Finanzas y auditoría

- **Rentabilidad**, **Comportamiento de pago**, **Análisis de proveedores** y
  **Valor del proveedor** tienen temas propios con el mismo nombre.
- **Facturación** e **Ingresos por producto**: consulte «Facturación, gastos, pagos e ingresos». Los
  **Ingresos por producto** se calculan a partir de las facturas locales y de
  las facturas de sistemas conectados como Lexoffice, también por categoría de
  artículo; las notas de crédito y los documentos de anulación reducen los
  ingresos.
- **Gastos** resume los gastos por persona, categoría y mes; solo los
  administradores tienen la vista de todas las personas. **Pagos externos**
  calcula la remuneración del personal externo sin nómina y solo es visible con
  el permiso **Gestionar los datos de personal y nómina**. Ambos se describen
  en «Facturación, gastos, pagos e ingresos».
- **Informes financieros** y **BWA & presupuesto** solo existen con una
  contabilidad llevada localmente. Leen únicamente asientos en firme; de ellos
  forma parte la **Previsión de liquidez**, por defecto a trece semanas;
  consulte «Cierre e informes».
- **Cumplimiento del tiempo de trabajo** comprueba los tiempos de trabajo reales
  frente a la ley alemana de jornada laboral; consulte «Cumplimiento de la ley
  de jornada laboral (ArbZG)». Las pestañas **Panel** e **Historial de
  infracciones** de esta página se describen en «Justificantes: actividad de auditoría, cumplimiento y salario mínimo». Desde allí
  genera también los justificantes para las autoridades de control: el
  **Justificante MiLoG (aduana)** para el control del salario mínimo con
  inicio, fin y duración por día de trabajo, también en «Justificantes: actividad de auditoría, cumplimiento y salario mínimo», y, si
  su organización registra tiempos de conducción, el **Justificante tiempos de
  conducción** sobre los tiempos de conducción y descanso por conductor;
  consulte «Flota, libro de ruta y tiempos de conducción».
- **Actividad de auditoría** resume el registro de auditoría por evento,
  persona y tipo de objeto y solo está abierta a los administradores; consulte
  «Justificantes: actividad de auditoría, cumplimiento y salario mínimo».
