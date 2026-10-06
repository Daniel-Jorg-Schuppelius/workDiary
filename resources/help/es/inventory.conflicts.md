---
title: "Conflictos con sistemas externos (existencias y artículos)"
topic: inventory.conflicts
version: 3
audience:
    - admin
    - geschaeftsfuehrung
    - teamleitung
modules:
    - module.lager
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

**Conflictos de artículos:** La misma lista muestra los artículos
modificados localmente cuyo estado difiere en el sistema externo conectado
(por ejemplo, Lexware Office) — con la estrategia de conflicto del plugin
en «Revisión manual». Para cada conflicto se muestran el artículo, los
campos divergentes y ambos valores, uno junto al otro. Tres vías:
*Mantener local* cierra el conflicto; el estado local se mantiene y se
transfiere al sistema externo en la próxima sincronización. *Adoptar el
estado del sistema externo* (por ejemplo «Adoptar el estado de Lexoffice»)
obtiene el artículo de nuevo desde el sistema externo y sobrescribe el
cambio local. *Descartar* cierra el conflicto sin conciliación — ambos
estados permanecen como están; si el artículo sigue difiriendo en la
próxima sincronización, se crea un nuevo conflicto.

**Permisos y filtros:** La pestaña «Conflictos» de la barra de pestañas
del almacén muestra el número de conflictos abiertos. Para consultar basta
el permiso de lectura de existencias o el de lectura de artículos; sin
permiso de existencias solo ve conflictos de artículos, sin permiso de
artículos solo conflictos de existencias. La resolución depende del tipo:
los conflictos de existencias requieren el permiso de contabilización,
porque la compensación es una contabilización real de almacén; los
conflictos de artículos requieren el permiso de gestión de artículos. La
lista puede filtrarse por conflictos abiertos o todos y por tipo
(existencias, artículo).

Los conflictos abiertos deben revisarse con prontitud: mientras existan,
las existencias locales y externas divergen — con consecuencias para
disponibilidades, propuestas de pedido y valoración.
