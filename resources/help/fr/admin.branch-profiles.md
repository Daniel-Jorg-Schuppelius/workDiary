---
title: "Profils de branche"
topic: admin.branch-profiles
version: 1
audience:
    - admin
related:
    - admin.handbook
    - admin.import
---

Les profils de branche installent en une étape un paquet de modèles
adaptés à un corps de métier : types de commande, catégories, règles
obligatoires, listes de contrôle, exigences de locaux et étiquettes
standard. Cherchez le métier voulu dans le catalogue, consultez l'**aperçu
du contenu** sur la carte, puis choisissez **Installer** et confirmez.
L'installation est **idempotente** : une réinstallation ne crée pas de
doublons et n'écrase pas les données adaptées localement ; **Réappliquer**
remet les modèles importés à l'état du profil, sans jamais toucher aux
listes de contrôle déjà publiées. Chaque installation est journalisée de
manière infalsifiable et de nouveaux métiers peuvent être ajoutés sans
modification du code.
