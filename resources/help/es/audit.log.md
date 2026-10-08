---
title: "Registro de auditoría"
topic: audit.log
version: 2
keywords:
    - pista de auditoría
    - historial de cambios
    - registro de actividad
    - quién cambió qué
    - trazabilidad
    - a prueba de manipulaciones
    - cadena hash
    - GoBD
    - log de cambios
    - cumplimiento normativo
audience:
    - admin
related:
    - admin.security
    - admin.handbook
    - privacy.overview
---

El registro de auditoría (`/audit`) es el protocolo de control a prueba
de revisión de los cambios y acciones realizados en el sistema. Las
entradas son **append-only** (solo se añaden) y están encadenadas entre
sí mediante una **cadena de hash SHA-256** (GoBD); nunca se escriben en
bruto y no pueden modificarse ni eliminarse a posteriori.

**Filtros**: la lista puede restringirse por

- **Acción** (p. ej. creado, modificado, eliminado, archivado,
  restaurado, así como eventos de importación),
- **Tipo** del objeto afectado (entre otros, entrada del diario,
  comentario, cliente, proveedor, ejecución de importación, serie
  numérica),
- **Usuario** y
- **Período** (mediante el filtro de fecha global).

En cada entrada ve el momento, el usuario que la originó, la acción, el
objeto, los cambios concretos y la dirección IP.

**Verificar la integridad**: la cadena de hash se comprueba con el
comando de consola `php artisan audit:verify`. Este valida el
encadenamiento y, si detecta una ruptura, termina con el código de
salida 1, lo que resulta ideal para cron/CI. Mantenga el comando
siempre en verde; una ruptura indica una manipulación o un error de
datos. Con `--chain` puede comprobarse de forma selectiva una sola
cadena (`audit_logs` u `organization_audit_logs`).

Nota: el registro de auditoría es una herramienta de solo lectura.
Muestra las operaciones, pero no modifica por sí mismo ningún dato.
