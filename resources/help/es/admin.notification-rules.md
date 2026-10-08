---
title: "Reglas de notificación"
topic: admin.notification-rules
version: 3
keywords:
    - escalado
    - configurar notificaciones
    - notificación por correo
    - notificación push
    - destinatarios
    - recordatorios
    - alertas de vencimiento
    - avisos de retraso
    - canales de notificación
audience:
    - admin
    - geschaeftsfuehrung
    - teamleitung
related:
    - admin.handbook
    - communication.notes
    - glossary.core
---

Las reglas de notificación definen para cada tipo de evento **quién**
recibe la información y por **qué canales**, y cuándo se escala. La
lista muestra para cada **Evento** las columnas **Activo**,
**Canales**, **Destinatarios** y **Escalado**; los eventos sin regla
propia llevan la indicación **Predeterminado (aún sin personalizar)**.

Procedimiento típico:

1. Elegir **Editar** en el evento (p. ej. punto abierto asignado/a
   punto de vencer/vencido, acción de seguimiento pendiente, documento
   a punto de caducar, solicitud de corrección, aprobación mensual
   enviada, certificado ISMS a punto de caducar, medida correctiva
   vencida, revisión de riesgos pendiente). Se abre **Editar regla de
   notificación**.
2. En **Activo**, activar **Notificaciones activadas para este evento**
   y elegir los **Canales**: **En la aplicación**, **Correo
   electrónico**, **Push**, **Microsoft Teams**, **Mattermost** o
   **Calendario**. En los eventos críticos (p. ej. **Alerta de
   crisis**, **Servicio de urgencia asignado**, **Evento de seguridad
   crítico**) también está disponible **SMS**.
3. Definir los **Destinatarios**: **Notificar a la persona afectada (p.
   ej. asignada o solicitante)**, **Roles destinatarios** (p. ej.
   jefatura de equipo) y **Destinatarios fijos adicionales**.
4. En los eventos de vencimiento, opcionalmente el **Escalado**:
   activar **Escalado activado**; tras **Escalar después de (horas)**
   (1–720) se notifica además al **Rol de escalado**. **Nivel de
   escalado 2** y **Nivel de escalado 3** notifican cada uno, tras horas
   adicionales, a sus propios roles y destinatarios fijos.

Conviene saber:

- Sin regla propia se aplica el valor predeterminado mostrado del
  evento (canales, indicador de persona afectada, roles); solo tiene
  que configurar los casos que se desvían.
- **Microsoft Teams** y **Mattermost** publican en el canal de chat
  configurado de la organización; **Calendario** añade los eventos con
  fecha a los calendarios conectados de la organización
  (CalDAV/Microsoft 365/Google). Los **SMS** solo llegan a personas con
  número de móvil confirmado y tienen coste por mensaje.
- El escalado solo existe para eventos de vencimiento o caducidad.
- Algunos eventos se desencadenan de inmediato (p. ej. la asignación),
  otros los detecta el escáner de plazos (p. ej. «a punto de vencer»).

Permiso: la lista la ven las personas con el permiso **Ver reglas de
notificación**; solo pueden modificarla quienes tienen **Editar reglas
de notificación**.
