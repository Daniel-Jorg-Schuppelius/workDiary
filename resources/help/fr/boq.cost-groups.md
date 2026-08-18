---
title: "Groupes de coûts selon DIN 276"
topic: boq.cost-groups
version: 1
audience: []
related:
    - boq.overview
---

Les postes portent des **affectations de catalogue** : le groupe de coûts indique
*à quoi* l’argent est dépensé, le corps d’état *qui* l’exécute. Les deux
arrivent généralement avec le fichier du pouvoir adjudicateur — dans la
construction fédérale allemande, le StLB-Bau est obligatoire comme base du
descriptif, et il fournit le groupe de coûts avec chaque variante de texte.

**Le référentiel est fourni.** Sont livrés les groupes de coûts DIN 276 des
éditions **2018-12** (trois niveaux) et **2008-12** ainsi que les corps d’état
StLB — uniquement numéros et désignations courtes, sans texte normatif.

**Les deux éditions DIN coexistent, elles ne se remplacent pas.** « 310 »
signifie « fouille » en 2008 et « fouille, terrassement » en 2018 ; l’édition
2018 a en outre regroupé les 200, 500 et 600/700. Un projet en cours continue de
compter selon son édition.

## Affecter

**Construction → devis quantitatif → Affecter.** Le filtre **« Uniquement sans
groupe de coûts »** est le véritable mode de travail — ce qui est affecté n’a pas
besoin d’être revu. Chaque ligne indique l’**origine** :

- *du fichier* — arrivée avec l’import, remplaçable lors d’un réimport,
- *manuelle* — préservée lors d’un réimport,
- *proposition* — posée par une règle.

Un code absent du catalogue est refusé. L’analyse additionne par numéro ; un
numéro erroné passerait sinon inaperçu.

L’**affectation en masse** écrase aussi les saisies manuelles — celui qui la
déclenche le veut ainsi.

Les **postes répartis** apparaissent avec leurs quantités partielles en lignes
distinctes, chacune avec son propre champ. Dans l’analyse, l’affectation de la
quantité partielle prime sur celle du poste.

## Règles de proposition

**Construction → Règles d’affectation** consigne quelle prestation relève
habituellement de quel groupe de coûts. Deux points d’ancrage :

- **Corps d’état** — figure dans le fichier et est comparé par préfixe (« 013 »
  couvre aussi « 013.2 »). La base la plus fiable.
- **Mot-clé** dans le texte court ou long — plus faible, mais seul recours quand
  le pouvoir adjudicateur ne transmet pas de corps d’état.

Le passage des règles **ne comble que les lacunes** : les affectations existantes
restent, quelle que soit leur origine. Si plusieurs règles s’appliquent, le rang
le plus bas l’emporte.

## Analyser

**Construction → devis quantitatif → Groupes de coûts** montre les totaux par
groupe, avec bascule entre premier, deuxième et troisième niveau, un graphique et
une sortie CSV/Excel.

Trois points comptent :

1. **Les quantités partielles priment sur le poste.** Si un poste est réparti
   (300 m³ sur GC 310, 150 m³ sur GC 320), c’est la répartition qui compte.
2. **La section se transmet** aux postes sans affectation propre.
3. **« Sans affectation » figure toujours dans le tableau**, même à 0,00 €. Le
   reliquat des répartitions incomplètes y atterrit aussi. Une analyse qui tait
   le reliquat n’est pas vérifiable.

En dessous se trouve le **suivi des coûts** : montant du devis, avenants, métré
et reste. Les avenants comptent séparément du devis — l’un a été mis en
concurrence, l’autre s’y est ajouté. Un métré supérieur à la quantité prévue
donne un **reste négatif** ; il est affiché, pas lissé. Le **budget** provient de
l’estimation des coûts au niveau du projet (voir ci-dessous) ; l’**état
facturé** manque volontairement — il réside dans le système de facturation de
référence.

## Estimation des coûts et budget

Le barème allemand HOAI connaît quatre étapes — **estimation, calcul, devis
estimatif, constatation des coûts**. Elles ne se remplacent pas : leur
comparaison *est* le contrôle des coûts.

Une estimation externe arrive au format **X51** et relève du **projet**, non du
devis quantitatif isolé — un ouvrage s’estime dans son ensemble. Elle apparaît
ensuite comme colonne budget dans le suivi des coûts ; sans projet, cette
colonne reste vide, car un budget absent n’est pas un budget nul.

Seuls le **devis estimatif** (montant du devis plus avenants) et la
**constatation des coûts** (le métré) sont produits. L’estimation et le calcul
relèvent de la conception ; les ratios nécessaires ne sont pas disponibles ici.

## Changer d’édition

Le changement de norme passe par **Affecter → Changer d’édition** et affiche
d’abord un **aperçu**. Seules les correspondances univoques sont converties ; le
reste demeure. Les lacunes sont l’essentiel — elles montrent où quelqu’un doit
décider. Un code deviné serait pire que l’ancien.
