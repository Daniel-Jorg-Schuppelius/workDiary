---
title: "Conectar la reserva de citas (Calendly)"
topic: admin.calendly
version: 2
keywords:
    - reserva online
    - página de reservas
    - agendar citas
    - agenda online
    - citas de clientes
    - cita online
    - cancelación de citas
    - reprogramar cita
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - appointments.inbox
---

La integración trae a la gestión de citas las reservas que los clientes hacen
en una página de reservas.

**Conexión:** Se establece una vez por organización y luego vale para todas las
páginas de la cuenta conectada. Las credenciales se guardan cifradas y ya no se
muestran en claro tras guardarlas.

**Reservas entrantes:** Las nuevas citas llegan primero a la **bandeja de
citas**, no directamente al calendario. Allí se asignan a un cliente: las
coincidencias inequívocas de forma automática, los casos dudosos quedan a la
espera de su decisión. Solo después se crea la cita.

**Cancelaciones y cambios** se reflejan si la página de reservas los comunica.
Una cita ya adoptada no se borra en silencio, sino que se marca como cancelada.

**Límites:** La integración lee reservas; no crea páginas de reserva ni cambia
disponibilidades. La disponibilidad se sigue gestionando donde se administra la
página de reservas.

**Sin coincidencia de cliente:** Una reserva cuya dirección no se puede asignar
inequívocamente a un cliente aparece en la **bandeja de integración** como caso
con destino «Cliente»: asignar (sugerencias por nombre), crear o descartar.
También puede asignar la solicitud a un cliente o lead en la bandeja de citas.

**Asignar anfitriones:** Los anfitriones de Calendly se reconocen por su
dirección de correo. Si la dirección de Calendly es distinta, asígnela a un
usuario en **Asignar usuarios de Calendly**; las citas irán entonces a ese
usuario.

**Permisos:** Conectar, webhook, sincronización, asignación de anfitriones y
enlaces de reserva corresponden a la administración de la organización.
Confirmar y rechazar solicitudes de cita puede hacerlo quien lleva la
planificación, en esta página y en la bandeja de citas.

**Soberanía de datos:** Las reservas y cancelaciones de Calendly pertenecen al área de calendario. Si otro sistema gestiona el calendario, WorkDiary no crea ni modifica ninguna solicitud de cita; el caso queda en la bandeja de integraciones para asumirlo una vez.
