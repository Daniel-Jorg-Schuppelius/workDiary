---
title: "Numéros de série"
topic: serials.tracking
version: 1
audience: []
modules:
    - module.lager
related:
    - inventory.stock
    - articles.master
    - manufacturing.orders
---

Les numéros de série suivent chaque appareil sur tout son cycle de vie :
chaque numéro est unique dans l'organisation et passe par les statuts
créé, en stock, réservé, livré, repris, bloqué ou mis au rebut, avec
lien vers l'article, la variante, l'entrepôt et, à la livraison, le
client livré. La vue d'ensemble se filtre par statut et numéro ; la
page de détail affiche le passeport de l'appareil (origine, ordre de
fabrication, expédition, statut), utile pour les contrôles de garantie
et de fraude. La vérification contrôle l'authenticité et le statut
actuel d'un numéro saisi. Le blocage marque un appareil comme
indisponible et est réversible ; la mise au rebut est définitive. La
lecture exige le droit de lecture du stock, le blocage et la mise au
rebut le droit de mouvement de stock.
