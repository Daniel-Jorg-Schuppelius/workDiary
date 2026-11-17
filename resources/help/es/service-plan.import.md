---
title: "Importar listas y comparar la lista de clientes"
topic: service-plan.import
version: 1
keywords:
    - importar lista
    - Excel
    - CSV
    - lista anual
    - lista por periodos
    - comparación de clientes
    - DATEV
    - lista de mandantes
audience: []
related:
    - service-plan.overview
    - customers.deadlines
---

## Importar lista

Importe listas de Excel o CSV existentes con **Importar lista** en el plan de
servicios. Primero una **vista previa** revisa cada fila y después importa.

- **Lista anual:** número de cliente, servicio, estado, observación;
  opcionalmente documentos recibidos, obligatorio y responsable.
- **Lista por periodos:** número de cliente, servicio y una columna por
  periodo (Ene … Dic, T1 … T4 o S1, S2) con el estado.
- Si no hay columna «servicio», elija el servicio para toda la lista.
- Las columnas con el nombre de una característica de los plazos de clientes
  (p. ej. periodicidad del IVA) adoptan las características como nueva
  versión.

La asignación **nunca adivina**: clientes solo por el número de cliente
exacto, servicios solo si encaja exactamente una plantilla o un tipo de
entrada, responsables solo por el nombre o el correo de un miembro. Una fila
con un número de cliente desconocido no crea nada y aparece en la vista
previa.

Se adoptan el alcance de servicios que falta (el existente se mantiene), las
características, la observación y «documentos recibidos» del periodo en curso
(solo donde aún no hay nada) y «no procede». Si lo desea, el estado del
periodo en curso se convierte en orden donde todavía no la hay.

## Comparar lista de clientes

**Comparar lista de clientes** compara su base de clientes con una lista de
otro programa, por ejemplo la exportación de mandantes de DATEV (número de
mandante, nombre). La vista previa muestra propuestas:

- **Nuevo:** número desconocido — crear cliente.
- **Cambio de nombre:** número conocido, nombre distinto — renombrar cliente.
- **Falta en la lista:** solo a título informativo.

No se sobrescribe ni se crea nada antes de que marque una propuesta.
