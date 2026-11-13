---
title: "Crear el periodo siguiente"
topic: diary-entries.follow-up
version: 2
keywords:
    - periodo siguiente
    - año siguiente
    - orden periódica
    - orden anual
    - orden mensual
audience: []
related:
    - diary-entries.review
    - carry-forward.notes
    - customers.deadlines
---

Las órdenes con **periodo** (por ejemplo «Impuesto sobre la renta 2025») no se
vuelven a registrar para el periodo siguiente: **Crear el periodo siguiente**
crea la orden con un clic.

## Qué se copia

- cliente, tipo de entrada y grupo de productos,
- responsable, revisión obligatoria y persona revisora nombrada,
- los procedimientos de la orden como nuevas ejecuciones.

Las **notas para el próximo periodo** aparecen solas en la nueva orden. No se
copian el contenido y las notas, los tiempos ni el resultado.

## Vencimiento según los plazos del cliente

El vencimiento de la nueva orden procede de los **plazos del cliente**: la
fecha más temprana de los plazos del cliente en el nuevo periodo que el
conjunto de reglas asigna al tipo de entrada y al grupo de productos de la
orden. Los plazos que no proceden no cuentan. Sin un plazo adecuado, el
vencimiento queda vacío.

## Individual o en bloque

- **Individual:** en la orden, con **Crear el periodo siguiente**.
- **En bloque:** en el libro de órdenes, con **Periodo siguiente en bloque**,
  elija tipo de entrada y periodo. La vista previa muestra qué órdenes se
  crearán y cuáles ya existen; solo entonces se crean.

## Sin duplicados

Por cliente, tipo de entrada, grupo de productos y periodo hay como máximo una
orden. Si ya existe, se indica la existente en lugar de crear una segunda. Las
órdenes canceladas no cuentan.
