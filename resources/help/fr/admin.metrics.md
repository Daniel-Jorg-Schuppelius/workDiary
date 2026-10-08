---
title: "Métriques"
topic: admin.metrics
version: 3
keywords:
    - indicateurs
    - statistiques
    - monitoring
    - utilisation du système
    - espace de stockage
    - utilisateurs actifs
    - tâches échouées
    - statistiques usage
    - performances
    - métriques d'exploitation
audience:
    - admin
related:
    - admin.diagnostics
    - admin.handbook
    - admin.backups
---

La page **Métriques d'exploitation** affiche, en lecture seule, des
indicateurs d'exploitation et de performance permettant de surveiller
le système. Elle complète le diagnostic, qui fournit le statut en feux
tricolores des contrôles de santé (health checks). Toutes les métriques
sont collectées et stockées exclusivement en local ; aucun envoi vers
des systèmes externes n'a lieu.

La page se compose des sections suivantes :

- **Version** de l'application (dans l'en-tête de la page)
- **File d'attente** : **Tâches en attente** et **Tâches échouées**
- **Heartbeats de sauvegarde** : sauvegardes signalées les plus
  récentes (horodatage, taille, source)
- **Erreurs de plugin (7 jours)** : nombre et derniers incidents
- **Stockage** : nombre et taille des **Pièces jointes** et des
  **Versions de documents** selon les métadonnées de la base de données
  (l'occupation disque est affichée par le diagnostic)
- **Utilisateurs actifs (30 jours)** : utilisateurs distincts ayant
  une connexion selon le journal d'audit
- **Enregistrements par module principal** : volumes par exemple pour
  **Missions (journal)**, **Documents**, **Protocoles** et **Articles
  de connaissance**
- **Utilisation des fonctionnalités (30 jours)** : **Nombre** et
  **Dernière utilisation** par fonctionnalité, agrégés par organisation
  et par jour
- **Transparence des métriques** : quels compteurs d'utilisation sont
  collectés et s'ils sont actuellement actifs

Les valeurs sont relevées à nouveau à chaque affichage ; en cas
d'indisponibilité, certaines sections se rabattent sur des valeurs par
défaut vides, sans bloquer la page.

L'accès requiert le droit **Voir les métriques d'exploitation**. Vous
trouverez les contrôles de santé détaillés et l'e-mail de test sous
**Diagnostic**.
