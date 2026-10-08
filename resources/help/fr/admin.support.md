---
title: "Rapport de support et diagnostic"
topic: admin.support
version: 2
keywords:
    - rapport technique
    - informations système
    - numéro de version
    - état de santé
    - paquet de support
    - dépannage
    - signaler un problème
    - infos de débogage
    - état du système
audience:
    - admin
    - geschaeftsfuehrung
    - support
related:
    - admin.security
    - admin.backups
    - admin.handbook
---

Le **Rapport support** rassemble l'état technique de votre
installation afin que le support puisse analyser un problème — **sans
que des données clients ne quittent l'entreprise**.

Voici comment le rapport est structuré :

- **Versions & build** : version de l'application, hash du build,
  versions de PHP, de Laravel et de la base de données, ainsi que les
  modules et plugins actifs.
- **État de santé** : le résultat de `php artisan system:health` (base
  de données, migrations, stockage, file d'attente, APP_KEY, e-mail,
  licence, sauvegarde) sous forme de bloc de statut compact.
- **Erreurs de plugin (7 jours)** : uniquement l'ID du plugin, la phase
  et le nombre — aucun texte d'erreur, aucune charge utile (payload).
- **Exploitation** : état de la file d'attente et derniers heartbeats
  de sauvegarde (uniquement des comptages et des métadonnées comme la
  taille et l'horodatage).
- **Comptages des données de base** : nombre d'enregistrements par
  table — jamais de contenus.
- **Indicateurs de configuration** : quels modules/fonctionnalités sont
  actifs, type de transport des e-mails, pilote de file d'attente. Les
  secrets (APP_KEY, mots de passe, jetons) sont systématiquement
  masqués.

**La minimisation des données est la promesse centrale.** Le rapport
contient exclusivement des champs techniques explicitement autorisés
(liste blanche). Les noms de clients, les données personnelles, les
identifiants d'accès en clair et les secrets n'y figurent jamais.

Voici comment générer le rapport :

- **Page d'administration** « Rapport support » : archive ZIP
  (optionnellement protégée par mot de passe), simple fichier JSON ou
  aperçu dans le navigateur.
- **Ligne de commande** (on-premise/CI) : `php artisan support:report`
  affiche le rapport sur STDOUT, `--output=chemin.json` l'écrit dans un
  fichier.

Chaque génération est consignée dans le journal d'audit
(`support.reportGenerated`, `support.reportDownloaded`).
