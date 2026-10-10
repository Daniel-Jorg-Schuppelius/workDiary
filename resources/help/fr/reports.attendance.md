---
title: "Présence, prévu/réel et couverture"
topic: reports.attendance
version: 7
keywords:
    - rapport de présence
    - analyser les pointages
    - comparaison prévu réel
    - effectif prévu et réel
    - sous-effectif
    - couverture des services
    - effectif minimum
    - retard
    - plage fixe
    - rapport mensuel d'équipe
    - heures par employé
    - jours-personnes
audience:
    - admin
    - geschaeftsfuehrung
    - personalverwaltung
    - teamleitung
related:
    - reports.overview
    - reports.my-reports
    - attendance.manage
    - planning.shifts
    - reports.utilization
    - reports.presence-emergency
---

Ces rapports comparent qui était présent et quand avec ce qui était prévu : les
pointages avec le modèle de temps de travail, les services avec l'effectif
cible et le temps prévu des ordres avec le temps comptabilisé. Il s'agit de
**Présence** et **Plan/réel** dans la zone de menu **Rapports** →
**Personnel**, ainsi que de **Couverture** et **Mois par employé** sous
**Rapports** → **Équipe**. Toutes les pages n'affichent que les données de
l'organisation active. Les corrections se font sur le pointage, la saisie de
temps, le modèle de temps de travail ou dans le planning des services ; le
rapport n'est pas une source de données à part.

## Présence

**Rapports** → **Personnel** → **Présence** ouvre le **Rapport de présence**
pour la période choisie dans l'en-tête (icône de calendrier **Choisir la
période**).

Le tableau comporte une ligne par personne et les colonnes :

- **Jours ouvrés** et **Cible** : jours et temps cible selon le modèle de temps
  de travail par jour de semaine. Les jours fériés et les jours d'absence
  approuvée – par exemple congés, congé spécial, congé non payé ou arrêt maladie – n'ont pas de
  cible et ne comptent pas comme jours ouvrés, exactement comme dans le **Bilan
  de travail** et le compte de temps de travail. Sans modèle de temps de
  travail, les deux valeurs sont à 0.
- **Présent** : pointages terminés après déduction des pauses ; les pointages
  en cours et annulés ne comptent pas.
- **Comptabilisé** : toutes les saisies de temps de la période, quel que soit
  leur type.
- **Solde** : présent moins cible, en rouge si négatif, en vert si positif.

La ligne **Total** et les tuiles **Cible**, **Présent**, **Comptabilisé** et
**Solde** regroupent toutes les personnes affichées. La carte de chaleur
**Présence par collaborateur et jour de semaine** montre quels jours de la
semaine une personne était présente et combien de temps ; **Présence dans le
temps** montre le total par jour, ou par semaine calendaire pour les périodes
de plus de 62 jours. Un clic sur un en-tête de colonne trie le tableau.

Les filtres **Zone** avec **Uniquement les miens** ou **Toute l’équipe**
(toutes les personnes de l'organisation) ainsi que **Employé** et **Équipe**
sont visibles pour les administrateurs et les personnes disposant du droit
**Voir les présences**. Toutes les autres personnes ne voient que leur propre
ligne.

Export : **PDF** avec le tableau et la carte de chaleur ; dans le menu
**Export**, **CSV** et **Excel** avec les jours ouvrés, la cible, le temps
présent, le temps comptabilisé et le solde en minutes par personne, plus une
ligne de total.

## Plan/réel

**Rapports** → **Personnel** → **Plan/réel** compare le prévu et le réel dans
plusieurs vues, que vous changez avec les onglets en haut de la page :
**Présence**, **Équipe**, **Organisation**, **Services**, **Projets** et
**Sites**. Vous ne voyez que les onglets pour lesquels vous disposez des droits.

Ici, la période n'est pas reprise de l'en-tête : réglez-la dans la barre de
filtres avec **De** et **Jusqu’à**. Sans indication, le mois en cours
s'applique ; la période est conservée quand vous changez d'onglet. Ces pages ne
proposent pas d'export.

### Onglet Présence

La page **Présence planifiée/réelle** montre vos propres jours avec les tuiles
**Plan**, **Réel**, **Δ** et **Avertissements** et, par jour, les colonnes :

- **Plan** : temps cible selon le modèle de temps de travail pour le jour de
  semaine ; « — » les jours sans modèle de temps de travail ou sans jour de
  travail, ainsi que les jours fériés et les jours d'absence approuvée comme
  les congés, le congé spécial, le congé non payé ou l’arrêt maladie.
- **Réel** : la présence pointée du jour ; les pointages annulés ne comptent
  pas.
- **Δ** : réel moins plan, valeurs négatives en rouge.
- **Début P/R** : début de la plage fixe selon le modèle de temps de travail et
  premier pointage du jour, suivis de l'écart en minutes.
- **Avertissements** : début tardif, lorsque le premier pointage a lieu plus de
  15 minutes après le début de la plage fixe, et écart d'heures, lorsque le
  réel s'écarte du plan de plus de 10 %. Les jours sans plan – y compris les
  jours fériés et les jours de congé – ne reçoivent pas d'avertissement.

Si vous ouvrez la page pour une autre personne depuis l'onglet **Équipe** ou
**Organisation**, la mention **Vue pour** suivie de son nom apparaît en haut.

### Onglets Team et Organisation

**Équipe** affiche les membres de l'une de vos équipes ; si vous avez plusieurs
équipes, choisissez-la dans le champ **Équipe**. Les administrateurs et les
personnes disposant du droit organisation peuvent choisir toute équipe non
archivée. **Organisation** affiche toutes les personnes de l'organisation sous
**Tous les employés**.

Les deux vues totalisent par personne **Prévu (h)**, **Réel (h)**, **Écart
(h)** et **Avertissements** selon la même logique journalière que l'onglet
**Présence**. La loupe **Détails** ouvre la vue journalière de la personne pour
la même période. Avec le droit équipe, cela n'est possible que pour les membres
de vos propres équipes.

### Onglets Services, Projets et Sites

- **Services** : le prévu correspond aux services publiés et confirmés du
  planning avec la durée de leur créneau horaire (services de nuit passant
  minuit inclus) ; le réel est le chevauchement des pointages de la personne
  affectée avec ce créneau ; les pointages annulés ne comptent pas. Tuiles
  **Plan**, **Réel**, **Différence** et **Couverture** (réel rapporté au prévu
  ; mise en évidence sous 100 %). Avec **Regroupement**, choisissez
  **Quotidien** ou **Hebdomadaire** pour le graphique **Prévu vs réel par
  jour** ou **Prévu vs réel par semaine**. Le tableau **Par type de poste**
  indique **Services**, **Prévu (h)**, **Réel (h)**, **Écart (h)** et
  **Couverture**. Les services sans créneau horaire sont marqués **sans créneau
  horaire** : ils n'ont pas de prévu, et la présence journalière de la personne
  compte comme réel.
- **Projets** : le prévu est la somme de la durée prévue des ordres dont la
  période touche la période choisie ; le réel est le temps comptabilisé par
  projet. La durée prévue est le champ **Durée prévue (HH:MM)** de l'ordre ;
  s'il est vide, la durée d’intervention d’une commande planifiée, sinon la durée du créneau ou du rendez-vous compte. **Ordres
  (planifiés)** compte les ordres disposant d'une telle durée. Tuiles **Plan**,
  **Réel**, **Différence** et **Facturable (réel)**, le graphique **Top projets
  : prévu vs réel** (les douze projets avec le plus d'heures réelles) et le
  tableau **Par projet** avec **Projet**, **Client**, **Ordres (planifiés)**,
  **Prévu (h)**, **Réel (h)**, **Facturable (h)** et **Écart (h)**. Les projets
  sans ordre planifié portent la mention **sans données cibles** – ce n'est pas
  une alerte. Le temps sans projet figure dans la ligne **Sans projet**. Les
  comparaisons avec des budgets en temps et en argent sont fournies par
  **Rentabilité**.
- **Sites** : il n'existe pas de données cibles pour les sites ; la vue montre
  uniquement la répartition réelle du temps issu de la saisie de temps basée
  sur la localisation. Tuiles **Réel**, **Visites sur site** et **Personnes**,
  graphique **Temps réels par site (top 15)** et tableau **Par site** avec
  **Site**, **Client**, **Visites sur site**, **Personnes**, **Réel (h)** et
  **Part**. Les visites de géorepérages sans site associé figurent sous **Sans
  affectation de site** avec la mention **Géorepérage sans site**.

Les longs tableaux des onglets **Projets** et **Sites** sont répartis sur des
pages de 50 lignes chacune ; les graphiques exploitent en revanche toutes les
lignes, pas seulement la page affichée.

## Couverture

**Rapports** → **Équipe** → **Couverture** compare l'effectif prévu et
l'effectif réel : les services planifiés atteignent-ils l'effectif cible ? Le
rapport calcule selon les mêmes règles que la carte de chaleur du planning de
service.

- La cible par jour et par type de service est la valeur minimale (**Min**) de
  l'**Effectif cible** que vous définissez dans le planning de service par type
  de service. L'indication la plus précise s'applique : une entrée pour une
  **Date précise** prime sur une entrée pour le **Jour de la semaine**, qui
  prime elle-même sur une entrée valable **Toujours**. Les entrées **Toujours**
  s'appliquent à chaque jour du planning de service. Si aucune entrée ne
  convient pour un jour, l'**Effectif minimal par service** du planning
  s'applique aux types de service qui y figurent.
- Les jours sans planning de service n'ont de cible que s'il existe des
  exigences communes à tous les plannings (option **Pour tous les plannings de service** dans l’effectif cible). Si plusieurs plannings de service
  s'appliquent le même jour, leurs cibles et leurs réels s'additionnent.
- Le réel est le nombre de services planifiés par type de service et par jour.
  Seuls les services au statut **Publié** ou **Confirmé** comptent ; les
  brouillons et les services annulés ne comptent pas.
- Les types de service sans cible sur la période n'apparaissent pas ; les
  services des jours sans cible ne sont pas pris en compte.
- Le décompte se fait en jours-personnes : un service d'une personne un jour
  donné correspond à un jour-personne.

Tuiles : **Types de service** (avec le nombre de jours évalués), **Cible
(jours-personnes)**, **Réel (jours-personnes)** avec l'écart, **Exécution** (réel
rapporté à la cible) et **Jours en sous-effectif**. La carte de chaleur **Taux
de couverture par type de poste et jour de semaine** affiche dans chaque case
réel/cible et le pourcentage ; **Jours-personnes manquants par semaine** montre
les manques par semaine calendaire. Le tableau **Par type de service** indique
**Cible**, **Réel**, **Différence**, **Exécution** et **Jours sous** ; en
dessous, **Jours en sous-effectif** liste chaque jour concerné avec **Date**,
**Type de service**, **Cible**, **Réel** et **Lacune**.

La période vient de l'en-tête, 400 jours au maximum – une période plus longue
est coupée après 400 jours. Filtre **Équipe** : il ne compte que les services des
membres de l'équipe ; la cible reste inchangée. Export : **PDF** avec la carte
de chaleur et les jours en sous-effectif, **CSV** et **Excel** avec les valeurs
par type de service.

## Mois par employé

**Rapports** → **Équipe** → **Mois par employé** (titre de la page **Rapport
mensuel d’équipe**) montre les heures comptabilisées de toutes les personnes sur
une année civile – l'année dans laquelle commence la période de l'en-tête.

- Le tableau comporte une ligne par personne ayant des saisies de temps dans
  l'année, une colonne par mois, le total annuel des heures et le total des
  revenus en euros ; la dernière ligne totalise chaque mois.
- Au-dessus du tableau figurent les totaux annuels des heures et des revenus.
- Le graphique **Heures par collaborateur** montre le total annuel de chaque
  personne avec une ligne de médiane, la carte de chaleur **Heures par
  collaborateur et par mois** la répartition sur l'année.
- Toutes les saisies de temps comptent, quel que soit leur type.

Filtres : **Employé** et **Équipe**. Export : **PDF** au format paysage avec carte
de chaleur, **CSV** et **Excel**.

## Qui voit quoi

- **Présence** : chaque personne voit sa propre ligne. Les administrateurs et
  les personnes disposant du droit **Voir les présences** voient toute
  l'organisation.
- **Plan/réel** : chaque personne voit l'onglet **Présence** avec ses propres
  jours. L'onglet **Équipe** requiert le droit **Consulter le rapport de présence
  (équipe)**, les onglets **Organisation**, **Services**, **Projets** et
  **Sites** le droit **Consulter le rapport de présence (organisation)**. Les
  administrateurs voient tous les onglets. Dans l'attribution standard, le rôle
  Chef d'équipe dispose du droit équipe, la Direction du droit organisation et
  la Gestion du personnel des deux.
- **Couverture** et **Mois par employé** sont réservés aux administrateurs ; le
  menu ne les affiche pas aux autres personnes.
- **Couverture** et **Mois par employé** nécessitent le module complémentaire
  de rapports d'équipe ; **Présence** et **Plan/réel** sont disponibles sans
  lui.
