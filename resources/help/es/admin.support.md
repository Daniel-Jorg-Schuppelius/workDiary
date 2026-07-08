---
title: "Informe de soporte y diagnóstico de errores"
topic: admin.support
version: 1
audience:
    - admin
related:
    - admin.security
    - admin.backups
    - admin.handbook
---

El **informe de soporte** reúne el estado técnico de tu instalación
para que el soporte pueda analizar un problema **sin que los datos de
clientes salgan de la casa**. Contiene versiones y build, el resultado
de `php artisan system:health`, errores de plugins de los últimos 7
días (solo ID, fase y recuento), estado de la cola y las copias de
seguridad, recuentos de registros por tabla y flags de configuración;
los secretos se redactan siempre. La minimización de datos es la
promesa central: solo se incluyen campos técnicos de una lista blanca,
nunca nombres de clientes ni credenciales. Genera el informe desde la
página de administración **«Informe de soporte»** (ZIP con contraseña
opcional, JSON o vista previa) o por línea de comandos con
`php artisan support:report`; cada generación queda registrada en el
log de auditoría.
