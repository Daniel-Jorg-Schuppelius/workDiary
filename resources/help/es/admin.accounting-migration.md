---
title: "Cambio de software contable"
topic: admin.accounting-migration
version: 1
audience:
    - admin
related:
    - admin.plugins
    - customers.billing
---

El cambio de software contable lleva a una organización de un sistema al
siguiente de forma controlada (primera ruta admitida: Lexoffice → orgaMAX).
WorkDiary no copia a ciegas de un sistema a otro: asigna ambos sistemas
externos a los mismos objetos de negocio locales.

Pasos: planificar (áreas de datos + fecha de corte, un solo cambio por
organización a la vez) → análisis como simulación (no escribe en ningún
sistema externo) → decidir individualmente los registros ambiguos →
operación paralela (los documentos antiguos se liquidan en el sistema
origen) → conmutación (desde la fecha de corte los nuevos documentos se
crean exclusivamente en el sistema destino; el envío al origen queda
bloqueado técnicamente y la conmutación permanece bloqueada mientras haya
conflictos) → finalización con protocolo CSV.

Principios: los documentos finalizados **nunca** se reconstruyen en el
sistema destino — permanecen localizables como histórico con número, estado
y origen. Cada paso queda registrado en una cadena de eventos a prueba de
manipulaciones.
