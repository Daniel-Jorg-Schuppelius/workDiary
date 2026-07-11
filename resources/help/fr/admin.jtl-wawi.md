---
title: "Connecter JTL-Wawi"
topic: admin.jtl-wawi
version: 1
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
---

WorkDiary connecte JTL-Wawi comme **système de gestion des stocks
maître** : les articles (parent/enfant), les entrepôts et les stocks
viennent de JTL ; WorkDiary les lit et retransmet ses propres
mouvements de stock de manière contrôlée.

**Modes de fonctionnement :** Une Wawi *OnPremise* se connecte via son
instance API locale (à créer dans l’administrateur JTL, port par
défaut 5883). Si la Wawi se trouve sur votre propre réseau,
l’autorisation des adresses privées doit être activée explicitement —
cette autorisation est auditée. La *passerelle cloud* utilise l’ID
client/le secret et l’ID de tenant du portail partenaire JTL.

**Enregistrement de l’app (OnPremise) :** Ouvrir d’abord « Admin >
Enregistrement des apps » dans JTL-Wawi, puis lancer l’enregistrement
ici et approuver l’app dans la Wawi. La clé API n’est délivrée
**qu’une seule fois** et stockée chiffrée — elle n’apparaît jamais
dans les journaux ni les diagnostics.

**Associations :** Après la première synchronisation, associez les
entrepôts JTL aux entrepôts WorkDiary (1:1 pour les écritures). Les
articles sont associés automatiquement via SKU et GTIN ; les cas
ambigus arrivent dans la boîte de réception des intégrations où vous
décidez — WorkDiary ne crée jamais d’articles automatiquement.

**Pilotage des stocks :** Sous « Pilotage des stocks », vous
choisissez qui pilote : *local* (WorkDiary), *externe* (JTL pilote,
WorkDiary retransmet via l’outbox) ou *lecture seule*. Le retour au
mode « local » reprend les stocks JTL comme inventaire d’ouverture.

**Note bêta :** L’API JTL-Wawi fonctionne actuellement en programme
bêta/pilote. Après la sortie officielle, elle peut dépendre de
l’édition et devenir payante ; une licence expirée mène à un état
bloqué visible, jamais à des écritures erronées silencieuses.
