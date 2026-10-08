---
title: "Justificantes: actividad de auditoría, cumplimiento y salario mínimo"
topic: reports.compliance
version: 1
keywords:
    - análisis de auditoría
    - quién cambió qué
    - rastrear exportaciones
    - resumen de cumplimiento
    - infracciones de jornada
    - confirmar infracciones
    - aceptar una infracción
    - casos sin aclarar
    - justificante salario mínimo
    - inspección de aduanas
    - registro de jornada
    - obligación de registro
audience:
    - admin
    - geschaeftsfuehrung
    - teamleitung
    - buchhaltung
related:
    - reports.arbzg-compliance
    - audit.log
    - corrections.requests
    - attendance.manage
    - reports.fleet
    - admin.organization-settings
---

Estas páginas sirven como justificante ante auditores y autoridades: quién
hizo qué en el sistema, en qué situación están las infracciones de la ley
alemana de jornada laboral (ArbZG) y cómo se trataron, y el registro de la
jornada según la ley del salario mínimo para la aduana. Qué reglas comprueba
el cumplimiento del tiempo de trabajo y cómo es la lista detallada se
describe en el tema dedicado al cumplimiento del tiempo de trabajo.

## Período y exportación

- El período se elige con el selector de período de la cabecera. El
  **Historial de infracciones**, en cambio, muestra todas las infracciones
  guardadas.
- Donde existe una exportación, se menciona en la sección correspondiente.
  Las exportaciones PDF y CSV se registran en el registro de auditoría.

## Actividad de auditoría

**Análisis** → **Finanzas y auditoría** → **Actividad de auditoría** resume
las entradas del registro de auditoría del período. La página solo se abre
para los administradores; a todos los demás se les deniega el acceso.

- Mosaicos: **Eventos Σ** (todas las entradas del período), **Usuarios
  activos** y **Tipos de entidad**. Los dos últimos cuentan las entradas de
  las listas top 20 y por eso muestran como máximo 20.
- Gráficos: **Eventos a lo largo del tiempo**, **Principales actores (top
  15)** y los eventos a lo largo del tiempo por tipo de evento.
- Tablas: **Por evento**, **Por tipo de entidad (Top 20)**, **Por usuario
  (Top 20)** y **Últimos 100 eventos** con **Momento**, **Usuario**,
  **Evento**, **Tipo**, **ID** e **IP**. Los eventos y tipos aparecen con su
  nombre legible cuando lo hay.
- Filtro: **Empleados**.

Exportar análisis también genera una entrada con el análisis, el formato y
los filtros; así se puede rastrear quién descargó qué análisis. Las entradas
individuales con todos los detalles se muestran en el **Registro de
auditoría**. Exportación en PDF, CSV y Excel.

## Panel de cumplimiento

El **Panel de cumplimiento** se abre con la pestaña **Panel** de la página
**Cumplimiento del tiempo de trabajo** (**Análisis** → **Finanzas y
auditoría** → **Cumplimiento del tiempo de trabajo**). Las pestañas
**Panel**, **Informe detallado** e **Historial de infracciones** conectan las
tres vistas. Se necesita el permiso **Ver el cumplimiento del tiempo de
trabajo**.

El panel determina los hallazgos del período a partir de los tiempos de
trabajo registrados, igual que el informe detallado; si las normas de
tiempos de conducción están activadas, se añaden los hallazgos de tiempos de
conducción y descanso.

- Mosaicos: **Total de hallazgos** (un clic abre el informe detallado),
  **Empleados afectados**, **Abierto (sin corrección)** y **Con corrección
  aprobada** (hallazgos en días con una corrección de tiempo aprobada).
- Un mosaico por tipo de infracción con el número; un clic abre el informe
  detallado filtrado por ese tipo.
- Gráficos: hallazgos abiertos a lo largo del tiempo y hallazgos a lo largo
  del tiempo por tipo de infracción.
- **Infracciones por regla y mes**: por cada mes, los hallazgos de cada tipo
  de infracción con **Suma**.
- **Hallazgos por equipo**: deliberadamente por equipo y no por persona.
  Quien pertenece a varios equipos cuenta en cada uno; las personas sin
  equipo aparecen en **Sin equipo**.

Filtro: **Equipo**. El panel no ofrece exportación.

## Historial de infracciones

La pestaña **Historial de infracciones** abre la página **Infracciones de
cumplimiento** con las infracciones guardadas y su estado de tratamiento. Una
comprobación periódica, por defecto una vez al día durante la noche, guarda
los hallazgos nuevos. Si en esa comprobación un hallazgo ya no se detecta,
pasa a **Resuelto**; si vuelve a producirse, vuelve a **Abierto**.

- Mosaicos por estado: **Abierto**, **Confirmado**, **Resuelto** y
  **Aceptado**, contados en toda la organización. Un clic filtra la lista.
- Gráfico **Hallazgos nuevos vs. confirmados por mes** para los últimos 24
  meses con datos.
- Lista: **Empleado**, **Fecha**, **Tipo**, **Valor**, **Umbral**,
  **Gravedad** y **Estado**; en las infracciones tratadas aparecen debajo el
  nombre, la fecha y el motivo.
- Filtros: **Empleados**, **Equipo**, **Estado** y **Categoría** (**ArbZG**,
  **Casos sin aclarar**, **Tiempos de conducción**).

Así se trata una infracción con el estado **Abierto** o **Confirmado**:

1. Si es necesario, escriba un motivo en el campo **Motivo (obligatorio para
   «aceptado»)**.
2. Elija **Confirmar** para tomar conocimiento de la infracción o
   **Aceptar** para tolerarla conscientemente. Para **Aceptar** el motivo es
   obligatorio.

Cada cambio de estado queda registrado en el registro de auditoría. En los
**Casos sin aclarar**, un icono adicional abre una **Solicitud de
corrección** con el día del hallazgo. La lista no se limita al período y
muestra 50 entradas por página. La página no ofrece exportación.

## Justificante MiLoG (aduana)

El **Justificante MiLoG (aduana)** se descarga en la página **Cumplimiento
del tiempo de trabajo** desde el menú **Exportación**. Sirve como registro
según el § 17, apartado 1, de la ley alemana del salario mínimo (MiLoG) y
requiere el permiso **Ver el cumplimiento del tiempo de trabajo**.

- El archivo CSV contiene, por empleado y día natural, **Empleado**,
  **Número de personal**, **Fecha**, **Inicio**, **Fin**, **Pausas (min)** y
  **Duración**.
- Se basa en los fichajes completados; los fichajes cancelados y los que
  siguen abiertos no cuentan. **Inicio** es el primer inicio y **Fin** el
  último fin del día, las pausas se suman y **Duración** es el tiempo de
  trabajo tras descontar las pausas.
- Se ordena por nombre y fecha. La descarga adopta el período y el filtro de
  empleado de la página y queda registrada en el registro de auditoría.

El **Justificante tiempos de conducción** del mismo menú se describe en el
tema dedicado a flota, libro de ruta y tiempos de conducción.
