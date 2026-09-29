---
title: "Stock de licences"
topic: finance.license-stock
version: 1
audience: []
modules:
    - module.reselling
related:
    - finance.resale
---

Le **stock de licences** gère les licences que vous achetez par lots et
revendez à l’unité — par exemple dix licences VPN, chacune composée de
plusieurs clés associées. On compte toujours des licences, jamais des clés.

## Produit et lot

1. **Créer un produit de licence :** nom, fabricant et clés par licence,
   p. ex. « Numéro de série » et « Clé d’activation ». Le **seuil de
   réapprovisionnement** détermine quand « À recommander » apparaît :
   vide = jamais, 0 = seulement en cas de rupture. En option, reliez un
   article du catalogue d’articles (fichier articles ou logiciel comptable
   connecté).
2. **Créer un lot de licences :** référence du lot, date d’achat,
   fournisseur, quantité et, en option, la pièce d’achat. Autant de
   licences numérotées que d’achetées sont créées, d’abord sans clés. Les
   rachats sont de nouveaux lots.
3. **Saisir les clés :** individuellement via « Gérer les clés » ou en CSV
   avec une ligne par licence (modèle dans le lot). L’aperçu montre les
   lignes et les erreurs ; seul un fichier sans erreur est repris, et
   seulement après votre confirmation.

## Statut d’une licence

- **Incomplète :** au moins une clé manque — non vendable.
- **Disponible :** toutes les clés présentes, non vendue, non bloquée.
- **Vendue :** affectée à un seul client, avec le jeu de clés complet.
- **Bloquée :** retirée de la vente avec un motif.

Les licences achetées sont toujours la somme des disponibles, vendues,
incomplètes et bloquées. Le stock est actuel et ne dépend pas de la période
de l’en-tête.

## Vendre et corriger

- **Vendre une licence** propose la licence disponible la plus ancienne. La
  vente est documentée mais ne crée pas de facture ; un numéro de facture
  n’est qu’une référence. Avec un client final comme titulaire, le client
  reste le destinataire de la facture.
- **Corriger la vente** affecte la même licence sans interruption à un autre
  client ; les deux restent dans l’historique.
- **Reprendre la vente** bloque la licence, car sa clé a peut-être déjà été
  utilisée. Elle ne peut être libérée qu’avec un motif et votre confirmation.

## Protéger les clés

Les clés sont chiffrées. Les listes, la fiche client et les formulaires ne
les affichent jamais ; le texte en clair n’apparaît que via « Afficher les
clés », avec le droit spécifique « afficher les clés de licence en clair »,
qui n’est attribué automatiquement à aucun rôle. Chaque accès est journalisé
— sans la valeur.
