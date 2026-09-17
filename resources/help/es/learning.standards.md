---
title: "Estándares de aprendizaje: SCORM, cmi5 y LTI"
topic: learning.standards
version: 1
audience: []
related:
    - learning.overview
    - training.overview
    - admin.integrations
---

Además de sus propias unidades, la plataforma admite los formatos de
intercambio habituales, de modo que puedes importar cursos adquiridos y lanzar
tus cursos en otros sistemas.

**SCORM 1.2 y 2004** — Un paquete SCORM es un ZIP con un manifiesto. Al
subirlo se comprueba y se extrae; los archivos ejecutables y las rutas que
salen del paquete se rechazan. El contenido se ejecuta en un **host propio**
para que el código ajeno no se ejecute en el origen de la aplicación. El
progreso y la finalización se toman de los mensajes del paquete.

**cmi5 y xAPI** — Los cursos cmi5 comunican su actividad como declaraciones al
registro de aprendizaje incluido. Una sesión solo acepta declaraciones dentro
de una ventana de tiempo limitada; después se cierra.

**LTI 1.3** — La plataforma funciona en ambos sentidos: puede integrar
herramientas externas como unidad de aprendizaje **y** ser lanzada desde otro
sistema de gestión del aprendizaje. El lanzamiento usa tokens firmados; las
claves se renuevan con regularidad y las anteriores siguen siendo válidas para
verificar sesiones en curso.

**Límites:** En los tres casos rige la regla de finalización del curso, no la
del paquete. Un paquete puede notificar la finalización, pero si cuenta lo
decide la configuración de publicación del curso.
