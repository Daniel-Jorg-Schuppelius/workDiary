---
title: "Candidaturas y licitaciones"
topic: applications.overview
version: 2
keywords:
    - reclutamiento
    - selección de personal
    - gestión de candidatos
    - oferta de empleo
    - entrevista de trabajo
    - bolsa de talento
    - rechazar candidato
    - contratación
    - concurso público
    - presentación de ofertas
    - negociación de contrato
audience: []
modules:
    - module.applications
related:
    - documents.manage
---

El módulo gestiona dos expedientes previos, antes de que surjan encargos
operativos o datos de empleados:

**Candidaturas a encargos (licitaciones):** Expediente con plazos,
potencial de valor, decisión go/no-go, lista de verificación de
documentación y paquetes de presentación versionados (snapshot con hash
SHA-256). Las licitaciones ganadas se transfieren de forma controlada a
un proyecto; las perdidas quedan disponibles para su análisis con el
motivo de la pérdida.

**Candidaturas de personal:** Necesidad de puesto → publicación →
expediente de candidatura con entrevistas, evaluaciones y decisión. Los
datos de los candidatos se almacenan cifrados y solo son visibles para
el área de personal (permisos de recruiting). Los rechazos inician
automáticamente la marca de borrado programado (por defecto seis meses
tras el plazo de reclamación según la ley AGG, configurable); el pool de
talento exige un consentimiento expreso y con plazo limitado. Las
aceptaciones generan un borrador de empleado — una cuenta activa solo
surge mediante la invitación deliberada. Una decisión es definitiva:
entrevistas, propuestas de cita y nuevas decisiones quedan bloqueadas
después. Los anuncios publicados pasan cada día a «Caducado» tras la
fecha de caducidad o el fin del plazo de candidatura; puede volver a
publicarlos con una nueva fecha o cerrarlos.

La decisión también pone fin a las entrevistas y propuestas de cita
abiertas: las entrevistas planificadas se marcan como canceladas (la nota se
conserva) y los enlaces de cita aún no elegidos caducan de inmediato. La
única excepción es el pool de talento: mientras el consentimiento sea
válido, «Readmitir desde el pool de talento» devuelve el expediente al
inicio del proceso de selección; la marca de borrado y el consentimiento se
eliminan y el plazo de borrado se fija de nuevo con la próxima decisión. Sin
consentimiento válido, el expediente permanece en el pool de talento hasta
que se aplique la marca de borrado.
**Negociaciones contractuales:** paso propio y versionado entre la
decisión de adjudicación o aceptación y el traspaso. Los puntos
bloqueantes abiertos y las aprobaciones pendientes (comercial + técnica,
autoaprobación bloqueada) impiden el cierre. Una aprobación vale para la
versión presentada: si se guarda una nueva versión después de haberse
concedido un nivel de aprobación, la aprobación comienza de nuevo con una
nueva ronda; la ronda anterior permanece visible en el expediente como
historial.

Las etapas de aprobación aparecen también en «Aprobaciones» para el rol que
la organización asigna al tipo de etapa — por defecto: comercial →
Contabilidad, técnica → Jefe de equipo, RR. HH. → Administración de
personal; se puede cambiar al editar la organización, en la sección
«Aprobaciones». Una decisión tomada allí tiene el mismo efecto que la
aprobación desde el expediente; allí también se puede rechazar una etapa
(con motivo) — una nueva versión inicia entonces la siguiente ronda.

Aviso legal: WorkDiary documenta el proceso, pero no sustituye el
asesoramiento jurídico — en particular, ninguna valoración sobre si las
condiciones contractuales son admisibles o económicamente razonables.
