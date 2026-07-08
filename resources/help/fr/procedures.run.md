---
title: "Exécuter une procédure"
topic: procedures.run
version: 1
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
