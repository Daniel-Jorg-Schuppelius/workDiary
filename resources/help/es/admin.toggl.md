---
title: "Importación de Toggl"
topic: admin.toggl
version: 1
audience:
    - admin
related:
    - admin.plugins
    - admin.import
    - admin.openproject
---

La importación de Toggl trae los registros de tiempo de Toggl Track a
WorkDiary y es **unidireccional** (solo lectura): no se escriben
tiempos de vuelta. Hay dos vías: la **importación por API** (token de
API y rango de fechas) y la importación de archivos (informe detallado
CSV o archivo de exporte completo del workspace). Los
clientes/proyectos de Toggl sin asignación automática se acumulan en
la bandeja de entrada, donde los asignas a clientes/proyectos
existentes, creas nuevos o los descartas; los futuros imports usan las
asignaciones guardadas, que puedes modificar o eliminar. Importar de
nuevo el mismo periodo puede generar duplicados si los datos de origen
cambiaron, y el descarte de entradas es definitivo.
