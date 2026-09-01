---
title: "Copias de seguridad & supervisión"
topic: admin.backups
version: 3
audience:
    - admin
schema: process
related:
    - admin.handbook
    - admin.security
    - admin.import
---

## Objetivo y contexto

WorkDiary supervisa las copias externas mediante **latido**: el
trabajo de copia informa su éxito a la plataforma tras cada
ejecución. Las copias no se registran a mano — con el primer latido
la fuente aparece automáticamente en **Copia & restauración**. La
copia y la restauración reales ocurren a propósito fuera de
WorkDiary.

## Requisitos

- Un trabajo de copia externo (p. ej. el `backup.sh` incluido).
- El token en la variable de entorno `BACKUP_HEARTBEAT_TOKEN` — sin
  token el punto de acceso está desactivado.
- Derechos de administración para la página **Copia & restauración**.

## Procedimiento recomendado

1. Configurar el trabajo y hacer que envíe el latido:
   `POST /admin/backup/heartbeat` con token Bearer (fuera del
   circuito normal de acceso, con límite de frecuencia); se
   transmiten `manifest_sha256`, `size_bytes`, `source` y
   `occurred_at`.
2. Revisar la fuente en **Copia & restauración**: la página muestra
   la última copia por fuente y la marca **atrasada** si el último
   latido supera la frescura configurada
   (`BACKUP_HEARTBEAT_FRESHNESS_HOURS`, 26 h por defecto).
3. Probar restauraciones con regularidad en un entorno separado y
   anotarlas con **Registrar prueba de restauración**.
4. Vigilar el estado del sistema: `php artisan system:health`
   comprueba base de datos, migraciones, almacenamiento, cola,
   APP_KEY, correo y licencia (código de salida 0/1, no cambia datos
   — ideal para cron/CI).

## Ejemplo práctico

El cron nocturno copia a las 23 h y envía el latido. Cuando una obra
en el almacenamiento detiene el trabajo dos noches, la fuente pasa a
«atrasada» — el admin lo ve por la mañana en el panel, antes de que
amenace una pérdida de datos.

## Errores habituales

- **Copiar solo la base de datos:** sin el **APP_KEY**, los campos
  cifrados (PII, 2FA, casos de protección de datos) se pierden sin
  remedio.
- **No probar nunca las restauraciones:** una copia sin restauración
  verificada es una esperanza, no un concepto.
- **Confundir latido con copia:** el latido solo informa del éxito —
  no sustituye ni copia ni retención.

## Efectos y próximos pasos

Cada latido se guarda y se registra como evento de auditoría
`backup.heartbeatReceived`; los atrasos afloran en la supervisión.
Después: programar una prueba de restauración, añadir
`system:health` al cron y leer en el manual de administración las
notas de recuperación ante desastres.
