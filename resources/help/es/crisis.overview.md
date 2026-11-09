---
title: "Gestión de emergencias y crisis"
topic: crisis.overview
version: 2
keywords:
    - comité de crisis
    - plan de emergencia
    - continuidad de negocio
    - BCM
    - alerta de crisis
    - informe de situación
    - plazo de notificación
    - NIS2
    - brecha de datos
    - recuperación
    - simulacro de crisis
audience: []
modules:
    - module.crisis_management
related:
    - documents.manage
---

El expediente de crisis mantiene un estado de mando coordinado sobre los
procesos especializados vinculados (tickets de servicio, incidentes de
seguridad/protección de datos, sucesos de seguridad laboral, ejecuciones
de playbooks) — los módulos especializados siguen siendo rectores.

**Gabinete de crisis y alertas:** Roles con persona, suplencia y datos
de contacto. La alerta anula deliberadamente los períodos de descanso
(alarma de crisis); cada miembro acusa recibo de su alerta, y las
alertas sin confirmar se escalan manualmente a la suplencia. El
nombramiento en el gabinete constituye a la vez el acceso de emergencia
auditado al expediente.

**Imagen de situación y decisiones:** Los informes de situación están
versionados y son append-only; el protocolo de decisiones registra el
momento, la decisión y la justificación.

**Plazos de notificación:** Las plantillas de plazos por categoría
(RGPD 72 h, NIS2 24 h/72 h/1 mes, KRITIS sin demora) son datos de
catálogo — la organización puede sobrescribirlas; las fechas concretas
corren a partir de la activación.

**Descartar una falsa alarma:** Si una crisis notificada resulta ser una
falsa alarma antes de que nadie haya evaluado la situación, «Descartar
como falsa alarma» cierra el expediente con una justificación obligatoria.
La justificación queda en el historial de auditoría y el expediente se
conserva solo en lectura. Tras la evaluación ya no se descarta, sino que
se da el fin de la alerta.

**Comunicación:** El borrador, la aprobación (nunca por el redactor) y
el envío documentado quedan trazables por separado.

**Reanudación y análisis posterior:** procesos críticos con RTO/RPO,
soluciones provisionales y procesos sustitutivos; tras el fin de la
alerta sigue el análisis posterior (obligatorio antes del cierre). Los
simulacros se ejecutan por separado y no falsean expedientes reales.
