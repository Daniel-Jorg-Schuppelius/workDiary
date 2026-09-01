---
title: "Órdenes de fabricación"
topic: manufacturing.orders
version: 1
audience: []
modules:
    - module.lager
related:
    - manufacturing.work-centers
    - procurement.orders
    - articles.master
    - inventory.stock
---

Las órdenes de fabricación representan la producción de un artículo a
partir de su lista de materiales o receta; solo son seleccionables los
artículos marcados como fabricables, y el sistema deriva la necesidad de
material de la cantidad objetivo, la variante y la lista de materiales.
Al liberar la orden se guarda una instantánea de la lista de materiales,
de modo que cambios posteriores no afectan a la orden en curso. El flujo
sigue una máquina de estados (borrador, liberada, en curso, en espera,
bloqueada, completada, anulada): el material se bloquea contra el stock
con **«Reservar»**, las notificaciones parciales registran cantidades
producidas, buenas, de desecho y de retrabajo, y con **«Entregar»** el
producto terminado se contabiliza como stock. Desde la página de detalle
la orden puede asignarse a un puesto de trabajo o subcontratarse a un
proveedor (genera un pedido); anular es irreversible y crear, notificar
y entregar requieren el permiso de contabilización de stock.
