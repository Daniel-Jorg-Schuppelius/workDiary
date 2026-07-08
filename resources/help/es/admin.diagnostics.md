---
title: "Diagnóstico"
topic: admin.diagnostics
version: 1
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
está disponible como JSON. Además puedes enviar un **correo de prueba**
a tu propia dirección para verificar la configuración de correo. Las
consultas y pruebas se registran en el log de auditoría; ver el
diagnóstico requiere el permiso correspondiente y lanzar pruebas, un
permiso propio. Las métricas operativas están en **Métricas**.
