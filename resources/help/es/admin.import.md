---
title: "Importación CSV"
topic: admin.import
version: 1
audience:
    - admin
related:
    - admin.handbook
    - admin.tenants
---

El asistente de importación lleva datos maestros a WorkDiary por CSV,
con análisis previo y un informe de errores completo. Flujo típico:
elegir la **entidad** (clientes, usuarios, proyectos, etc.), subir el
CSV para el **análisis preliminar (preflight)**, revisar la vista
previa con filas reconocidas, advertencias y errores, confirmar (la
importación se ejecuta como trabajo en segundo plano) y descargar el
**CSV de errores** con las filas rechazadas para corregirlas y
reimportarlas. Antes de confirmar no se escribe nada, y las filas
erróneas no interrumpen la ejecución. Consejo: importa primero un
archivo de prueba pequeño y respeta el orden (primero
clientes/equipos, luego datos dependientes como proyectos).
