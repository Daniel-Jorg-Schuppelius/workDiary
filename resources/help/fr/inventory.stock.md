---
title: "Stocks et scan"
topic: inventory.stock
version: 6
keywords:
    - état des stocks
    - entrée de marchandises
    - sortie de stock
    - transfert de stock
    - réservation
    - point de commande
    - stock minimum
    - bloquer un lot
    - gestion des lots
    - FEFO
    - scanner un code-barres
    - valeur du stock
audience: []
modules:
    - module.lager
related:
    - warehouses.manage
    - inventory.counts
    - inventory.labels
    - articles.master
---

La vue des stocks affiche, par emplacement, les quantités disponibles,
physiques et réservées des variantes, le prix moyen pondéré, la valeur de
stock et le seuil de réapprovisionnement. Avec le droit de mouvement, vous
saisissez des mouvements manuels (entrée, sortie, réservation, libération)
et définissez les stocks minimum et de réapprovisionnement ; les sorties
en négatif ne sont possibles que si vous les autorisez explicitement. Les
lots se gèrent dans la liste des lots (division et fusion possibles) ; la
vue de scan résout un code (numéro de série, lot, GTIN ou SKU) et
comptabilise directement une action (entrée, sortie, transfert). Tous les
mouvements sont inscrits dans le journal continu et sont irréversibles —
les corrections se font par contre-écritures.

Un lot peut être **bloqué** puis **libéré** dans la liste des lots — chaque
fois avec un motif ; les deux opérations figurent dans le journal d’audit. Le
stock d’un lot bloqué reste en entrepôt, mais la liste de préparation ne le
propose plus ; le fractionnement et la fusion ne sont possibles qu’après la
libération. Lors d’une fusion, le stock du lot source passe au lot cible par
contre-écritures (transfert sortie/entrée) ; le lot fusionné est ensuite clos
et n’accepte plus d’entrée.

**Sortie par lot.** Si l’entrepôt contient du stock en lots, le formulaire
de mouvement propose le champ « Lot (sortie) » : laissé vide, la sortie suit
FEFO (date limite la plus proche d’abord, comme sur la liste de
préparation) ; sinon, exactement le lot choisi est prélevé. Pour l’entrée,
la réservation et la libération, le champ est sans effet. Les articles
suivis par lot peuvent ainsi être prélevés tant que leurs lots couvrent la
quantité — le stock sans lot n’est pas prélevé pour eux ; l’entrée passe
toujours par la réception. Un transfert par scan emporte les lots vers
l’entrepôt cible ; un code de lot scanné comptabilise exactement ce lot.

**Blocage dans le stock.** Bloquer un lot comptabilise son stock dans
l’état « bloqué » : il reste en entrepôt, mais n’est plus compté comme
disponible et apparaît dans la vue des stocks dans la colonne « Bloqué ».
Un lot bloqué n’accepte aucune réception ; les retours et remises en stock
dans ce lot restent bloqués. La libération contre-passe le stock bloqué. La
division d’un lot est elle aussi une écriture : la quantité divisée passe
au nouveau lot dans le journal des mouvements.

**Nettoyer l’ancien stock.** Jusqu’en octobre 2026, les sorties ne
portaient pas de lot ; le stock d’un lot peut donc dépasser ce qui en reste
réellement. Votre administrateur le vérifie avec la commande
`inventory:lots:repair` : sans option, c’est une simulation avec un tableau
par lot (solde comptable, reste des couches de valorisation, écart) ; avec
`--apply`, elle reporte l’écart vers « sans lot » en valorisation FIFO ou
FEFO, le stock total restant inchangé. En prix moyen pondéré, elle signale
seulement le lot ; les lots bloqués seulement après leur libération.

## Unités, journal de stock et paramètres

**Saisie dans une unité de l’article.** Si un article possède d’autres unités avec un facteur de conversion (par exemple 1 rouleau = 100 m), le formulaire de mouvement et la vue de scan proposent le choix **Unité** à côté de la quantité. Le mouvement est toujours enregistré dans l’unité de base ; la quantité saisie et son unité restent attachées au mouvement. L’arrondi est commercial, à quatre décimales. Une quantité qui vaut zéro dans l’unité de base et une unité non définie pour l’article sont refusées.

**Journal de stock.** L’onglet **Journal de stock** liste tous les mouvements avec la date, le dépôt, le type de mouvement, la quantité en unité de base et la colonne **Saisi comme** (quantité et unité saisies). La recherche, le dépôt et la période en haut restreignent la liste.

**Durée de conservation.** Si un article porte la propriété « durée de conservation requise », le stock n’accepte son entrée que par la réception de marchandises d’une commande, avec lot et date limite de consommation. Les entrées manuelles et par scan sont refusées. Un lot déjà connu apporte sa date.

**Paramètres du stock.** Avec le droit de configuration du stock, vous ouvrez les **paramètres du stock** dans l’aperçu des stocks. Vous y choisissez la **méthode de valorisation** de l’organisation (coût moyen pondéré, FIFO ou FEFO) et le **moment de la réservation** en fabrication (à la libération ou au démarrage). Lors du passage à FIFO ou FEFO, le stock existant reçoit une couche initiale au prix moyen précédent. Le retour au coût moyen pondéré n’est possible que lorsqu’il ne reste plus de stock en couches, car la moyenne n’a pas été tenue à jour pendant cette période.

## Droits, réservations et numéros d’article

**Droits séparés.** Enregistrer des mouvements, compter l’inventaire, valider les écarts d’inventaire, modifier les réservations et compenser les écritures externes sont des droits distincts. Qui pouvait enregistrer conserve le comptage, la réservation et la compensation ; votre administrateur peut séparer les droits par rôle.

**Modifier une réservation.** Avec le droit sur les réservations, l’icône crayon d’une réservation active ouvre une fenêtre indiquant la quantité réservée, prélevée et ouverte. Vous y fixez la priorité (un nombre plus petit signifie une priorité plus élevée) et libérez une quantité partielle, par exemple pour l’attribuer à un autre ordre.

**Validation à quatre yeux.** Dans les paramètres du stock, vous pouvez activer la validation à quatre yeux des écarts d’inventaire, avec un seuil pour la valeur de stock de l’écart par ligne. À partir du seuil, la personne qui a compté ne valide pas. Le seuil et le valideur figurent dans le journal d’audit.

**SKU et GTIN.** Les paramètres du stock indiquent qui gère le numéro d’article, le SKU des variantes et le GTIN. Le choix se fait sur la page « Plages de numéros », plage « Article ». Si une gestion commerciale gère les numéros, les champs sont verrouillés dans la fiche article et arrivent avec la synchronisation.

**Stock négatif à quatre yeux :** si l’organisation exige, dans les paramètres de stock, une validation à quatre yeux pour les sorties en stock négatif, une sortie avec « Autoriser le stock négatif » devient une demande dans la boîte des validations. Elle n’est comptabilisée qu’après l’accord d’une autre personne disposant du droit « Autoriser les stocks négatifs » ; demandeur et valideur figurent dans le journal d’audit.

**Redistribuer des réservations :** dans la boîte de dialogue « Modifier la réservation », vous cédez une quantité ouverte à une autre réservation du même article, entrepôt et propriété, par exemple à un ordre plus prioritaire. Une réservation plus récente n’en évince jamais une plus ancienne en silence ; la redistribution figure dans le journal d’audit et la ligne de matière d’un ordre de fabrication la suit.

**Valeur du stock selon la méthode :** Le prix moyen et la valeur du stock de
la vue suivent la méthode de valorisation de l’article : pour le prix moyen
pondéré à partir du registre des moyennes, pour FIFO et FEFO à partir des
couches de valorisation ouvertes (valeur des quantités restantes, moyenne =
valeur ÷ quantité). La classification ABC de l’inventaire tournant utilise les
mêmes valeurs.
