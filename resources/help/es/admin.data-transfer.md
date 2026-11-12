---
title: "Transferencia de datos"
topic: admin.data-transfer
version: 3
keywords:
    - exportación de datos
    - exportar datos
    - importar datos
    - exportación CSV
    - exportación Excel
    - descargar exportación
    - historial de exportaciones
    - exportar clientes
    - migración de datos
audience:
    - admin
    - buchhaltung
related:
    - admin.import
    - admin.backups
    - admin.handbook
---

La transferencia de datos agrupa la importación y exportación bajo una
navegación por pestañas: **Importación** (asistente CSV, ver el
capítulo **Importación**), **Exportación** (elegir una entidad y un
formato, con filtros opcionales; el resultado queda disponible para
descarga) e **Historial** (ejecuciones con estado y cifras clave).
Todas las ejecuciones se limitan a la organización actual y cada
exportación se registra en el log de auditoría; al eliminar una
exportación también se borra el archivo. Tiene acceso quien es
administrador o posee al menos un permiso de exportación. Para copias
de seguridad completas del sistema, consulta **Copias de seguridad**.

## Columnas con permiso propio

Las tarifas de facturación y de coste de las personas solo se exportan con el
permiso para datos de personal y nóminas; las de los proyectos, solo con el permiso
para la facturación de proyectos. Sin él, las columnas faltan en el archivo; un
archivo que las contenga solo lo descarga quien tenga ese permiso. Al importar,
workDiary omite esas columnas sin el permiso y las indica en el informe de
comprobación; las demás columnas se importan.
