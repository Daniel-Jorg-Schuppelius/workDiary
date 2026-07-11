---
title: "Configuración del sistema"
topic: admin.settings
version: 1
audience:
    - admin
related:
    - admin.handbook
---

Esta página administra en un único lugar todos los ajustes registrados
de la plataforma — desde tamaños de página y límites de subida hasta
umbrales operativos y de integraciones.

**Registro central:** Cada ajuste está registrado con su tipo, sus
ámbitos de aplicación permitidos y sus reglas de validación. La
escritura se realiza exclusivamente por esta vía validada — los valores
no válidos (p. ej. fuera de los límites mín./máx.) se rechazan con un
mensaje de error claro antes de que puedan surtir efecto.

**Dos ámbitos de aplicación:** Según la entrada, los ajustes rigen **a
nivel de sistema**, **por organización** o ambos. Con el conmutador de
ámbito cambia la vista; la búsqueda filtra por claves y la lista está
ordenada por grupos.

**Lógica de precedencia:** Para cada valor rige un orden fijo: el
**ajuste de la organización** prevalece sobre el **ajuste del
sistema**, y este sobre el **valor estándar** incorporado de la
instalación. La vista general muestra para cada entrada el valor
efectivo con su origen, de modo que reconoce de inmediato si un valor
es estándar o ha sido sobrescrito.

**Restablecer e historial:** Cada sobrescritura puede restablecerse
individualmente al estándar. Para los ajustes del sistema puede
consultar además el historial de cambios: quién estableció qué valor y
cuándo — trazable a través del registro de auditoría.

**Valores sensibles:** Las entradas marcadas como sensibles (p. ej.
direcciones de webhook con secretos) se muestran enmascaradas en la
interfaz. Pueden establecerse de nuevo, pero no leerse.

**Efecto sobre los trabajos:** Algunos ajustes influyen en trabajos en
segundo plano programados (como plazos de conservación u horarios de
ejecución). Estas relaciones están anotadas en la entrada; el cambio se
aplica en la siguiente ejecución.

**Recomendación:** Sobrescriba lo menos posible. Cada override de
organización hace el comportamiento más difícil de predecir — úselo
solo si la organización realmente debe desviarse, y documente el
motivo. Tras los cambios, compruebe el valor efectivo mostrado en lugar
de confiar en lo introducido.
