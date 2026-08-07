---
title: "Valeur fournisseur"
topic: reports.supplier-value
version: 1
audience: []
related:
    - reports.supplier-analysis
    - reports.customer-value
---

Le rapport de valeur fournisseur est le pendant achats de la valeur client
et répond : **de quels fournisseurs dépendons-nous, où se trouve le risque
de concentration, lesquels sont stratégiques, dormants ou occasionnels ?**

## Comment lire ce rapport

- **Dépenses par fournisseur (Pareto)** : barres décroissantes plus une
  ligne de pourcentage cumulé — la vitesse à laquelle la ligne atteint
  80 % révèle la dépendance envers quelques fournisseurs.
- **Dépenses par inactivité** : plus c'est à droite, plus le dernier
  justificatif est ancien ; les points à droite **au-dessus** de la ligne
  P80 sont des fournisseurs à fortes dépenses qui n'ont rien livré depuis
  longtemps.
- **Fournisseurs par segment** : cliquer sur une barre filtre la liste des
  fournisseurs ci-dessous sur exactement ces fournisseurs.
- **Liste des risques** : fournisseurs dont la part de dépenses dépasse le
  seuil configuré (risque de concentration mono-source), avec une
  évolution des dépenses sur 12 mois (sparkline).

## R, F et M — comment naissent les scores

Chaque fournisseur actif sur la période reçoit trois **scores de quintile
de 1 à 5** :

- **R (Recency)** — jours depuis le dernier justificatif. Plus c'est
  court, plus le score est élevé.
- **F (Frequency)** — nombre de jours avec justificatif sur la période.
- **M (Monetary)** — dépenses sur la période (justificatifs d'achat du
  miroir, les avoirs les réduisent).

Quintile signifie que les fournisseurs sont répartis en cinq groupes de
taille égale par indicateur. Les scores sont donc **relatifs à votre
propre base de fournisseurs**, pas absolus.

## Segments

- **Stratégique** — R ≥ 4, F ≥ 4, M ≥ 4 (fortes dépenses, régulier,
  récent).
- **Fournisseur clé dormant** — R ≤ 2 avec M ≥ 4 (fortes dépenses mais pas
  de justificatif depuis longtemps).
- **Fournisseur régulier** — F ≥ 3 (approvisionnement régulier).
- **Occasionnel** — tous les autres fournisseurs actifs.
- **Nouveau** — le premier justificatif tombe dans la période.
- **Dormant** — aucun justificatif dans la période.

Le rapport présente des données financières et n'est visible que pour les
utilisateurs disposant du droit de reporting.
