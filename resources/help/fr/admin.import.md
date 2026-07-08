---
title: "Import CSV"
topic: admin.import
version: 1
audience:
    - admin
related:
    - admin.handbook
    - admin.tenants
---

L'assistant d'import charge des données de base au format CSV dans
WorkDiary, avec analyse avant écriture et rapport d'erreurs complet.
Déroulement : choisir l'entité (clients, utilisateurs, projets…),
téléverser le CSV, laisser l'**analyse préalable (preflight)** vérifier
structure et contenu sans rien écrire, contrôler l'aperçu, puis confirmer —
l'import s'exécute en tâche de fond. Les lignes rejetées n'interrompent
pas l'exécution : elles sont réunies dans un **CSV d'erreurs** avec
justification, à corriger puis réimporter. Conseils : importer d'abord un
petit fichier de test, et respecter l'ordre — d'abord clients/équipes,
ensuite les données dépendantes comme les projets.
