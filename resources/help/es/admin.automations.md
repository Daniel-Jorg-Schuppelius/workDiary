---
title: "Automatizaciones"
topic: admin.automations
version: 3
keywords:
    - workflow
    - flujo de trabajo
    - reglas
    - regla si entonces
    - disparador
    - motor de reglas
    - acción automática
    - automatizar procesos
    - crear regla
    - automatización
audience:
    - admin
related:
    - admin.handbook
    - admin.notification-rules
    - admin.webhooks
---

Las automatizaciones son flujos basados en reglas que siguen el patrón
**evento → condición → acción**. Cuando se produce un evento
desencadenante definido y se cumplen las condiciones configuradas, se
ejecuta la acción asignada. Las reglas se aplican por organización y
están estrictamente limitadas al propio inquilino. Cada evaluación
queda registrada en el registro de auditoría.

La vista general muestra todas las reglas con **Prio**, **Nombre**,
**Disparador**, **Acción(es)** y **Activo**, ordenadas por defecto por
prioridad. Están disponibles las siguientes acciones:

- **Crear nueva regla (JSON)**: abre el diálogo **Nueva regla de
  automatización** con **Nombre**, **Desencadenante** (p. ej.
  «Liquidación de gastos enviada»), **Acción** (p. ej. «Aprobar
  gastos»), **Prioridad** y **Condiciones (JSON)**. La acción debe
  corresponder al desencadenante; una condición vacía se aplica
  siempre. **Crear regla** guarda la regla.
- **Desactivar**/**Activar**: las reglas desactivadas se conservan,
  pero ya no desencadenan ninguna acción.
- Vista de detalle (clic en el nombre): muestra el desencadenante, las
  condiciones y las acciones, así como el **Registro de auditoría
  (últimos 50)** con **Momento**, **Sujeto**, **Decisión** y
  **Registro**.
- **Eliminar**: elimina la regla de forma permanente.

La **Prioridad** determina el orden cuando varias reglas dependen del
mismo desencadenante (el valor más bajo primero, 100 por defecto). Solo
se ejecuta la primera regla que coincide; una regla se dispara como
máximo una vez por registro. Un JSON no válido en las condiciones se
rechaza.

Permiso: las automatizaciones las gestionan los administradores de la
organización.

Nota: para simples avisos, las **Reglas de notificación** suelen ser la
opción más sencilla; para sistemas externos, consulte **Webhooks**.
