---
title: "Compras y pedidos"
topic: procurement.orders
version: 1
audience: []
related:
    - inventory.stock
    - articles.master
    - manufacturing.orders
    - contacts.manage
---

Los pedidos registran la compra de artículos a un proveedor contra un
almacén de destino: se crean como borrador, se rellenan con líneas
(artículo, cantidad, precio de compra opcional) y luego se piden. La
recepción de mercancía se contabiliza contra la línea individual y
aumenta el stock valorado; se admiten entregas parciales y excesos, así
como avisos de entrega (ASN). Las propuestas automáticas de pedido
calculan la necesidad por almacén según el stock mínimo y las
solicitudes abiertas. Crear, pedir y contabilizar requieren el permiso
de movimientos de stock; cancelar un pedido no es reversible.
