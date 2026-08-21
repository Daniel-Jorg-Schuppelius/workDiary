---
title: "Circulares a clientes"
topic: circulars.overview
version: 1
audience: []
related:
    - contacts.manage
    - invoices.manage
---

Las circulares son comunicaciones comerciales a un grupo filtrado de
clientes — ajuste de precios, ventana de mantenimiento, horarios de guardia
modificados. No son un boletín: sin píxel de seguimiento y sin enlaces
reescritos.

**Destinatarios:** El grupo se determina con los filtros de clientes ya
existentes (búsqueda, ciudad, inicio del código postal, solo clientes con un
proyecto activo). Antes del envío se muestra el número de destinatarios con
la lista completa — un correo a todos los clientes no debe poder lanzarse
por descuido.

**Rechazo de envíos masivos:** Los clientes con la opción *Sin envíos
masivos* se omiten. Las circulares marcadas como *comunicación obligatoria*
les llegan igualmente; eso queda reservado a la información exigida
legalmente.

**Prueba:** Cada destinatario genera una línea — también los omitidos, con
su motivo (por ejemplo, una dirección de correo que falta). La comunicación
se archiva además como nota en el expediente del cliente y, si se desea,
aparece en el portal de clientes.

**Marcadores:** `:firma`, `:kunde` y `:ansprechpartner` se sustituyen para
cada destinatario. Si falta un valor, el hueco queda vacío — no se inventa
nada.
