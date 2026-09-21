---
title: "Gestionar el acceso al catálogo B2B"
topic: admin.b2b-catalog
version: 1
audience:
    - admin
related:
    - admin.integrations
    - supplier-catalogs.overview
    - articles.master
---

El acceso al catálogo B2B permite a los clientes empresariales obtener
artículos y precios directamente de su stock: como traspaso del carrito desde
su sistema de compras o como archivo de catálogo.

**Accesos:** Emite uno por cliente. El secreto se muestra **una sola vez** y
nunca más: anótelo enseguida. Un acceso perdido no se recupera, se **renueva**;
el anterior queda inválido. Revoque el acceso que ya no necesite.

**Publicación:** Solo se ve lo que publica. Puede fijar un precio de cliente
por artículo; sin precio propio rige el habitual.

**Pedidos:** Los pedidos que regresan del sistema de compras aparecen en la
vista general y se comprueban como cualquier otra entrada antes de convertirse
en un encargo: nada se adopta a ciegas.

**Archivo de catálogo:** En lugar del carrito puede exportar el stock
publicado como archivo de catálogo y precios que leen los ERP habituales.

**Seguridad:** Las páginas del cliente dependen únicamente del secreto de
acceso. Trátelo como una contraseña y renuévelo cuando cambie el personal del
cliente.
