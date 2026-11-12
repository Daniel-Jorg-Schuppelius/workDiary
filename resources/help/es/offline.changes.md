---
title: "Cambios sin conexión"
topic: offline.changes
version: 2
keywords:
    - trabajar sin conexión
    - sin internet
    - sin cobertura
    - cola de sincronización
    - sincronización
    - cambios pendientes
    - entradas rechazadas
    - modo offline
    - no sincronizado
audience: []
related:
    - attendance.manage
    - forms.fill
---

Las acciones registradas sin conexión (fichajes, comentarios de órdenes, formularios) se encolan en este dispositivo y se transfieren automáticamente al recuperar la conexión.

**Pendientes:** entradas aún no transferidas. Se envían en el próximo contacto en línea; «Descartar» las elimina antes.

**Rechazadas:** entradas no aceptadas por el servidor (falta un campo obligatorio, fichaje ya abierto…) — con motivo. «Aplicar de nuevo» vuelve a encolar la entrada.

**Importante:** la lista solo existe en este dispositivo y se vacía al cerrar sesión. Archivos y firmas no pueden registrarse sin conexión.

## Qué funciona sin conexión

Además de fichajes, comentarios de órdenes y formularios, sin conexión también registra **tiempo de proyecto** con la barra de entrada de «Hoy» (duración o desde/hasta, no el temporizador), **material** con «Material» en «Hoy» y **correcciones de sus fichajes** con el icono del lápiz en la lista de fichajes de «Hoy». Estos diálogos forman parte de la página, así que se abren sin red si «Hoy» se cargó antes.

## Decidir conflictos

Un conflicto significa que el registro **no** se adoptó porque algo cambió desde entonces o falta un dato obligatorio: por ejemplo, un fichaje modificado entretanto, un día cerrado o una reserva sin proyecto. Usted decide:

- **Conservar la otra versión** o **Descartar**: su versión se elimina.
- **Enviar mi versión**: solo para un fichaje modificado; su corrección se aplica al nuevo estado.
- **Editar**: ajustar campos (como fecha, duración o motivo) y enviarlos como nueva entrada.

Cada decisión queda registrada en el registro de auditoría. La página también muestra conflictos abiertos surgidos en otro dispositivo; su contenido está allí, de modo que aquí solo pueden descartarse.
