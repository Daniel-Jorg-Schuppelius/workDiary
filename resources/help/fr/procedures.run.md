---
title: "Exécuter une procédure"
topic: procedures.run
version: 2
keywords:
    - remplir une check-list
    - suivre une instruction
    - démarrer une procédure
    - étapes obligatoires
    - signaler un écart
    - contre-signature
    - double contrôle
    - preuve de sauvegarde
    - valider une étape
audience: []
related:
    - protocols.create
---

Une procédure (instruction de travail avec étapes obligatoires) se lance
sur une **intervention** ou un **actif**. Les **étapes obligatoires**
doivent être traitées dans l'ordre défini ; les **étapes de sauvegarde**
exigent une preuve (hash + taille ou lien de stockage externe) et les
**étapes à double contrôle** la confirmation d'une **seconde personne**
avec le rôle adéquat. Les **écarts** doivent être justifiés avec action
de suivi — impossible de sauter une étape sans motif. À la clôture, le
numéro de version du modèle est figé ; les modifications ultérieures du
modèle n'ont **pas** d'effet rétroactif.

## Étapes à double contrôle

La barre **Validation à quatre yeux** de l'étape indique l'état : pas
encore demandée, demandée, prise en charge ou contresignée.

1. La personne qui exécute l'étape clique sur **Demander une seconde
   personne**. Les responsables d'équipe reçoivent une notification avec
   un lien vers le déroulement.
2. Une autre personne autorisée ouvre le déroulement et clique sur
   **Contresigner en tant que seconde personne**. La personne qui a fait
   la demande ne peut pas contresigner elle-même.
3. Ce n'est qu'ensuite que l'étape peut être terminée.

Une contresignature peut être **révoquée** avec un motif (droit
« Révoquer l'approbation à quatre yeux », en général responsables ou
administration). L'étape repasse à « ouverte », la demande reste active
et une autre personne peut contresigner. Demande, contresignature et
révocation sont consignées avec la personne et l'heure dans l'historique.
