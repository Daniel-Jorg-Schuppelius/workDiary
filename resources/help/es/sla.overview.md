---
title: "SLA, contratos y niveles de servicio"
topic: sla.overview
version: 9
keywords:
    - acuerdo de nivel de servicio
    - tiempo de respuesta
    - tiempo de resolución
    - contrato de servicio
    - incumplimiento de SLA
    - plazo vencido
    - escalado
    - ticket atrasado
    - tasa de cumplimiento
    - informe SLA
audience: []
related:
    - glossary.core
---

Los contratos SLA (Service Level Agreements) guardan, por cliente o como
**Contrato predeterminado** para todos los clientes, los plazos de respuesta y
de resolución acordados por prioridad (**Plazos por prioridad**),
opcionalmente con **Horario laboral**; sin él, los plazos corren en
tiempo natural. Encontrará los contratos en **Service desk** →
**Contratos SLA**. A partir de estos valores objetivo, WorkDiary deduce
el estado SLA de un ticket de servicio y documenta los incumplimientos de
forma inalterable.

## Estado SLA en el ticket

Cada ticket de servicio con un plazo SLA muestra su estado de resolución
como distintivo:

- **SLA en plazo**: queda tiempo suficiente hasta el plazo de
  resolución.
- **SLA en riesgo**: el tiempo restante es como máximo el 20 % del plazo
  total.
- **SLA incumplido**: el plazo se ha superado (o el ticket se confirmó o
  resolvió demasiado tarde).
- **SLA cumplido**: el ticket se resolvió a tiempo.

Los tickets sin plazo muestran «Sin SLA». El plazo de respuesta se evalúa
del mismo modo y se comprueba en la primera confirmación.

## Registro de incumplimientos y detección

Los plazos superados se registran en un registro de incumplimientos,
exactamente una vez por ticket y tipo («Tiempo de respuesta» o «Tiempo de
resolución»). Se detectan:

1. en la comprobación automática de los tickets abiertos, que de forma
   predeterminada se ejecuta cada cinco minutos,
2. en los cambios de estado, cuando la primera respuesta o la resolución
   llega demasiado tarde.

A cada incumplimiento se le puede asignar una **Causa** en la **Lista de
incumplimientos** del informe SLA y marcarlo con **Confirmar**; para ello
se necesita el permiso **Confirmar incumplimientos de SLA**.

## Escalado

La comprobación automática notifica a la persona asignada los tickets en
riesgo e incumplidos. Si el evento sigue sin resolverse, WorkDiary escala
según las **Reglas de notificación** de la organización al rol de
escalado configurado allí (de forma predeterminada, los jefes de
equipo). Además, WorkDiary aplica los niveles guardados en **Escalado**
en el contrato SLA.

Usted mantiene los niveles en el contrato, en **Niveles de escalado**: por
nivel, los minutos tras vencer el plazo de solución y el destinatario – el
responsable del ticket, un rol o una persona.

## Qué contrato se aplica

Para un ticket nuevo se aplica el primer contrato que encaje en este orden:
contrato del activo, contrato del proyecto, contrato del cliente, **SLA
predeterminado** de la cola, **contrato predeterminado** de la organización.
El contrato del activo es el compromiso más estrecho: vale para este objeto
concreto y se elige en el formulario del activo (tema «Activos y flota»). Solo
se aplica si está activo y pertenece al cliente del ticket o no tiene cliente;
si no, se aplica el resto del orden. Un cambio de cola lo mantiene. Si el contrato no
define horario de atención, se aplica el de la cola; la región de festivos de
la cola sustituye a la de la organización. El estado del contrato se congela
en el ticket: los cambios posteriores del contrato no reinterpretan los
tickets existentes.

## Pausas

En **Pausar SLA en** el contrato define en qué estados de espera se detiene
el reloj. Al reanudar – también por una respuesta del cliente – los plazos
abiertos se desplazan la duración de la pausa.

## OLA

Un contrato con **OLA (objetivo interno)** y un equipo no es una promesa al
cliente: nunca se usa como SLA de un ticket y no puede ser contrato
predeterminado ni vincularse a un cliente o a un proyecto. Los tickets de las
colas del equipo muestran además el objetivo interno en el reloj SLA.

Si un ticket abierto supera el objetivo interno, el responsable del equipo del
OLA recibe una única notificación (si no lo hay, la persona asignada); después
se aplica el escalado de la regla de notificación **Objetivo interno (OLA)
superado**. WorkDiary no lleva un registro de incumplimientos para los OLA.

## Mantenimientos obligatorios por contrato

La página del contrato muestra en **Mantenimientos obligatorios por
contrato** los planes de mantenimiento vinculados a este contrato, con
el activo, el próximo vencimiento y la acción **Al vencimiento**. Los
planes con **Obligación contractual** llevan la marca del mismo nombre.
La vinculación se establece en el diálogo del plan de mantenimiento del
activo (tema «Activos y flota»). Con la acción **Crear un ticket de
servicio** se crea un ticket por vencimiento al que se aplica este
contrato.

## Contingentes de tiempo incluido

En el contrato SLA, **Añadir contingente** fija por período (mes, trimestre,
año) las horas incluidas, el **umbral de aviso** en porcentaje a partir del cual
el control de plazos notifica y, como justificante, la **tarifa plana** y la
**tarifa de horas adicionales**. El consumo es el tiempo facturable de las
personas para el cliente del contrato en el período. La página del contrato y el
informe de SLA muestran el consumo, la tarifa plana y el importe de las horas
adicionales (horas por encima del contingente × tarifa). workDiary no emite
factura con ello: la facturación se hace en el programa de contabilidad. Editar
y eliminar requieren el permiso para gestionar los contratos SLA.

## Informe de SLA

El **Informe de SLA** (**Análisis** → **Proyectos y clientes** → **SLA**)
muestra para el periodo elegido los **Tickets con SLA**, la **Tasa de
cumplimiento** y los **Incumplimientos**, desglosados **Por tipo**, **Por
prioridad**, **Por cliente** y **Por causa**, además de una **Lista de
incumplimientos** con salto al ticket y las **Cuotas de tiempo
incluido**. El informe puede exportarse en PDF, CSV y Excel. Puede
consultarlo quien tenga el permiso **Ver estado e informe de SLA**; para
exportarlo se requiere además el permiso **Exportar los informes**; sin él no
aparecen los botones de exportación. Los administradores pueden hacer ambas
cosas siempre.

## Propuesta de material

Si el ticket tiene asignado un equipo con lista de componentes, muestra las **piezas de desgaste vencidas** (según intervalo de sustitución y fecha de montaje) como propuesta para la intervención; lo que realmente se cambia lo decide el técnico. La misma tarjeta aparece en los pedidos con equipo.

**Tickets confidenciales en el informe:** en los tickets confidenciales que no
puede abrir, la lista de incumplimientos de SLA no muestra ni el ticket ni el
cliente; en el desglose por cliente cuentan como «Ticket confidencial».
