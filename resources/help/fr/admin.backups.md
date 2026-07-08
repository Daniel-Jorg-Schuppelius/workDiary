---
title: "Sauvegardes & supervision"
topic: admin.backups
version: 1
audience:
    - admin
related:
    - admin.handbook
    - admin.security
---

WorkDiary surveille les sauvegardes externes via un **heartbeat** : votre
tâche de sauvegarde signale la réussite de chaque exécution à la plateforme
(`POST /admin/backup/heartbeat`, jeton Bearer) avec notamment le **hachage
du manifeste (SHA-256)** et la **taille** ; chaque réception est journalisée
dans l'audit. Il n'existe pas encore d'interface de supervision : mettez en
place une alerte externe en cas d'absence de heartbeat. La commande
`php artisan system:health` vérifie base de données, migrations, stockage,
file d'attente, APP_KEY, mail et licence sans modifier de données. Pour la
restauration, sauvegardez impérativement l'**APP_KEY** en plus de la base —
sans lui, les champs chiffrés sont perdus — et testez régulièrement vos
restaurations sur un environnement séparé.
