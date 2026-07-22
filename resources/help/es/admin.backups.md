---
title: "Copias de seguridad y supervisión operativa"
topic: admin.backups
version: 2
audience:
    - admin
related:
    - admin.handbook
    - admin.security
---

WorkDiary supervisa las copias de seguridad externas mediante un
**heartbeat**: tu trabajo de backup notifica el éxito de cada ejecución
a `POST /admin/backup/heartbeat` con token Bearer (definido con la
variable de entorno `BACKUP_HEARTBEAT_TOKEN`; sin token el endpoint
queda desactivado), transmitiendo `manifest_sha256`, `size_bytes`,
`source` y `occurred_at`; cada recepción se registra como evento de
auditoría. Las copias no se registran manualmente en la interfaz: con
el primer heartbeat la fuente aparece automáticamente en la página
**Backup & Restore**, que muestra la última copia por fuente y la marca
como vencida si supera la frescura configurada
(`BACKUP_HEARTBEAT_FRESHNESS_HOURS`, 26 h por defecto). Los tests de
restauración se anotan allí con **Registrar test de restauración**; la
restauración en sí se realiza deliberadamente fuera de WorkDiary. El
comando `php artisan system:health` comprueba base de datos,
migraciones, almacenamiento, cola, APP_KEY, correo y licencia sin
modificar datos. Para la restauración, guarda siempre el **APP_KEY**
junto con la base de datos —sin él se pierden los campos cifrados— y
prueba las restauraciones con regularidad en un entorno separado.
