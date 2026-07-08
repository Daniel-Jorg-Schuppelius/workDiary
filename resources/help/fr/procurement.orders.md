---
title: "Achats & commandes"
topic: procurement.orders
version: 1
audience: []
related:
    - inventory.stock
    - articles.master
    - manufacturing.orders
    - contacts.manage
---

Les commandes enregistrent l'achat d'articles auprès d'un fournisseur
vers un entrepôt cible : créées en brouillon avec des lignes (article,
quantité, prix d'achat optionnel), puis commandées ; le statut passe par
brouillon, commandé, partiellement livré, livré ou annulé. La réception
de marchandises se comptabilise ligne par ligne et augmente le stock de
façon valorisée ; livraisons partielles et excédentaires, ainsi que les
avis d'expédition (ASN), sont pris en charge, et la vue « Réceptions
attendues » liste les lignes ouvertes triées par date de livraison. Les
propositions de commande automatiques calculent le besoin par entrepôt
(seuil d'alerte + demandes ouvertes) en tenant compte de la quantité
minimale et du fournisseur préféré et créent des brouillons à vérifier.
Créer, commander et comptabiliser exigent le droit de mouvement de
stock ; l'annulation d'une commande est irréversible.
