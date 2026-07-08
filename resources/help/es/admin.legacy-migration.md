---
title: "Migración desde el sistema anterior"
topic: admin.legacy-migration
version: 1
audience:
    - admin
related:
    - admin.import
    - admin.data-transfer
    - admin.handbook
---

La migración legacy transfiere los datos del sistema anterior a
WorkDiary y muestra el estado de la transferencia por área de datos
(**usuarios**, **entradas de diario**, **guardias**, **intervenciones
de emergencia**), comparando los registros existentes en el sistema
antiguo con los ya importados. Requiere una conexión de base de datos
configurada al sistema anterior; si no está accesible, el área aparece
como «no configurada». La importación se inicia por área y ejecuta en
segundo plano el comando `legacy:import`; los registros ya importados
quedan vinculados por un identificador legacy, de modo que las
ejecuciones repetidas no crean duplicados. El acceso de escritura
depende de la configuración (`legacy_write_enabled`) y el acceso a esta
área requiere el permiso de consulta de logs de auditoría.
