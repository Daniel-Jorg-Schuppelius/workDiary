---
title: "Comptes de temps (administration)"
topic: admin.time-accounts
version: 1
audience: [admin]
related:
    - time-accounts.overview
---

Les comptes de temps supplémentaires transforment des données existantes
en comptes gérés : compteurs de services de nuit, comptes d'épargne de
repos, collecteurs de primes. L'horaire flexible et les congés restent des
comptes distincts et ne sont pas dupliqués ici.

Par compte, vous définissez l'unité (minutes, jours, nombre), des seuils
de feu tricolore facultatifs et la règle de report — cumulative ou
plafonnée à la clôture mensuelle. Les règles d'imputation définissent la
source de façon déclarative : motifs de type de salaire du moteur de
règles, présence nette, jours d'absence, un compteur par type de poste ou
des quantités de positions externes importées ; un facteur pondère
(p. ex. 1,25 pour « l'heure de nuit compte 1:1,25 »).

L'exécution quotidienne impute de façon idempotente ; le journal est
immuable — les corrections sont des contre-écritures d'annulation, les
écritures manuelles exigent un motif et sont auditées. En option, le solde
apparaît dans la réponse d'état du terminal.
