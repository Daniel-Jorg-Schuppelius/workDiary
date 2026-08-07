---
title: "Analyse des fournisseurs"
topic: reports.supplier-analysis
version: 1
audience: []
related:
    - reports.customer-analysis
    - reports.customer-value
---

L'analyse des fournisseurs est le pendant achats de l'analyse clients et
répond à : **à quoi dépensons-nous de l'argent, de quels fournisseurs
dépendons-nous, où se trouvent les dettes ouvertes ?**

## Comment lire ce rapport

- **Dépenses par fournisseur (Pareto)** : barres décroissantes plus une
  ligne de pourcentage cumulé — la vitesse à laquelle la ligne atteint
  80 % révèle la dépendance envers quelques fournisseurs (risque de
  concentration à l'achat).
- **Dépenses par mois** : évolution des dépenses de l'organisation sur
  les douze derniers mois, indépendamment de la période choisie.
- **Montant ouvert par fournisseur** : justificatifs d'achat pas encore
  entièrement payés — les dettes actuelles.

## Source des données

Les dépenses proviennent du **miroir des justificatifs de la
comptabilité** (factures d'achat, avoirs d'achat et justificatifs
génériques par fournisseur). Les avoirs réduisent les dépenses. Les
brouillons et les justificatifs annulés ne comptent pas. Le rapport
fonctionne donc **sans le module d'entrepôt**.

Si le **module d'entrepôt** est actif, les **commandes** (passées dans la
période) et les **commandes ouvertes** (en cours) s'ajoutent par
fournisseur.

## Indicateurs

- **HHI (concentration)** — indice de Herfindahl-Hirschman sur les
  dépenses : sous 1500 non critique, 1500–2500 modéré, au-dessus de 2500
  élevé.
- **Part du top 5** — part des cinq fournisseurs aux dépenses les plus
  élevées ; le risque de concentration commence vers 60 %.
- **Tendance %** — dépenses de la période par rapport à la période de
  comparaison immédiatement précédente, de même durée.

Chaque ligne ouvre la **fiche du fournisseur** au clic. Le rapport
présente des données financières et n'est donc visible que pour les
utilisateurs disposant du droit de reporting.
