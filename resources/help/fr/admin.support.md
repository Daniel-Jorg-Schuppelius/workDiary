---
title: "Rapport de support et diagnostic"
topic: admin.support
version: 1
audience:
    - admin
related:
    - admin.security
    - admin.backups
    - admin.handbook
---

Le **rapport de support** regroupe l'état technique de votre
installation pour l'analyse par le support — **sans qu'aucune donnée
client ne quitte la maison**. Il contient versions et build, statut de
santé, erreurs de plugins des 7 derniers jours (identifiants et
compteurs uniquement), état d'exploitation, comptages de données par
table (jamais de contenus) et drapeaux de configuration ; les secrets
sont systématiquement expurgés (liste blanche stricte). Générez-le via
la page d'administration **« Rapport de support »** (bundle ZIP,
fichier JSON ou aperçu) ou en ligne de commande avec
`php artisan support:report`. Chaque génération est tracée dans le
journal d'audit.
