---
title: "Entrée de documents cloud"
topic: cloud-intake.overview
version: 1
audience: []
related:
    - documents.manage
    - admin.integrations
---

WorkDiary LIT les documents des dossiers surveillés dans Dropbox, OneDrive/SharePoint et Google Drive et les route vers les factures entrantes ou la GED via des règles de dossiers.

**Connexions :** un compte par fournisseur est connecté et confirmé via OAuth ; ensuite le conteneur (drive/bibliothèque) et le dossier racine sont choisis. L’import ne démarre qu’avec au moins une règle valide.

**Règles :** des motifs de chemin avec * et ** plus des variables comme {customer_number} associent les fichiers aux clients, projets, commandes, actifs ou contrats existants — jamais par création automatique. Les cas ambigus vont dans la boîte de réception d’intégration.

**Sécurité :** scopes en lecture seule, jetons chiffrés, fichiers sources intacts ; les webhooks ne sont que des signaux de réveil — l’exécution delta reprise fait foi.
