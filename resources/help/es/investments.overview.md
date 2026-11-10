---
title: "Planificación de inversiones"
topic: investments.overview
version: 3
keywords:
    - solicitud de inversión
    - solicitud de presupuesto
    - gastos de capital
    - CapEx
    - aprobación de inversión
    - comparar ofertas
    - desviación presupuestaria
    - previsto frente a real
    - análisis de rentabilidad
    - lecciones aprendidas
audience: []
modules:
    - module.investments
related:
    - documents.manage
---

El expediente de inversión acompaña una inversión desde la idea hasta la
evaluación posterior: motivo, objetivo/beneficio, riesgo, comparación de
variantes con ofertas (costes únicos y recurrentes, plazo de entrega,
calidad, recomendación), solicitud de presupuesto versionada y cadena de
aprobaciones.

**Aprobaciones:** Por debajo del umbral basta una aprobación comercial;
a partir del umbral (configurable, estándar 10.000 €) rige el principio
de los cuatro ojos con un nivel adicional de dirección. El solicitante
nunca puede aprobar por sí mismo. Los presupuestos aprobados se congelan
como snapshot — un incremento pasa siempre por una desviación
presupuestaria aprobada y una adenda (nueva solicitud; el estado
anterior se conserva como «sustituido»).

**Bandeja de aprobaciones:** Las fases abiertas de una solicitud de
presupuesto aparecen en «Aprobaciones» y en la cola de trabajo para el
rol que su organización asigna al tipo de fase (predeterminado:
comercial → contabilidad, dirección → dirección). Al presentarla y al
pasar a la siguiente fase se notifica a los responsables. Allí se decide
como en el expediente; solo aprueba quien puede aprobar inversiones.

**Decidir desde el expediente:** Rige la misma responsabilidad que en la
bandeja. «Liberar» y «Rechazar» solo los ve quien es responsable de la
fase abierta y puede decidirla — ni el solicitante ni quien decidió una
fase anterior de la misma ronda. Los demás ven a qué espera la solicitud,
por ejemplo «En espera de la etapa 2: Dirección (rol Dirección).» También
los administradores deciden solo con el rol de la fase; usted define qué
rol es en la configuración de la organización, en «Aprobaciones».

**Aplazamiento y rechazo:** Durante la planificación, el expediente se
puede aparcar con «Aplazar»; «Reanudar» lo devuelve exactamente a la fase
en la que estaba. El rechazo de una solicitud de presupuesto es
definitivo: un expediente rechazado sigue rechazado y no se puede aplazar
ni volver a solicitar — un nuevo intento empieza con un nuevo expediente.

**Ejecución y valores reales:** Tras la aprobación se vinculan proyecto,
pedido, activo, factura de entrada o documento. La vista
previsto-real muestra el presupuesto aprobado, los fondos comprometidos
(pedidos) y los costes reales (facturas de entrada vinculadas, costes de
adquisición de activos, valores manuales) como proyección — los módulos
rectores siguen siendo rectores; WorkDiary no sustituye ninguna
contabilidad.

**Evaluación posterior:** Tras el cierre o la cancelación se documentan
el beneficio real, la rentabilidad, las lecciones aprendidas y las
medidas de seguimiento.
