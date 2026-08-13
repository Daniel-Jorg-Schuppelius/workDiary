---
title: "Historial de cambios y comparación de versiones"
topic: admin.audit-diff
version: 1
audience: [admin]
related:
    - audit.log
---

El historial de cambios hace legible la cadena de auditoría: para un
registro seleccionado (miembro, cliente, modelo de jornada, tipo de
turno, cuenta de tiempo, organización) la línea de tiempo muestra todos
los cambios registrados con momento, evento y usuario.

Seleccione dos estados (A = más antiguo, B = más reciente) y compare:
la tabla de diferencias muestra por campo el valor antes del estado A y
después del estado B — en segundos queda claro desde cuándo existe un
valor y quién lo cambió.

Los campos sensibles (contraseñas, secretos, números fiscales y de
seguridad social) se muestran enmascarados. La comparación es solo
visualización: las correcciones siguen siendo operaciones auditadas — no
hay reversión automática.
