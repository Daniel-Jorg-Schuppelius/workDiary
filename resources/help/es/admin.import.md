---
title: "Importación CSV"
topic: admin.import
version: 4
keywords:
    - importación de datos
    - importar datos maestros
    - importar clientes
    - asistente de importación
    - asignación de columnas
    - importación masiva
    - migración de datos
    - informe de errores
    - importar iCal
    - importar calendario
audience:
    - admin
    - geschaeftsfuehrung
    - personalverwaltung
    - teamleitung
    - buchhaltung
    - user
    - aussendienst
schema: process
related:
    - admin.handbook
    - admin.tenants
    - contacts.manage
---

## Objetivo y contexto

El asistente de importación trae datos maestros a WorkDiary por CSV —
con análisis **antes** de escribir e informe de errores completo. Es
la vía más rápida para asumir un patrimonio existente (clientes,
usuarios, proyectos, equipos, proveedores, materiales) de forma
estructurada, sin dejar la calidad de los datos al azar.

## Requisitos

- Derechos de administración.
- Un archivo CSV por entidad; el mapeo de columnas se hace en el
  asistente.
- Con datos dependientes: el **orden** correcto (primero
  clientes/equipos, luego proyectos, etc.).

## Procedimiento recomendado

1. **Elegir la entidad** (clientes, usuarios, proyectos, equipos,
   proveedores, materiales…).
2. **Subir el CSV** — el **análisis previo** revisa estructura y
   contenido sin escribir nada.
3. **Revisar la vista previa:** filas reconocidas, avisos, errores.
4. **Confirmar** — la importación corre como tarea en segundo plano.
5. **Descargar el CSV de errores:** todas las filas rechazadas con su
   motivo; corregir y reimportar.

![Asistente de importación con elección de entidad, plantilla y análisis previo](media/administration/import-assistent.png)
*El asistente de importación: elegir la entidad, descargar la plantilla, subir el archivo — el análisis no escribe nada.*

## Asignar sus propios nombres de columna

Si la importación no reconoce un encabezado de columna, la ejecución muestra
la tarjeta **Asignación de columnas**. Elija la columna de destino de cada
encabezado y guarde: el archivo se vuelve a comprobar de inmediato. A partir
de entonces, la asignación se aplica a cada archivo del mismo tipo de
importación, también a la importación de clientes desde la lista de clientes.
Una propuesta de la IA solo rellena la selección. Las asignaciones guardadas
aparecen en el menú de la lista de importaciones, en **Asignaciones de
columnas guardadas**, donde puede eliminarlas una a una.

## Ejemplo práctico

En una migración, una empresa importa primero un archivo de prueba
con diez clientes, revisa vista previa y mapeo, y luego carga las 800
filas completas. Doce filas caen motivadas en el informe de errores,
se corrigen y entran en la segunda pasada.

## Errores habituales

- **Cargar todo sin archivo de prueba** — los errores de mapeo se
  multiplican sin necesidad.
- **Ignorar el orden:** proyectos antes que sus clientes fallan por
  referencias ausentes.
- **Ignorar el informe de errores:** las filas erróneas no abortan la
  pasada — pero faltan del patrimonio hasta reimportarlas.

## Efectos y próximos pasos

Antes de confirmar no se escribe **nada** — análisis y vista previa
son seguros. El historial muestra todas las pasadas con su estado,
filtrable por entidad y condición. Después: revisar por muestreo los
datos importados y fusionar duplicados.

## Datos de movimiento y de medición

Además de los datos maestros, la importación también recoge datos
continuos: lecturas de contador, consumos ESG (actividad, cantidad,
periodo y calidad de los datos), mediciones de inspección para
inspecciones existentes y posiciones de equipos de exportaciones
telemáticas. En las posiciones, WorkDiary avisa cuando un equipo alquilado
abandona su lugar de uso.

## Fichajes y tiempos de proyecto desde el calendario

Para fichajes y tiempos de proyecto puede elegir una fuente de calendario
conectada en lugar de un archivo (CalDAV, Google Calendar, Microsoft 365).
Los eventos del periodo elegido se obtienen y se comprueban como un
archivo iCal, con vista previa, filtro de categorías y resolución de
series.

La importación de asistencias admite la columna opcional **erfasst am** (fecha
y hora del registro original). Sirve para el plazo de registro de la MiLoG; sin
ella, el plazo no se comprueba en esas líneas.
