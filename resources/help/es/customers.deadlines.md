---
title: "Plazos de clientes"
topic: customers.deadlines
version: 1
keywords:
    - Plazos de clientes
    - Plazos
    - Declaración anticipada del IVA
    - Prórroga permanente
    - Retenciones salariales
    - Declaración recapitulativa
    - Declaración anual
    - Justificante de cotizaciones
    - Visita de asesoramiento
    - Alcance de servicios
audience: []
related:
    - contacts.manage
    - work-queue.overview
    - delegations.manage
    - accounting.filings
---

Algunos plazos no dependen de un contrato, sino de las características del
cliente y de la ley: la declaración anticipada del IVA de un cliente de una
asesoría, el justificante de cotizaciones de un cliente de nóminas o la visita
de asesoramiento de un cliente de cuidados. workDiary calcula estos **plazos de
clientes** a partir de las características y del alcance de servicios del
cliente, avisa a tiempo y los marca como cumplidos en cuanto se completa la
orden correspondiente. La interfaz dice «cliente»; en workDiary «mandante»
designa a la organización.

## Conjuntos de reglas

Un **conjunto de reglas** contiene las normas legales de un sector. Está
activado cuando lo aporta el perfil sectorial o cuando lo activa en **Plazos
de clientes › Configuración**.

- **Asesoría fiscal:** declaración anticipada del IVA (día 10 tras el período,
  un mes más tarde con prórroga permanente), pago anticipado especial (10 de
  febrero en declaración mensual con prórroga), retenciones salariales (día 10,
  nunca prorrogadas), declaración recapitulativa (día 25, nunca prorrogada),
  declaraciones anuales por impuesto según el alcance de servicios (con
  asesoramiento hasta finales de febrero del segundo año siguiente, si no el 31
  de julio; plazos transitorios hasta 2024) y, con el servicio **nóminas**, el
  justificante y el vencimiento de las cotizaciones (antepenúltimo día hábil
  bancario; justificante dos días hábiles bancarios antes). Si un plazo fiscal
  cae en fin de semana o en un festivo **en la sede de la oficina tributaria**,
  rige el siguiente día hábil; con varias cajas de salud rige la fecha más
  temprana de todos los estados federados.
- **Cuidados a domicilio:** visita de asesoramiento según el § 37.3 SGB XI;
  obligatoria solo con prestación económica y grado 2 a 5, una vez por
  semestre; con grado 4 y 5, a petición, una vez por trimestre. workDiary lee
  el grado de la clasificación del cliente.

## Gestionar las características

En el **panel del cliente**, la tarjeta **Plazos de clientes** muestra las
características y los próximos plazos abiertos. **Características de plazos**
abre el diálogo, precargado como la versión vigente («como el año anterior»).
Un cambio rige desde el día elegido y cierra la versión anterior; los plazos
pasados conservan su base. workDiary recalcula después solo los plazos
**abiertos**; los cumplidos, prorrogados o requeridos por anticipado se
mantienen. No se genera nada que venciera antes del inicio de la primera
versión.

Si falta el **estado federado de la oficina tributaria** o de las entidades
recaudadoras, workDiary usa la región de festivos de la organización y marca
el plazo.

En **Alcance de servicios** figuran los servicios del cliente (tipo de entrada
y grupo de productos, obligatorio u opcional, tipo de período, vigencia). El
conjunto de asesoría fiscal toma de ahí las declaraciones anuales y las
nóminas; el plan de servicios los muestra por período.

## Trabajar con los plazos

Un clic en un plazo lo abre con su historial. Puede marcarlo como
**cumplido**, **prorrogarlo** (nueva fecha y motivo), marcarlo como
**requerido por anticipado** (la oficina tributaria exige la declaración antes,
§ 149.4 AO), indicar que **no procede** o **reabrirlo**. Una orden completada
con período cumple por sí sola el plazo correspondiente, por ejemplo una orden
de **visita de asesoramiento** de 2026-H1 la visita del primer semestre.

## Resumen, avisos y sustituciones

- **Plazos de clientes** muestra todos los plazos con semáforo, filtros por
  conjunto de reglas, tipo, responsable, mes y estado, y exportación a CSV o
  Excel.
- La **persona responsable** recibe un aviso agrupado cuando los plazos entran
  en el preaviso (7 días por defecto, ajustable por conjunto) y otro al
  vencer. Los plazos hasta el fin de semana y los vencidos aparecen en su
  **bandeja de trabajo** y en el **calendario**.
- Durante una **sustitución**, la persona sustituta ve estos plazos y avisos
  con la indicación «En sustitución de …». En caso de baja o traspaso
  definitivo, la responsabilidad pasa a la persona sucesora.

Pueden ver los plazos las personas con el permiso **Ver los plazos de
clientes**; características, servicios y todos los plazos los gestiona quien
tenga **Gestionar los plazos de clientes y sus características**. La persona
responsable trabaja también con sus propios plazos con el permiso de lectura.

## Lo que workDiary no hace

No envía nada a ELSTER, DATEV ni a las cajas de dependencia y no lleva la
contabilidad de los clientes. Los plazos pactados siguen siendo obligaciones
contractuales; los de equipos, planes de inspección.
