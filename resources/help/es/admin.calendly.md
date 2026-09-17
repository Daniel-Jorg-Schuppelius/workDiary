---
title: "Conectar la reserva de citas (Calendly)"
topic: admin.calendly
version: 1
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
espera de tu decisión. Solo después se crea la cita.

**Cancelaciones y cambios** se reflejan si la página de reservas los comunica.
Una cita ya adoptada no se borra en silencio, sino que se marca como cancelada.

**Límites:** La integración lee reservas; no crea páginas de reserva ni cambia
disponibilidades. La disponibilidad se sigue gestionando donde se administra la
página de reservas.
