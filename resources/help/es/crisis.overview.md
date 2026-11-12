---
title: "Gestión de emergencias y crisis"
topic: crisis.overview
version: 4
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

Los plazos propios se gestionan en la pestaña **Plazos de notificación**:
adoptar los valores predeterminados de una categoría como plazos propios,
crear, modificar y desactivar plazos. En cuanto una categoría tiene un plazo
propio activo, solo se le aplican los plazos propios; sin plazo propio activo
vuelven a aplicarse los valores predeterminados.

**Medidas con predecesor:** al registrar una medida puede elegir un
predecesor del mismo expediente. La medida solo puede completarse cuando el
predecesor esté terminado o cancelado; puede empezarla antes.

**Proceso sustitutivo y estado restante:** para un proceso crítico usted
registra el proceso sustitutivo además de la solución provisional. Con el
estado de recuperación anota la limitación restante.

**Abrir una crisis a partir de un incidente:** el incidente de seguridad, el
incidente de protección de datos, el evento de seguridad laboral y el ticket
de incidente grave llevan el botón «Abrir crisis». El diálogo rellena título,
categoría y desencadenante y vincula el incidente al nuevo expediente. Otros
expedientes se vinculan mediante «Vincular un expediente» desde una selección;
solo se ofrece lo que usted puede leer.

**Lista de presencia de emergencia:** con el permiso correspondiente, un
expediente de crisis en curso lleva directamente a ella.

**Cambiar el predecesor:** el predecesor de una medida se cambia o elimina directamente en la lista de medidas. Solo se pueden elegir medidas del mismo expediente; WorkDiary rechaza una elección que crearía un ciclo (A espera a B, B a A).
