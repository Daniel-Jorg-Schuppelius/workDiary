---
title: "Conflictos de existencias (transferencia externa)"
topic: inventory.conflicts
version: 1
audience:
    - admin
related:
    - inventory.stock
    - warehouses.manage
---

Si un sistema externo tiene la soberanía sobre las existencias (por
ejemplo, un ERP de mercancías), WorkDiary replica allí cada movimiento
de almacén contabilizado localmente. Esta página muestra los casos en
los que la replicación ha fracasado definitivamente — son el lugar para
el retrabajo funcional.

**Transferencia con idempotencia:** Cada movimiento genera como máximo
una orden de entrega en una cola persistente. Si el mismo proceso se
lanza varias veces, aun así solo se produce una transferencia — las
contabilizaciones duplicadas en el sistema externo quedan por tanto
excluidas. Los errores transitorios se reintentan automáticamente.

**Cuándo surge un conflicto:** Si la entrega de un movimiento fracasa
definitivamente — por ejemplo porque el sistema externo lo rechaza —,
surge un conflicto. La contabilización local se mantiene, pero las
existencias externas divergen. Cada conflicto aparece aquí con
referencia al movimiento subyacente y espera una decisión consciente.

**Resolución:** Por conflicto existen dos vías. *Mantener local* acepta
expresamente la divergencia y cierra el conflicto sin ninguna
contabilización adicional — razonable cuando el estado local es
funcionalmente correcto. *Compensar* neutraliza el movimiento local
mediante un contraasiento por el mismo importe en las mismas
existencias. Nunca se borra a posteriori ni se revierte técnicamente; el
diario de almacén permanece sin lagunas y cada decisión se registra con
persona y momento.

**Permisos y filtros:** Para consultar basta el permiso de lectura de
existencias; para resolver se requiere además el permiso de
contabilización, porque la compensación es una contabilización real de
almacén. La lista puede filtrarse por conflictos abiertos o todos.

Los conflictos abiertos deben revisarse con prontitud: mientras existan,
las existencias locales y externas divergen — con consecuencias para
disponibilidades, propuestas de pedido y valoración.
