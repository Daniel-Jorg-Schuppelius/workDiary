---
title: "Connecter Billbee"
topic: admin.billbee
version: 1
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
---

WorkDiary connecte **Billbee** comme agrégateur multicanal : les commandes
d'Amazon, eBay, Otto, Kaufland, Shopify et autres convergent dans Billbee
et sont importées ici comme **miroir de commandes avec origine du canal**.

**Inbox-first :** Les acheteurs ne sont jamais créés à l'aveugle comme
clients. Les correspondances uniques ou les acheteurs récurrents déjà
affectés sont liés ; tout le reste apparaît comme proposition dans la boîte
d'intégration et y est décidé.

**Identifiants :** clé API (activée par le support Billbee), nom
d'utilisateur Billbee et mot de passe API distinct — chiffrés par
organisation, gérés via la carte du plugin (Administration → Plugins).

**Canal de retour des stocks :** Si l'organisation gère les stocks en mode
« externe » via Billbee, les mouvements locaux sont transmis comme **mises
à jour absolues de stock** par SKU (pas de dérive en cas de répétition).
Cela nécessite un mappage SKU entretenu — les produits sans équivalent
local restent visibles comme affectations ouvertes.

**Limitation :** Billbee autorise 2 requêtes par seconde ; la
synchronisation respecte automatiquement cette limite.
