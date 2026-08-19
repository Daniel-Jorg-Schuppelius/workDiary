---
title: "Medios de acceso"
topic: access.media
version: 1
audience: []
related:
    - assets.fleet
---

**Transpondedores, tarjetas y códigos** como inventario gestionado — la
ampliación de la entrega física de llaves. Cada medio tiene en todo momento
**exactamente un estado** (En almacén / Entregado / Perdido / Bloqueado /
Retirado) y un paradero documentado.

## Principios

- **El número del medio se guarda solo como hash** — quedan visibles los
  últimos cuatro dígitos. El texto claro solo se conoce al crearlo.
- **El titular es un usuario O una persona externa** (nombre + empresa) — un
  servicio de limpieza no tiene cuenta de empleado.
- **workDiary no controla ninguna instalación de acceso.** El estado
  administrativo aquí y el de la instalación allí se mantienen unidos por la
  tarea de bloqueo.

## Pérdida y bloqueo

Una declaración de pérdida pone el estado en **Perdido** y crea
obligatoriamente una **tarea de bloqueo** («Bloquear el medio …1234 en la
instalación X», vence en dos días). Solo quien realizó el bloqueo en la
instalación lo confirma — entonces el medio pasa a **Bloqueado** y la tarea a
hecha. Perdido y bloqueado son estados deliberadamente separados: justo esa
brecha debe ser visible, porque en ella el medio es un riesgo.

## Entrega y devolución

Cada entrega (entrega/devolución) queda en el **historial** del medio — con
titular, momento, devolución esperada y estado. Un medio entregado no puede
retirarse — primero recogerlo.
