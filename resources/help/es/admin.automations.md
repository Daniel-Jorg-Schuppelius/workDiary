---
title: "Automatizaciones"
topic: admin.automations
version: 1
audience:
    - admin
related:
    - admin.handbook
    - admin.notification-rules
    - admin.webhooks
---

Las automatizaciones son reglas con el patrón **evento → condición →
acción**: cuando ocurre el evento desencadenante y se cumplen las
condiciones, se ejecutan las acciones asignadas; las reglas son por
organización y quedan limitadas al propio inquilino. En la vista
general puedes **crear** reglas (condiciones y acciones se introducen
como JSON en el MVP actual), **activarlas/desactivarlas**, consultar
las últimas ejecuciones en la vista de detalle y **eliminarlas**. La
**prioridad** determina el orden cuando coinciden varias reglas (valor
más bajo primero) y el JSON inválido se rechaza. Para simples avisos
suelen bastar las **reglas de notificación**; para sistemas externos
consulta **Webhooks**.
