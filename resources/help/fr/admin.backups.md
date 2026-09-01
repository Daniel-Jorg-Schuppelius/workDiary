---
title: "Sauvegardes & surveillance"
topic: admin.backups
version: 3
audience:
    - admin
schema: process
related:
    - admin.handbook
    - admin.security
    - admin.import
---

## Objectif et contexte

WorkDiary surveille les sauvegardes externes par **battement de
cœur** : le job de sauvegarde signale son succès à la plateforme
après chaque exécution. Les sauvegardes ne s'enregistrent pas à la
main — au premier battement, la source apparaît automatiquement sur
**Sauvegarde & restauration**. La sauvegarde et la restauration
elles-mêmes se font volontairement hors de WorkDiary.

## Prérequis

- Un job de sauvegarde externe (p. ex. le `backup.sh` fourni).
- Le jeton dans la variable d'environnement `BACKUP_HEARTBEAT_TOKEN` —
  sans jeton, le point d'accès est désactivé.
- Droits d'administration pour la page **Sauvegarde & restauration**.

## Déroulement recommandé

1. Configurer le job et lui faire envoyer le battement :
   `POST /admin/backup/heartbeat` avec jeton Bearer (hors du circuit
   de connexion, limité en débit) ; sont transmis `manifest_sha256`,
   `size_bytes`, `source` et `occurred_at`.
2. Vérifier la source sur **Sauvegarde & restauration** : la page
   montre la dernière sauvegarde par source et la marque **en
   retard** si le dernier battement dépasse la fraîcheur configurée
   (`BACKUP_HEARTBEAT_FRESHNESS_HOURS`, 26 h par défaut).
3. Tester régulièrement les restaurations sur un environnement séparé
   et les consigner via **Consigner un test de restauration**.
4. Contrôler l'état du système : `php artisan system:health` teste
   base de données, migrations, stockage, file d'attente, APP_KEY,
   courriel et licence (code de sortie 0/1, ne modifie rien — idéal
   pour cron/CI).

## Exemple pratique

Le cron nocturne sauvegarde à 23 h et envoie le battement. Quand un
chantier de stockage arrête le job deux nuits, la source passe « en
retard » — l'admin le voit au matin sur le tableau de bord, avant
toute perte de données.

## Erreurs fréquentes

- **Ne sauvegarder que la base :** sans l'**APP_KEY**, les champs
  chiffrés (PII, 2FA, dossiers de protection des données) sont
  irrémédiablement perdus.
- **Ne jamais tester les restaurations :** une sauvegarde sans
  restauration vérifiée est un espoir, pas un concept.
- **Confondre battement et sauvegarde :** le battement ne signale que
  le succès — il ne remplace ni sauvegarde ni rétention.

## Effets et prochaines étapes

Chaque battement est enregistré et journalisé comme événement d'audit
`backup.heartbeatReceived` ; les retards remontent dans la
surveillance. Ensuite : planifier un test de restauration, mettre
`system:health` au cron et lire le manuel d'administration sur la
reprise après sinistre.
