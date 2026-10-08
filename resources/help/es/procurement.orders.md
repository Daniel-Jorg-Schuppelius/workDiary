---
title: "Compras y pedidos"
topic: procurement.orders
version: 3
keywords:
    - orden de compra
    - pedido a proveedor
    - recepción de mercancía
    - entrega parcial
    - aviso de envío
    - propuestas de reposición
    - punto de pedido
    - cantidad mínima de pedido
    - entregas previstas
    - reponer stock
audience: []
modules:
    - module.lager
related:
    - inventory.stock
    - articles.master
    - manufacturing.orders
    - contacts.manage
---

Los pedidos de compra registran la compra de artículos a un proveedor
para un almacén de destino. Los encontrará en **Ventas y facturación** →
**Aprovisionamiento y catálogos** → **Pedidos de compra**. Con **Nuevo
pedido** se crea primero un borrador con **Proveedor**, **Almacén** y,
opcionalmente, **Fecha de entrega** y **Nota**. Con **Añadir línea**
completa las líneas del pedido (**Artículo**, **Cantidad** y,
opcionalmente, **Precio unitario**) y después realiza el pedido con
**Pedir**. Solo se pueden pedir los artículos marcados como
**Comprable**. El estado pasa por «Borrador», «Pedido», «Parcialmente
recibido», «Recibido» o «Cancelado».

La **Entrada de mercancía** se registra contra cada línea del pedido y
aumenta el stock de forma valorada; se admiten entregas parciales y en
exceso, y las columnas **Pedido** y **Recibido** muestran el avance.
Como alternativa, con **Registrar aviso de entrega** puede anotar las
cantidades anunciadas para un pedido y registrar después la entrada a
partir de él con **Registrar entrada**. La pestaña **Entradas previstas**
abre la vista «Entradas previstas», que enumera las líneas abiertas de
los pedidos realizados, ordenadas por fecha de entrega.

La pestaña **Sugerencias de pedido** determina, tras **Seleccionar
almacén**, la **Necesidad** a partir del punto de pedido y de las
solicitudes abiertas y propone cantidades (**Sugerido**) teniendo en
cuenta la cantidad mínima de pedido y el proveedor preferido. **Crear
pedidos** genera a partir de ellas borradores por proveedor, que conviene
revisar antes de pedir. Crear, pedir y registrar requieren el permiso
**Registrar movimientos de stock**; **Cancelar** un pedido es
irreversible.
