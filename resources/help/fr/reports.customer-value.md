---
title: "Valeur client"
topic: reports.customer-value
version: 2
audience: []
related:
    - reports.customer-analysis
    - reports.customer-retention
---

Le rapport de valeur client répond : **de quels clients vit l'entreprise,
où se situe le risque de concentration, quels clients A sont menacés ?**

## Comment lire ce rapport

- **Chiffre d'affaires par client (Pareto)** : colonnes décroissantes +
  ligne cumulée — plus la ligne atteint vite 80 %, plus la dépendance à
  quelques clients est forte.
- **Chiffre d'affaires par inactivité** : plus à droite = plus longtemps
  sans prestation ; les points à droite **au-dessus** de la ligne P80 sont
  les clients A menacés.
- **Clients par segment** : un clic sur une barre filtre la liste des
  clients en bas sur exactement ces clients.
- **Liste de risque** : clients à fort chiffre d'affaires sans prestation
  depuis le seuil configuré, avec l'évolution sur 12 mois (sparkline).

## R, F et M — comment naissent les scores

Chaque client actif sur la période reçoit trois **scores par quintile de
1 à 5** :

- **R (récence)** — jours depuis la dernière prestation. Plus c'est
  récent, plus le score est haut.
- **F (fréquence)** — nombre de jours d'activité sur la période.
- **M (montant)** — chiffre d'affaires sur la période (temps facturables,
  même source que le rapport de rentabilité).

Quintile signifie : les clients sont répartis en cinq groupes égaux par
indicateur. Exemple avec cinq clients par chiffre d'affaires
10 000/8 000/5 000/1 000/300 € → scores M 5/4/3/2/1. Les scores sont
**relatifs à votre propre portefeuille**, pas absolus.

## Les segments (la première règle applicable gagne)

| Segment | Règle |
| --- | --- |
| Inactifs | aucune prestation sur la période |
| Nouveaux | première prestation dans la période |
| Champions | R ≥ 4 et F ≥ 4 et M ≥ 4 |
| Menacés | R ≤ 2 avec M ≥ 4 (fort CA, long silence) |
| Inactifs | R ≤ 2 (actif tôt, puis silencieux) |
| Fidèles | F ≥ 3 |
| À développer | tous les autres clients actifs |

## HHI — concentration avec exemple chiffré

HHI = somme des parts de chiffre d'affaires **au carré** (en %). Deux
clients à 50 % chacun → 50² + 50² = **5000** (extrêmement concentré) ;
dix clients à 10 % → 10 × 10² = **1000** (non critique). Repères : moins
de 1500 non critique, 1500–2500 modéré, plus de 2500 risque élevé.

## Que faire des segments ?

- **Champions** : fidéliser — service prioritaire, pas d'expériences.
- **Menacés** : prendre contact activement, comprendre le silence.
- **À développer** : offres ciblées — le potentiel de croissance est là.
- **Nouveaux** : réussir l'onboarding, sécuriser la deuxième commande.
- **Inactifs** : décider consciemment — réactiver ou clore proprement.
- **HHI/top 5 élevé** : prioriser l'acquisition de nouveaux clients.

Chaque point de diagramme et chaque ligne de tableau mène par clic à sa
base de données (rapport clients & projets ou liste filtrée).
