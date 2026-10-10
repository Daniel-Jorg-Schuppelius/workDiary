---
title: "Exploitation : répartition du temps, procédures, matériel, astreinte"
topic: reports.operations
version: 6
keywords:
    - rapport d’exploitation
    - commandes de service
    - analyse par centre de coûts
    - écarts de procédure
    - procédures bloquées
    - consommation de matériel
    - astreinte
    - taux de défauts
    - heures de projet
    - archiver des projets
    - classification manquante
    - analyse des tournées
audience:
    - admin
    - geschaeftsfuehrung
    - teamleitung
    - buchhaltung
    - user
    - aussendienst
related:
    - reports.overview
    - reports.drilldown
    - procedures.run
    - materials.manage
    - duties.overview
    - projects.manage
    - admin.time-dimensions
    - admin.classifications
---

Ces rapports montrent ce qui se passe dans l’exploitation : commandes de
service, tâches et tournées, la répartition du temps de travail sur les
projets, centres de coûts et autres dimensions, les écarts et blocages dans
les procédures, le matériel consommé, les astreintes, les défauts sur les
produits ainsi que les heures et les phases d’inactivité de chaque projet.
Vous trouverez la plupart des pages sous **Rapports** → **Projets et
clients** et **Rapports** → **Ressources**.

## Période, filtres et export

- Vous choisissez la période avec le sélecteur de période dans l’en-tête. La
  barre de filtres ne l’affiche qu’à titre indicatif ; les exceptions sont
  décrites avec le rapport concerné.
- Les filtres s’appliquent dès la sélection. L’interrupteur **Inclure les
  clients masqués** n’apparaît que si des clients sont marqués avec **Masquer
  dans les analyses** ; sans lui, leurs données restent exclues.
- Certains rapports comportent le champ **Zone**. Il n’apparaît que pour les
  administrateurs, qui passent ainsi de leurs propres données à toute
  l’équipe. Tous les autres y voient toujours leurs propres données.
- **PDF** télécharge une version imprimable ; **CSV** et **Excel** se trouvent
  sous **Export**. Les exports reprennent les filtres définis. Chaque export
  est consigné dans le journal d’audit.
- Les exports nécessitent le droit **Exporter les rapports**, y compris pour
  **Exécutions de procédure bloquées** ; sans ce droit, les boutons d'export
  n'apparaissent pas. Les administrateurs peuvent toujours exporter. Restent
  libres les exports qui ne contiennent que vos propres données – voir
  « Utiliser les rapports ».

## Operations

**Rapports** → **Projets et clients** → **Operations** ouvre le **Rapport
opérationnel** : commandes de service (commandes du type de commande
Service), tâches et tournées de la période.

- Tuiles : **Commandes de service** avec le taux de clôture (**Clôture**),
  **Temps de service Σ**, **Commandes** avec le nombre de commandes en retard et
  leur taux de clôture (la tuile change de couleur dès qu’une tâche est en
  retard) ainsi que **Tournées** avec les kilomètres et la durée planifiés.
- Graphiques : **Ordres de service : créés vs terminés par semaine** et
  **Backlog par client (top 15)** avec les commandes de service encore
  ouvertes par client. Avec le droit **Voir les rapports**, un clic sur une
  barre ouvre les points ouverts du client ; sans ce droit, les barres ne
  sont pas cliquables.
- Tableaux : **Commandes de service – statut**, **Commandes de service –
  priorité**, **Tâches – statut**, **Tâches – priorité** et **Tournées – par
  employé** (tournées, **Km planifiés**, **Durée planifiée**).

Les commandes de service sont regroupées en quatre groupes : **Ouvert**
(planifiée ou acceptée), **En cours**, **Problème** (en attente de réponse ou
de matériel) et **Terminé** (terminée, réceptionnée ou facturée). Les
commandes annulées comptent dans le total, mais ni dans un groupe ni dans le
taux de clôture. La date planifiée de la commande est déterminante. Les
tâches comptent si elles ont été créées, modifiées ou étaient échues dans la
période ; les tâches archivées restent exclues.

Filtres : **Zone**, **Client**, **Projet**, **Employé**, **Statut de
l'ordre** et **Inclure les clients masqués**. Le client et le projet
s’appliquent aux commandes de service et aux tâches, l’employé aux trois
domaines ; les tournées ne connaissent ni client ni projet. Le **Statut de
l'ordre** ne restreint que les tuiles et tableaux des commandes de service.

Sans droits d’administrateur, vous voyez les commandes de service qui vous
sont attribuées, les tâches qui vous sont attribuées ou que vous avez créées,
et vos propres tournées. Export en PDF, CSV et Excel.

## Répartition du temps

**Rapports** → **Projets et clients** → **Répartition du temps** ouvre la page
**Répartition du temps par dimension**. Elle montre comment les saisies de
temps réparties de la période se distribuent sur **Commandes**, **Actifs**,
**Projets**, **Centres de coûts**, **Sites**, **Véhicules**, **Activités** et
les dimensions libres issues de **Dimensions de temps**.

- Tuiles : **Temps réparti** et le nombre de **Dimensions**.
- Une carte par dimension avec **Cible**, **Minutes** (affichées en heures et
  minutes) et **Saisies** (nombre de saisies de temps), triée par temps
  décroissant.
- La base de données est constituée exclusivement des parts de répartition.
  Le temps non réparti apparaît dans les autres rapports de temps.

La page couvre toute l’organisation et n’a pas d’autres filtres. Elle
apparaît dans le menu pour les administrateurs et pour les rôles disposant du
droit **Voir les rapports**. Export en PDF, CSV et Excel.

## Écarts de procédure

**Rapports** → **Projets et clients** → **Écarts de procédure** analyse les
écarts saisis lors de l’exécution de procédures dans la période. L’entrée de
menu apparaît avec le droit **Voir les écarts de procédure**.

- Tuiles : **Écarts**, **Critiques**, **Part avec suivi** (part avec un point
  ouvert ou un ordre de suivi) et **Ø heures jusqu’à la décision** (de la
  création à l’acceptation du risque, uniquement les écarts décidés).
- Graphiques : **Écarts par type**, les écarts dans le temps par gravité et
  **Procédures avec le plus d’écarts (top 10)**.
- Liste : **Date**, **Procédure**, **Étape**, **Type**, **Gravité**, **Suivi**
  (**Point ouvert** ou **Ordre de suivi**), **Risque accepté le** et **H
  jusqu’à décision**. L’icône en fin de ligne ouvre l’exécution de procédure.

Filtres : **Procédure**, **Type**, **Gravité**, **Risque accepté**
(**Acceptés uniquement** ou **Ouverts uniquement**) et **Mesure de suivi**
(**Avec point ouvert/ordre de suivi** ou **Sans mesure de suivi**). Export en
PDF, CSV et Excel ; CSV et Excel contiennent en plus l’action proposée et le
motif.

## Exécutions de procédure bloquées

**Rapports** → **Projets et clients** → **Exécutions de procédure bloquées**
montre les exécutions qui attendent un délai d’attente, une deuxième personne
ou une décision de risque. L’entrée de menu apparaît avec le droit **Voir les
exécutions de procédure**.

- **Actuellement bloquées** : **Procédure**, **Motif du blocage**, **Bloquée
  depuis** et **Heures**, avec un accès direct à l’exécution. Les motifs de
  blocage sont un écart critique sans décision de risque, un délai d’attente
  pas encore écoulé et une deuxième personne manquante. À l’ouverture de la
  page, les délais d’attente écoulés sont levés.
- **Blocages terminés sur la période** : par motif de blocage et procédure
  **Nombre**, **Heures moy.** et **Plus long (h)**.

Ici, vous réglez la période dans le champ de début et de fin de la barre de
filtres ; sans saisie propre, le sélecteur de période de l’en-tête
s’applique. Export en CSV et Excel ; il contient les blocages terminés.

## Matériels

**Rapports** → **Ressources** → **Matériels** ouvre la page **Consommation de
matériel**. Elle repose sur les lignes de matériel des feuilles d’heures dont
la journée de travail se situe dans la période.

- Graphiques : **Valeur de consommation par matériau (top 20)** et les coûts
  de matériel dans le temps.
- Tuiles : **Matériels**, **Utilisations** et **Net Σ**.
- Tableau **Consommation par matériel** : **SKU**, **Matériel**, **Unité**,
  **Quantité**, **Utilisations** et **Net**, trié par montant net
  décroissant. Un même matériel dans des unités différentes figure sur des
  lignes distinctes ; les lignes sans fiche matériel apparaissent sous leur
  description.

Filtres : **Zone**, **Client**, **Projet** et **Inclure les clients
masqués** ; le client s’applique via le projet de la feuille d’heures. Sans
droits d’administrateur, vous ne voyez que vos propres feuilles d’heures.
Export en PDF, CSV et Excel.

## Service d’astreinte

**Rapports** → **Ressources** → **Service d’astreinte** ouvre le **Rapport de
service d’urgence** avec les services d’astreinte et les interventions
réelles par employé, tels qu’ils sont gérés dans la **Liste de travail**. Les
durées qui dépassent la période ne comptent qu’au prorata ; les entrées
archivées ne comptent pas.

- Tuiles : **Employés**, **Astreinte** (avec le nombre de services),
  **Interventions actives** (temps d’intervention avec le nombre
  d’interventions) et **Part active** (temps d’intervention rapporté au temps
  d’astreinte).
- Graphiques : **Astreinte par collaborateur et semaine** sous forme de carte
  de chaleur et les interventions dans le temps.
- Tableau par employé : **Services**, **Astreinte**, **Interventions**,
  **Temps d’intervention** et **Part active** avec une ligne de total.

Filtres : **Zone** (**Uniquement mon astreinte** ou **Toute l’équipe**,
uniquement pour les administrateurs), **Employé** et **Équipe**. Export en PDF
(avec la carte de chaleur), CSV et Excel.

## Analyse produit

**Rapports** → **Projets et clients** → **Analyse produit** montre les
défauts, points ouverts et l’effort par actif, groupe de produits ou modèle.
L’entrée de menu apparaît pour les administrateurs et avec le droit **Voir
les rapports**.

- **Niveau** : **Par actif**, **Par groupe de produits** ou **Par modèle** ;
  les autres filtres sont **Groupe de produits**, **Fabricant**, **Client** et
  **Inclure les clients masqués**.
- Colonnes : **Assets**, **Commandes** (commandes liées à l’actif créées dans la
  période), **Points ouverts** (actuellement ouverts, indépendamment de la
  période), **Escaladé** (points ouverts au statut **Bloqué**), **Défauts**
  (procès-verbaux de défaut de ces commandes dans la période), **Taux de
  défauts %** (défauts rapportés aux commandes) et **Dernier incident**.
- S’il existe dans la période des saisies de temps issues de sessions de
  télémaintenance pour les appareils, **Sessions de maintenance** et **Temps
  de maintenance** s’ajoutent, ainsi qu’un graphique du temps de maintenance.
- Graphiques : **Défauts sur la période (top 20)** et **Taux de défauts (top
  15)**. Les chiffres des points ouverts, escalades et défauts ainsi que les
  barres mènent aux listes détaillées correspondantes.

Export en PDF, CSV et Excel.

## Détails du projet

**Rapports** → **Projets et clients** → **Détails du projet** montre les
heures et le revenu d’un seul projet par mois. Le rapport porte sur l’année
civile dans laquelle commence la période choisie.

- Choisissez **Client** et **Projet**. Sans sélection, le premier projet de
  la liste s’affiche. Le filtre **Employé** n’existe qu’avec une vue des
  temps à l’échelle de l’organisation.
- La carte du projet indique les totaux annuels **Σ h** et **Σ €** et
  liste **Mois**, **Heures** et **Revenu** ; suit la **Répartition par
  employé**. Le revenu est la somme des montants enregistrés avec les saisies
  de temps.
- Graphiques : **Évolution des heures sur la période**, **Heures réelles et
  planifiées par mois** (plan issu du champ **Durée prévue (HH:MM)** des
  commandes du projet selon leur début, à défaut de la durée d’intervention d’une commande planifiée, sinon la durée du créneau ou du
  rendez-vous ; avec un employé sélectionné, seulement les commandes qui lui
  sont attribuées, sans vue des temps à l’échelle de l’organisation, seulement
  celles qui vous sont attribuées ; sans données de plan, une ligne montre la
  médiane des mois réels) et **Heures par type de commande et par mois**.

Les administrateurs et les rôles disposant de **Voir toutes les saisies de
temps** voient tous les projets et toutes les heures. Tous les autres ne
voient que les projets sur lesquels ils ont eux-mêmes saisi du temps, et
seulement leurs propres heures. Export en PDF, CSV et Excel dès qu’un projet
est sélectionné.

## Projets inactifs

**Rapports** → **Projets et clients** → **Projets inactifs** liste tous les
projets non archivés de l’organisation sur lesquels aucun temps n’a été saisi
dans la période.

- Graphique **Projets par durée d'inactivité** : **≤ 3 mois**, **3–6 mois**,
  **6–12 mois**, **> 12 mois** et **Sans saisie**, mesuré de la dernière
  saisie de temps jusqu’à la fin de la période.
- Tableau : **Projet**, **Client**, **Statut** et **Dernière activité** (la
  saisie de temps la plus récente, toutes périodes confondues).
- Filtre : **Client**.

Pour faire du rangement, cochez des projets et choisissez **Archiver la
sélection** ; confirmez la demande avec **Archiver**. Seuls les projets que
vous avez créés vous-même sont archivés ; les administrateurs peuvent tous
les archiver. Le message indique le nombre de projets effectivement archivés.
Export en CSV et Excel.

## Qualité des données

**Rapports** → **Projets et clients** → **Qualité des données** ouvre la page
**Qualité des données : classifications obligatoires**. Elle liste les
commandes de la période auxquelles manquent des indications exigées par les
règles obligatoires de **Classifications**. L’entrée de menu et la page
exigent le droit **Voir les rapports**.

- Tuiles : **Commandes avec lacunes**, **Lacunes bloquantes** (règles
  bloquantes) et **Écarts mineurs** (avertissements).
- Graphiques : les commandes avec lacunes de classification dans le temps et
  **Classifications manquantes par client (top 15)**.
- **Par domaine** et **Par phase** : où se trouvent les lacunes et à partir de
  quelle phase l’indication est exigée (à la création, avant la clôture ou
  avant la signature).
- **Commandes concernées** : **Commande**, **Date** et **Classifications
  manquantes** (rouge = bloquante, jaune = mineure). **Saisir a posteriori**
  ouvre la commande.

Filtres : **Client**, **Projet**, **Type de commande** et **Inclure les
clients masqués**. Au maximum les 1 000 commandes non archivées les plus
récentes de la période sont vérifiées. La page ne modifie rien et ne propose
pas d’export.
