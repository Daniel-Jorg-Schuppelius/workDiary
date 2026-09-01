---
title: "Élimination et justificatifs"
topic: disposal.overview
version: 1
audience: []
modules:
    - module.entsorgung
related:
    - assets.fleet
    - customer-portal.overview
---

Le dossier d'élimination gère la mise au rebut des anciens appareils comme
un processus traçable : enlèvement chez le client, liste des appareils
avec codes déchets (AVV/CED), traitement des supports de données selon la
norme DIN 66399, remise à l'entreprise d'élimination certifiée avec
justificatifs, et clôture avec justificatif client dans le portail.

**Chaîne de statuts :** Créé → Enlevé → En traitement → Remis à
l'éliminateur → Clôturé. L'étape de traitement peut être sautée si aucun
support de données n'est concerné. L'annulation est possible jusqu'à la
clôture ; elle est définitive et consignée avec motif dans la chaîne de
traçabilité.

**Liste des appareils :** Chaque position porte la catégorie, le
fabricant/modèle, le numéro de série, la quantité, le poids et le code
déchet (AVV/CED). La classification « dangereux » est déduite
automatiquement de l'astérisque du code déchet — elle n'est jamais saisie
à la main. Les positions ne sont modifiables que jusqu'à la remise à
l'éliminateur.

**Traitement des supports de données :** Pour chaque appareil contenant
des supports de données, le traitement est documenté — type de support,
procédé (p. ex. effacement logiciel, démagnétisation, broyage ou démontage
pour destruction), catégorie de matériau DIN 66399 avec niveau de
sécurité, ainsi que l'exécutant et une référence de justificatif. La
catégorie de matériau est préremplie selon le type de support.

**Remise à l'éliminateur :** Les remises à l'entreprise d'élimination
certifiée sont saisies avec type de justificatif (p. ex. bon de prise en
charge, bordereau de suivi, justificatif d'élimination), numéro de pièce,
date de remise et référence du certificat EfbV. Une pièce téléversée est
archivée comme document GED.

**Clôture :** Le contrôle de clôture du dossier exige quatre conditions —
au moins une position d'appareil, la signature de prise en charge du
client, un traitement documenté pour chaque appareil porteur de données
et, pour les déchets dangereux, un justificatif de l'éliminateur. À la
clôture, le justificatif client est généré en PDF, publié dans le portail
client, et les actifs liés sont mis au rebut. La clôture et l'annulation
nécessitent le droit « Clôturer et annuler les dossiers d'élimination ».

**Rapport :** Le rapport d'élimination évalue les dossiers clôturés sur la
période choisie — quantités éliminées par client, par mois et par code
déchet (AVV/CED), avec à chaque fois la part dangereuse.
