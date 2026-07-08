---
title: "Transferencia a facturación"
topic: finance.transfers
version: 1
audience: []
related:
    - exports.payroll
    - admin.surcharge-rules
    - roles.buchhaltung
    - glossary.core
---

La transferencia a facturación envía **tiempos** y **materiales**
facturables al sistema de facturación líder (DATEV o Lexoffice); la
factura se crea siempre en el programa externo y WorkDiary solo aporta
las posiciones verificadas. Flujo: crear la transferencia como
borrador eligiendo canal («Servicios/Tiempo» o «Productos/Material») y
destino, revisar y **confirmar** las posiciones y luego **ejecutar**
hasta el estado final «Entregado». El estado «Entregado» es definitivo
y bloquea las posiciones; las correcciones se hacen mediante
transferencias de anulación o diferencia, nunca revirtiendo en
silencio. Los canales de tiempo y material tienen permisos separados.
