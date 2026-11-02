---
title: "Plantillas periódicas (plan)"
topic: finance.resale.plan
version: 1
keywords:
    - plantillas periódicas
    - factura recurrente
    - propuesta de plantillas
    - comprobación del plan
    - estado de ejecución
    - suscripción sin plantilla
audience: []
modules:
    - module.reselling
related:
    - finance.resale
    - finance.resale.accounts
    - finance.resale.periods
---

Las **plantillas periódicas** son las facturas recurrentes del programa de
contabilidad; el registro las refleja, asigna sus líneas a las suscripciones
y comprueba el inventario frente al plan. La página «Propuesta de plantillas»
indica qué plantillas faltan por crear.

**Plantillas periódicas (plan):** Si un programa de contabilidad emite las
facturas, las facturas recurrentes se crean allí como plantillas periódicas. El
registro las refleja (Lexoffice: a diario y tras la sincronización de
comprobantes, solo lectura) y muestra en la cuenta de licencias por plantilla su
estado, ritmo, próxima ejecución y las posiciones de licencia con las
suscripciones que facturan. La asignación suscripción ↔ posición la propone la
ejecución — primero por la factura que la plantilla creó por última vez y que
está asignada a un periodo, si no por mismo destinatario, artículo y cantidad
(con varios clientes finales de un socio solo si se nombran): usted confirma, asigna a mano o quita. Los periodos que
factura una plantilla activa quedan fuera de la propuesta de factura; lo que
termina antes de su primera ejecución permanece. Así los años antiguos pueden
facturarse aunque la plantilla ya exista. Las
**comprobaciones existencias ↔ plan** avisan antes de la factura: suscripción
sin plantilla, plantilla sin suscripción («Crear suscripción desde la plantilla»
toma artículo, cantidad, ritmo y precio), cantidad distinta, plantilla que sigue
tras el fin de la suscripción, plantilla pausada o finalizada con suscripción
activa, próxima ejecución a más de siete días del inicio del periodo, última
ejecución fallida: en el mosaico, el resumen semanal y la cola de trabajo. Cada
factura reflejada lleva la referencia a su plantilla.

**Propuesta de plantillas y estado de ejecución:** La página «Propuesta de
plantillas» (desde la lista de periodos) lista, por destinatario con
facturación externa, las suscripciones sin plantilla asignada con artículo,
texto de línea, cantidad, unidad, precio unitario, intervalo y primera
ejecución; los destinatarios cubiertos por la ejecución periódica del registro
están marcados. La plantilla se crea en el programa de contabilidad, el
siguiente reflejo propone la asignación. Por plantilla, la cuenta de licencias
muestra qué fue de la última ejecución — factura pagada, abierta (comprobar
el envío en el programa) o falta — y, si falló, el mensaje del programa como
tooltip; la API de Lexoffice no sabe si una factura salió por correo. Una
línea de plantilla admite como máximo tantas licencias como factura; las
suscripciones de dominio solo se asignan si el dominio se nombra exactamente.
