---
title: "Gestion de projet agile"
topic: agile.overview
version: 1
audience: []
modules:
    - module.agile_projects
related:
    - projects.manage
    - work.overview
---

Un tableau agile peut être activé pour chaque projet — au choix pour un
travail en continu (Kanban) ou pour des sprints (Scrum). Principe de base :
la tâche reste maîtresse. Chaque carte du tableau est une tâche ordinaire
avec ses responsables, ses imputations de temps et ses liens ; le module
agile ajoute le rang, les story points et l'affectation au sprint, mais ne
remplace rien. Les story points ne sont volontairement jamais convertis en
temps ou en argent — le temps imputé et les points figurent côte à côte,
séparément, sur la carte.

**Backlog produit :** Par projet, une liste ordonnée de tous les éléments de
travail. Les nouveaux éléments naissent directement dans le backlog ou par
reprise de tâches existantes. Chaque élément porte un type (p. ex. story ou
bug), des story points, des critères d'acceptation et, si besoin, un
marquage « bloqué ». L'ordre s'entretient par déplacement vers le haut ou le
bas ; des filtres par type, blocages et terme de recherche gardent même les
grands backlogs lisibles.

**Tableau :** Les colonnes représentent le flux de travail ; les cartes se
déplacent de gauche à droite. Avec un sprint sélectionné, le tableau
n'affiche que les éléments de ce sprint.

**Sprints :** Planification par affectation d'éléments du backlog, puis
démarrage, clôture ou interruption. À la clôture, chaque élément non terminé
exige une décision explicite — retour au backlog ou passage au sprint
suivant. Rien ne se déplace en silence.

**Protection contre les conflits :** Les changements de rang, les paramètres
du tableau et les actions de sprint sont protégés contre l'édition
parallèle : le premier enregistrement gagne, la deuxième personne reçoit un
avertissement et repart de l'état actuel. Il n'existe pas d'écrasement
silencieux.

**Historique :** Chaque modification pertinente — rang, colonne, affectation
au sprint, points — est consignée comme événement agile et reste traçable
dans l'historique.

**Rapports :** Le burndown montre, pour un sprint en cours, la série
quotidienne des points restants ; la vélocité, les points terminés par
sprint clôturé d'un tableau. Une vue de synthèse pour le management
rassemble tous les tableaux : sprint actif, médiane de vélocité, travail non
planifié, blocages et une prévision empirique — cette dernière n'apparaît
que lorsque suffisamment de semaines comparables sont disponibles, plutôt
que de simuler une fausse précision. La synthèse n'affiche que les projets
que la personne qui la consulte est de toute façon autorisée à voir.
