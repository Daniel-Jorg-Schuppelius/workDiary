---
title: "Conflits avec les systèmes externes (stock et articles)"
topic: inventory.conflicts
version: 3
audience:
    - admin
    - geschaeftsfuehrung
    - teamleitung
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

**Conflits d'articles :** La même liste affiche les articles modifiés
localement dont l'état diffère dans le système externe connecté (par
exemple Lexware Office) — lorsque la stratégie de conflit du plugin est
« Vérification manuelle ». Pour chaque conflit, l'article, les champs
divergents et les deux valeurs sont présentés côte à côte. Trois voies :
*Conserver local* clôt le conflit ; l'état local reste et est transmis au
système externe lors de la prochaine synchronisation. *Reprendre l'état du
système externe* (par exemple « Reprendre l'état Lexoffice ») récupère
l'article à neuf depuis le système externe et écrase la modification
locale. *Rejeter* clôt le conflit sans rapprochement — les deux états
restent tels quels ; si l'article diffère encore lors de la prochaine
synchronisation, un nouveau conflit est créé.

**Droits & filtres :** L'onglet « Conflits » de la barre d'onglets du
stock indique le nombre de conflits ouverts. Pour consulter, le droit de
lecture des stocks ou le droit de lecture des articles suffit ; sans droit
sur les stocks, vous ne voyez que les conflits d'articles, sans droit sur
les articles, que les conflits de stock. La résolution dépend du type : les
conflits de stock exigent le droit d'écriture comptable, car la
compensation est une véritable écriture de stock ; les conflits d'articles
exigent le droit de gestion des articles. La liste peut être filtrée sur
les conflits ouverts ou sur l'ensemble des conflits, ainsi que par type
(stock, article).

Les conflits ouverts doivent être examinés rapidement : tant qu'ils
subsistent, le stock local et le stock externe divergent — avec des
conséquences sur les disponibilités, les propositions de commande et la
valorisation.
