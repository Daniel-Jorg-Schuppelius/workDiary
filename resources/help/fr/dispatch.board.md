---
title: "Centre de contrôle : tableau et carte"
topic: dispatch.board
version: 3
keywords:
    - tableau de planification
    - planning des interventions
    - kanban
    - vue carte
    - carte des interventions
    - vue par technicien
    - risque SLA
    - répartiteur
    - dispatcheur
    - poste de pilotage
audience: []
modules:
    - module.planung
related:
    - dispatch.overview
    - tours.manage
    - sla.overview
---

Le **Centre de contrôle** affiche en un coup d'œil les commandes ouvertes et
planifiées d'une période — sous forme de **Tableau** (colonnes) ou de
**Carte**. Il s'agit d'une simple vue d'ensemble : toutes les modifications
continuent de se faire dans la commande concernée.

## Tableau

Le tableau regroupe les commandes de la période choisie, au choix :

- **Par statut** : colonnes selon le statut de planification (Non planifié,
  Planifié, Confirmé, En route, Terminé).
- **Par salarié** : une ligne par salarié affecté.

Chaque fiche indique le client, la plage horaire et le salarié, et signale
les situations particulières :

- **Conflit** : l'affectation actuelle présente un **conflit de
  planification bloquant** (par ex. double planification, chevauchement
  de postes).
- **SLA** : pour ce client, un ticket de service est **menacé** ou
  **violé** (risque SLA).

Un clic sur une fiche ouvre la commande.

## Carte

La carte situe les commandes d'après leur propre emplacement ou — si aucun
n'est renseigné — d'après l'**emplacement du client**. La couleur du
marqueur suit le statut de planification ; les commandes dont le **SLA est
menacé ou violé** sont mises en évidence en **rouge**. Les filtres
permettent d'afficher de manière ciblée **uniquement les risques SLA** ou
**uniquement les commandes non confirmées**.

## Délibérément non inclus

Le centre de contrôle est une pure visualisation. L'**optimisation des
tournées**, le **suivi en temps réel** et la **surveillance permanente de
la localisation** ne font pas partie de cette vue pour des raisons de
protection des données.
