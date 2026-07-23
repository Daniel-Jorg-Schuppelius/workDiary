---
title: "Sauvegardes & supervision"
topic: admin.backups
version: 2
audience:
    - admin
related:
    - admin.handbook
    - admin.security
---

WorkDiary surveille les sauvegardes externes via un **heartbeat** : votre
tâche de sauvegarde signale la réussite de chaque exécution à la plateforme
(`POST /admin/backup/heartbeat`, jeton Bearer défini par la variable
d'environnement `BACKUP_HEARTBEAT_TOKEN` — sans jeton, l'endpoint est
désactivé) avec `manifest_sha256`, `size_bytes`, `source` et `occurred_at` ;
chaque réception est journalisée dans l'audit. Les sauvegardes ne
s'enregistrent donc pas manuellement dans l'interface : dès le premier
heartbeat, la source apparaît sur la page **Backup & Restore**, qui affiche
la dernière sauvegarde par source et la marque en retard au-delà de la
fraîcheur configurée (`BACKUP_HEARTBEAT_FRESHNESS_HOURS`, 26 h par défaut).
Les tests de restauration s'y consignent via **Consigner un test de
restauration** ; la restauration elle-même s'effectue volontairement en
dehors de WorkDiary. La commande `php artisan system:health` vérifie base
de données, migrations, stockage, file d'attente, APP_KEY, mail et licence
sans modifier de données. Pour la restauration, sauvegardez impérativement
l'**APP_KEY** en plus de la base — sans lui, les champs chiffrés sont
perdus — et testez régulièrement vos restaurations sur un environnement
séparé.
