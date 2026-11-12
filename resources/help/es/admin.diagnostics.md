---
title: "Diagnóstico"
topic: admin.diagnostics
version: 2
keywords:
    - estado del sistema
    - health check
    - comprobación del sistema
    - resolución de problemas
    - correo de prueba
    - comprobar envío de correo
    - cola
    - programador de tareas
    - cron
audience:
    - admin
related:
    - admin.security
    - admin.metrics
    - admin.handbook
---

El diagnóstico ofrece un informe de salud del sistema con estado tipo
semáforo por área comprobada: **versión**, **licencia**, **cola**,
**planificador**, **correo**, **almacenamiento** y **backup**, cada una
con estado OK, advertencia, crítico o desconocido; el informe también
está disponible como JSON. Además puede enviar un **correo de prueba**
a su propia dirección para verificar la configuración de correo. Las
consultas y pruebas se registran en el log de auditoría; ver el
diagnóstico requiere el permiso correspondiente y lanzar pruebas, un
permiso propio. Las métricas operativas están en **Métricas**.

**Acceso de monitorización:** para una monitorización externa, cree un acceso en «Acceso de monitorización». La monitorización obtiene el informe sin iniciar sesión en /api/monitoring/diagnostics y envía el token en la cabecera «Authorization: Bearer …». El token se muestra una sola vez y solo se guarda su huella; al revocarlo queda bloqueado de inmediato. El informe no contiene nombres ni direcciones, como el titular de la licencia o la dirección del remitente. Las consultas se registran como máximo una vez por hora y acceso.
