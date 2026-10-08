---
title: "Stocks et scan"
topic: inventory.stock
version: 2
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
