---
title: "Copias de seguridad y supervisión operativa"
topic: admin.backups
version: 1
audience:
    - admin
related:
    - admin.handbook
    - admin.security
---

WorkDiary supervisa las copias de seguridad externas mediante un
**heartbeat**: tu trabajo de backup notifica el éxito de cada ejecución
a `POST /admin/backup/heartbeat` con token Bearer, incluyendo el
**hash del manifiesto (SHA-256)** y el **tamaño**; cada recepción se
registra como evento de auditoría. Todavía no existe interfaz de
monitorización, así que configura una alerta externa si dejan de
llegar heartbeats. El comando `php artisan system:health` comprueba
base de datos, migraciones, almacenamiento, cola, APP_KEY, correo y
licencia sin modificar datos. Para la restauración, guarda siempre el
**APP_KEY** junto con la base de datos —sin él se pierden los campos
cifrados— y prueba las restauraciones con regularidad en un entorno
separado.
