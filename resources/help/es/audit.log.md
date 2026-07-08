---
title: "Registro de auditoría"
topic: audit.log
version: 1
audience:
    - admin
related:
    - admin.security
    - admin.handbook
    - privacy.overview
---

El registro de auditoría (`/audit`) es el protocolo a prueba de
revisiones de todos los cambios y acciones del sistema: las entradas son
**append-only** y están encadenadas mediante una **cadena de hashes
SHA-256** (GoBD), por lo que no pueden modificarse ni borrarse. La lista
se filtra por **acción**, **tipo** de objeto, **usuario** y **período**;
cada entrada muestra fecha, usuario, acción, objeto, cambios concretos y
dirección IP. La integridad se comprueba con `php artisan audit:verify`
(código de salida 1 en caso de rotura, ideal para cron/CI); mantén el
comando siempre en verde. Es una herramienta de solo lectura que no
modifica datos.
