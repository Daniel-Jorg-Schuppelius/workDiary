---
title: "Gérer les feuilles d'heures"
topic: timesheets.manage
version: 4
keywords:
    - relevé d'heures
    - feuille de temps
    - rapport d'intervention
    - bon d'intervention
    - justificatif de prestation
    - bon de régie
    - faire signer
    - signature client
    - saisir du matériel
    - facturer les prestations
audience: []
related:
    - time-entries.start
    - protocols.sign
    - finance.transfers
---

Les feuilles d'heures regroupent les prestations d'un projet ou d'une
période pour contrôle, signature et facturation ultérieure ; les postes
peuvent contenir temps, activités et matériel. Déroulement type :
choisir le projet, saisir les postes, vérifier les totaux, mettre la
feuille à disposition pour signature et la clôturer après confirmation.
Les contenus déjà signés ne doivent jamais être modifiés en silence —
les corrections passent de façon traçable par une nouvelle version.
Avant la validation, période, client, projet, description des
prestations et quantités doivent être exacts ; le PDF n'est qu'une
sortie de l'état enregistré, pas la source de données de référence.

**Matériel depuis « Aujourd’hui » :** via « Matériel » sur « Aujourd’hui », vous saisissez des consommables avec projet, quantité et unité — même sans réseau. Ils sont ajoutés à votre feuille de temps ouverte du projet pour ce jour ; s’il n’y en a pas (ou si elle est déjà signée), une nouvelle est créée.

**Sortie de stock :** si le matériel est lié à un article géré en stock (article marqué « Stockable ») et que le module de stock est actif, la consommation comptabilise la sortie depuis l’entrepôt par défaut de l’organisation — dans l’unité saisie et valorisée selon la méthode de valorisation du stock. Si vous modifiez la quantité, seule la différence est comptabilisée ; un autre matériel remet l’article précédent en stock, et la suppression de la ligne ou de toute la feuille de temps annule la sortie. Sous la désignation, la ligne indique si et depuis quel entrepôt le stock a été comptabilisé. Si le stock est insuffisant, la saisie est refusée ; avec le droit « Autoriser les stocks négatifs », vous cochez « Autoriser le stock négatif ». Si l’organisation exige pour cela quatre yeux, la sortie attend la validation dans la boîte des validations (« Stock négatif demandé »). Sortez les articles soumis au numéro de série via l’entrepôt ; les articles soumis au lot sont prélevés dans les lots, la date limite la plus proche en premier. Sans entrepôt par défaut, la ligne indique « Non comptabilisé – aucun entrepôt par défaut » ; si le stock est tenu en lecture seule dans un autre système, WorkDiary ne comptabilise rien. Si un autre système gère le stock, chaque mouvement lui est aussi transmis. Il en va de même hors ligne : si le stock empêche la comptabilisation, la saisie apparaît comme conflit sous « Modifications hors ligne ».

**Limite de somme :** le matériel saisi sur « Aujourd’hui » va sur la feuille d’heures ouverte du jour. Si quantité × prix unitaire, avec les autres lignes, dépassait la somme enregistrable de la feuille, la saisie est refusée — en ligne comme hors ligne.
