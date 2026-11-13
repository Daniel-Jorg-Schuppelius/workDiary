---
title: "Analyse par type d'intervention"
topic: reports.entry-type-analysis
version: 5
keywords:
    - prévu vs réalisé
    - écart prévu réalisé
    - durée de traitement
    - durée moyenne
    - taux de reprise
    - dépassement de temps
    - comparer les types de mission
    - causes principales
    - analyse des écarts
    - estimation des durées
audience: []
modules:
    - module.auswertungen_team
related:
    - reports.customer-analysis
    - reports.drilldown
---

L'analyse par type d'intervention compare les durées prévues et réelles
par type d'entrée (p. ex. maintenance, panne, migration) : **durée
moyenne de traitement** et dispersion, **écart prévu/réel** en
pourcentage, **taux de reprise** et **causes principales** si elles sont
tenues comme classification. Elle révèle les types d'intervention où les
hypothèses de planification sont irréalistes ou où l'équipe a un
surcoût systématique — deux signaux de besoin de formation ou de
révision tarifaire.

La durée prévue d'un ordre est sa **Durée prévue (HH:MM)** ; si elle est vide,
la durée d’intervention d’une commande planifiée, sinon la durée du créneau ou du rendez-vous compte.

La colonne **Principales causes** indique, par type de commande, les trois classifications les plus fréquentes issues du motif de reprise des temps saisis et de la cause racine des réclamations liées à la commande. Chaque commande compte une fois ; un clic affiche les temps et réclamations concernés. L’export CSV contient aussi cette colonne.

## Délai de traitement par type de commande

La section **Délai de traitement par type de commande** affiche, pour les
commandes clôturées sur la période, la médiane des jours entre la création et
la clôture, ainsi que le nombre de clôtures. Les commandes annulées ne
comptent pas. Le filtre de personne ne s’applique volontairement pas ici : la
mesure porte sur la commande, jamais sur la personne. La vue de direction
affiche le même chiffre pour les types de commande les plus fréquents des
douze derniers mois et renvoie ici.
