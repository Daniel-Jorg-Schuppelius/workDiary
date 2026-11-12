---
title: "Analyse clients"
topic: reports.customer-analysis
version: 2
keywords:
    - statistiques clients
    - rapport client
    - interventions par client
    - temps par client
    - taux de reprise
    - temps non facturable
    - risque de marge
    - KPI clients
    - analyse de la clientèle
    - clients et projets
    - heures par projet
    - revenu par client
audience: []
modules:
    - module.auswertungen_team
related:
    - reports.drilldown
    - reports.entry-type-analysis
    - reports.billing
---

L'**analyse client** affiche par client l'effort, les reprises et le
temps non facturable sur la période de l'en-tête de page ; elle exige la
permission « Voir les rapports ». **Clients et projets** ventile les
heures et le revenu par client et par projet.

## Analyse client

Colonnes du tableau :

- **Commandes** de la période, **Total** (minutes saisies),
  **Facturable**, **Non facturable** et leur **Part %** – une part élevée
  est un indicateur précoce de risque sur la marge.
- **Reprise** : commandes avec un protocole de défaut sur la période.
- **Points ouverts** sur le client, ses commandes et ses projets, dont
  **Escaladé** (bloqués).
- **Ø min/commande** et **Tendance 30 j** : commandes des 30 derniers
  jours moins celles des 30 jours précédents.

Filtres : **Projet**, **Employé** et **Type de commande**, ainsi que
**Effort minimal (minutes)**, **Masquer les clients sans valeurs** et
**Inclure les clients masqués** (clients marqués « Masquer dans les
analyses »).

Cibles des clics : le nom du client ouvre la fiche client, **Commandes**
et **Total** ouvrent le journal de la période. **Reprise**, **Points
ouverts** et **Escaladé** ouvrent le drill-down avec les mêmes filtres.
Au-dessus figurent **Heures par client (top 20)** – un clic ouvre
Clients et projets pour ce client –, l'évolution des entrées de
commandes, **Points ouverts par client (top 15)** ainsi que les listes
**Top 5 effort**, **Top 5 reprise** et **Top 5 non facturable**. Export
en PDF, CSV ou Excel.

## Clients et projets

Le tableau **Client / projet** indique le total par client et, en
dessous, les projets avec **Heures** et **Revenu**, les projets triés par
heures décroissantes ; **Σ h** et **Σ €** figurent au-dessus. Le revenu
est la somme des montants enregistrés des saisies de temps. Les
graphiques montrent **Heures par client (top 20)** – un clic filtre la
page sur ce client – et **Projets principaux par heures**.

Filtres : **Client**, **Projet** et **Inclure les clients masqués**.
Qui peut voir toutes les saisies de temps choisit sous **Zone** entre
**Seulement les miens** et **Toute l'équipe** et filtre par **Employé** ;
les autres voient leurs propres temps. Export en PDF, CSV ou Excel.
