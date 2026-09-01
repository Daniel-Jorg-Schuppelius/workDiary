---
title: "Conflits de stock (transfert externe)"
topic: inventory.conflicts
version: 1
audience:
    - admin
modules:
    - module.lager
related:
    - inventory.stock
    - warehouses.manage
---

Lorsqu'un système externe détient la souveraineté sur les stocks (par
exemple un logiciel de gestion des marchandises), WorkDiary y reflète
chaque mouvement de stock comptabilisé localement. Cette page affiche les
cas où cette réplication a définitivement échoué — c'est ici que se fait
la reprise métier.

**Transfert avec idempotence :** Chaque mouvement génère au plus un ordre
de livraison dans une file d'attente persistante. Si la même opération
est déclenchée plusieurs fois, il n'en résulte malgré tout qu'un seul
transfert — les doubles écritures dans le système externe sont ainsi
exclues. Les erreurs temporaires sont retentées automatiquement.

**Quand un conflit apparaît :** Si la livraison d'un mouvement échoue
définitivement — par exemple parce que le système externe la refuse —, un
conflit est créé. L'écriture locale subsiste, mais le stock externe
diverge. Chaque conflit apparaît ici avec la référence au mouvement
sous-jacent et attend une décision délibérée.

**Résolution :** Deux voies existent par conflit. *Conserver localement*
accepte expressément l'écart et clôt le conflit sans écriture
supplémentaire — pertinent lorsque l'état local est correct sur le plan
métier. *Compenser* neutralise le mouvement local par une contre-écriture
d'un montant identique dans le même stock. Rien n'est jamais supprimé
après coup ni annulé techniquement ; le journal de stock reste sans
lacune et chaque décision est consignée avec la personne et le moment.

**Droits & filtres :** Le droit de lecture des stocks suffit pour
consulter ; la résolution exige en plus le droit d'écriture comptable,
car la compensation est une véritable écriture de stock. La liste peut
être filtrée sur les conflits ouverts ou sur l'ensemble des conflits.

Les conflits ouverts doivent être examinés rapidement : tant qu'ils
subsistent, le stock local et le stock externe divergent — avec des
conséquences sur les disponibilités, les propositions de commande et la
valorisation.
