---
title: "Utiliser les rapports"
topic: reports.overview
version: 7
keywords:
    - statistiques
    - indicateurs
    - KPI
    - analyses
    - exporter un rapport
    - chiffre d'affaires par produit
    - contrôle du salaire minimum
    - temps de conduite et de repos
    - prévision de trésorerie
    - reporting
    - évaluations
    - trouver un rapport
audience: []
related:
    - reports.my-reports
    - reports.attendance
    - reports.personnel
    - reports.operations
    - reports.fleet
    - reports.billing
    - reports.compliance
    - reports.customer-analysis
    - reports.entry-type-analysis
    - reports.drilldown
    - reports.saved-views
    - exports.payroll
---

La zone de menu **Rapports** de la barre latérale regroupe tous les rapports –
de votre propre vue mensuelle aux rapports financiers et d'audit. Les rapports
condensent les données existantes, comme les saisies de temps, les pointages,
les absences, les ordres et les factures, par période, personne, équipe,
projet ou client. Ils ne sont pas une source de données à part : les
corrections se font sur l'ordre, le temps, l'absence ou la donnée de base
d'origine. Pour les rapports personnels ou financiers, le principe du besoin
d'en connaître s'applique. Ce sujet explique la structure des rapports et vous
oriente vers les sujets consacrés à chaque rapport.

## La page Aperçu

**Rapports** → **Aperçu** affiche en haut vos propres indicateurs pour la
période de l'en-tête : **Mes heures**, **Jours saisis**, **Ø par jour**
(rapporté aux jours saisis) et **Projets actifs** (projets sur lesquels vous
avez saisi du temps). Le premier graphique montre vos heures – jusqu'à 31 jours
sous forme d'**Heures par jour**, jusqu'à environ six mois sous forme
d'**Heures par semaine**, au-delà sous forme d'**Heures par mois**. **Projets
principaux par heures** indique vos dix projets comptant le plus d'heures.

En dessous figure une carte par groupe de menu avec tous les rapports que vous
pouvez ouvrir. La sélection correspond exactement à la barre latérale.

## Menu et visibilité

Les rapports sont classés en groupes : **Aperçu**, **Personnel**, **Équipe**,
**Projets et clients**, **Ressources** et **Finances et audit**. Une entrée
n'apparaît que si votre organisation utilise le module correspondant et que
vous disposez du droit nécessaire. Le module complémentaire de rapports
d'équipe débloque ces rapports : **Semaine par employé**, **Mois par employé**,
**Couverture**, **Congés et flex**, **Maladies**, **Qualifications**, **Analyse
client**, **Analyse des types de commande**, **Analyse produit**, **Clients et
projets**, **Détails du projet**, **Projets inactifs**, **Operations**,
**Rentabilité** et **Conformité du temps de travail**. Sans le module, seules
ces entrées manquent ; tous les autres rapports des groupes restent visibles.
Les entrées que vous avez masquées via « Personnaliser le menu & Toutes les
fonctions » manquent aussi sur la page d'aperçu.

## Période

La plupart des rapports suivent la période choisie dans l'en-tête. Cliquez sur
l'icône de calendrier (**Choisir la période**) et sélectionnez sous
**Sélection rapide** par exemple **Aujourd’hui**, **Cette semaine**, **Mois
dernier**, **Ce trimestre** ou **90 derniers jours**. Les flèches **Période
précédente** et **Période suivante** font défiler les périodes ; sur les grands
écrans, vous pouvez aussi saisir vos propres dates de début et de fin dans
l'en-tête et confirmer avec **Appliquer**. La période vaut pour toutes les
pages jusqu'à ce que vous la changiez ou vous déconnectiez ; sans sélection,
**Ce mois-ci** s'applique. La barre de filtres la rappelle.

Le sujet de chaque rapport indique les exceptions, par exemple :

- **Mon mois**, **Mon année** et **Mois par employé** affichent le mois ou
  l'année dans lequel la période commence.
- **Plan/réel** a ses propres champs **De** et **Jusqu’à** ; sans indication,
  le mois en cours s'applique.
- **Qualifications** et **Problèmes et formation** montrent la situation du
  jour.

Un lien contenant des dates – par exemple issu d'une évaluation enregistrée –
ouvre le rapport avec exactement cette période.

## Filtres

- Selon le rapport, la barre de filtres propose des champs tels que
  **Client**, **Projet**, **Employé**, **Équipe** ou **Statut**. Une sélection
  s'applique généralement immédiatement ; **Réinitialiser** supprime tous les
  filtres.
- Certains champs, comme **Zone** avec **Uniquement les miens** ou **Toute
  l’équipe**, ne sont visibles que pour les administrateurs et les personnes
  disposant du droit qui ouvre aussi la liste correspondante – par exemple
  **Voir les présences** pour **Présence**. Les autres personnes n'y voient que
  leurs propres données.
- Les clients marqués **Masquer dans les analyses** dans leurs données de base
  sont exclus des rapports portant sur les clients et les projets.
  L'interrupteur **Inclure les clients masqués** – il n'apparaît que si de tels
  clients existent – les réintègre ; si vous choisissez directement un tel
  client dans le filtre, il est également affiché.
- Vous pouvez enregistrer un rapport paramétré comme vue nommée, voir
  « Évaluations enregistrées ».

## Export

- **PDF** télécharge une version imprimable dans la mise en page des documents
  de votre organisation ; le menu **Export** propose **CSV** et **Excel**. Tous
  les rapports ne proposent pas tous les formats, certains n'ont aucun export.
- Les exports reprennent la période et les filtres de la page.
- Les exports des rapports du menu **Rapports** nécessitent le droit
  **Exporter les rapports** – y compris les rapports d'autres domaines qui y
  figurent : **SLA**, **Analyse** de la plateforme d'apprentissage,
  **Exécutions de procédure bloquées**, **Rapports financiers** ainsi que
  **BWA & budget**. Il en va de même pour **Candidatures et appels d'offres**,
  le **Rapport d'audit** des moyens de contrôle et le **Cockpit des marchés**.
  Les administrateurs peuvent toujours exporter. Sans ce droit, les boutons
  d'export n'apparaissent pas. Les rapports d'autres menus, comme **Rapport
  helpdesk**, **Rapport qualité** ou **Durabilité & ESG**, peuvent être
  exportés par toute personne autorisée à les ouvrir.
- Restent libres les
  exports qui ne contiennent que vos propres données : **Mon mois**, votre
  propre **Bilan de travail**, la vue **Uniquement les miens** et les rapports
  qui, sans droit supplémentaire, ne vous montrent que vos propres données (par
  exemple **Comptes de temps**, **Comparaison de périodes**,
  **Qualifications**, **Plan de congés** et **Détails du projet**), ainsi que
  le **Justificatif carnet de bord** d'un véhicule dont vous êtes le
  **Conducteur par défaut**. La **Présence d'urgence** peut être exportée par
  toute personne autorisée à l'ouvrir.
- Les en-têtes de colonnes et les valeurs fixes des fichiers CSV et Excel,
  comme la ligne de total, apparaissent dans votre langue ; les codes tels que
  les clés de statut ou de type de paie restent inchangés.
- Dans le réglage standard, les fichiers CSV sont séparés par des
  points-virgules et enregistrés en UTF-8. Les premières lignes commencent par #
  et indiquent le rapport, l'heure de création et une empreinte des filtres –
  un fichier peut ainsi être rattaché plus tard à son état.
- Les exports sont consignés dans le journal d'audit avec le rapport, le
  format et les filtres.

## Drill-down

De nombreux indicateurs, points de graphique et lignes de tableau sont
cliquables et mènent aux enregistrements sous-jacents ou à une vue plus
détaillée – par exemple de **Mon année** à **Mon mois** ou de l'onglet **Équipe**
de **Plan/réel** aux jours d'une personne. Pour en savoir plus, voir
« Drill-down de l'indicateur à l'intervention ».

## Droits

- Les rapports personnels sont ouverts à tous et ne montrent que vos propres
  données.
- Les analyses à l'échelle de l'organisation sur les clients, les revenus et
  les fournisseurs nécessitent le droit **Voir les rapports** ou le rôle
  d'administrateur.
- Les exports des rapports du menu **Rapports** nécessitent en plus le droit
  **Exporter les rapports**, voir Export. Dans l'attribution standard, la
  Direction, la Comptabilité, le Chef d'équipe et la Gestion du personnel en
  disposent.
- Certains rapports ont leur propre droit, par exemple **Consulter le rapport
  de présence (équipe)** pour Plan/réel ou **Voir le registre des événements de
  sécurité** pour la sécurité au travail.
- La vue sur toutes les personnes dans les rapports du personnel suit le droit
  de la liste correspondante : **Voir les présences** pour **Présence**, **Voir
  toutes les demandes de congés** pour **Congés et flex** et le **Plan de
  congés**, **Voir les arrêts maladie** pour **Maladies** et les motifs
  d'absence dans le **Plan de congés**, **Gérer les qualifications** pour
  **Qualifications** et **Voir toutes les saisies de temps** pour le **Bilan de
  travail** d'autres personnes. Les administrateurs l'ont toujours.
- Restent réservés aux administrateurs **Couverture** et **Mois par employé**,
  ainsi que la vue sur toutes les personnes dans **Comptes de temps**,
  **Comparaison de périodes**, **Notes de frais**, **Parc automobile**,
  **Service d’astreinte**, **Operations** et **Matériels**.
- Chaque rapport n'affiche que les données de l'organisation active.

## Quel rapport pour quoi

L'aperçu suivant suit les groupes de menu et indique, pour chaque rapport, le
sujet qui donne tous les détails.

### Personnel

**Mon mois**, **Mon année** et **Bilan de travail** montrent votre propre temps
jour par jour, sur l'année et par rapport à la cible – voir « Mes rapports ».
**Présence** et **Plan/réel** figurent aussi dans ce groupe ; ils relèvent de la
section suivante.

### Présence, planification et comptes de temps

- **Présence**, **Plan/réel**, **Couverture** et **Mois par employé** comparent
  les pointages, les services et les heures comptabilisées avec la cible et la
  planification – voir « Présence, prévu/réel et couverture ».
- **Semaine par employé** montre par personne les heures de chaque jour de la
  semaine avec le total hebdomadaire, douze semaines au maximum à la fois. La
  vue sur toutes les personnes est réservée aux administrateurs et aux
  personnes disposant du droit **Voir toutes les saisies de temps**.
- **Charge** met en rapport le temps saisi, facturable et facturé – voir « Taux
  d'occupation & réalisation ».
- **Présence d'urgence** montre qui se trouve dans le bâtiment, à l'extérieur
  ou absent – voir « Liste de présence d'urgence ».
- **Prévision des majorations** estime, à partir des services planifiés, les
  minutes de majoration attendues par mois et par type de rémunération ; elle
  nécessite le droit **Voir les rapports** – voir « Facturation, frais, paiements et chiffre d’affaires ».
- **Comptes de temps** et **Comparaison de périodes** sont expliqués dans
  « Comptes de temps », le **Plan de congés** dans « Plan de congés (vue
  annuelle) ».

### Personnel et RH

**Congés et flex**, **Maladies**, **Qualifications**, **Sécurité au travail**
et **Problèmes et formation** sont décrits dans « Personnel : congés, maladie,
qualifications, sécurité ». La **Comparaison de cohortes** confronte des
indicateurs avant et après une formation – voir « Comparaison de cohortes
(avant/après formation) ». **Formations** relève de la « Gestion des
formations », l'évaluation des cours **Analyse** de la « Plateforme
d'apprentissage ».

### Clients et projets

- **Analyse client** et **Clients et projets** sont expliqués dans « Analyse
  clients », **Valeur client** et **Fidélisation client** ont leurs propres
  sujets du même nom, **Analyse des types de commande** est traitée dans
  « Analyse par type d'intervention ».
- **Operations**, **Répartition du temps**, **Écarts de procédure**,
  **Exécutions de procédure bloquées**, **Analyse produit**, **Détails du
  projet** et **Projets inactifs** sont des rapports opérationnels – voir
  « Exploitation : répartition du temps, procédures, matériel, astreinte ». La **Qualité des données** y est également décrite.
- **SLA** et **Contrats SLA** sont décrits dans « SLA, contrats & niveaux de
  service ».

### Ressources

- **Parc automobile** montre par véhicule les kilomètres, la consommation, les
  coûts de carburant et de recharge ainsi que le coût par kilomètre. Le
  **Justificatif carnet de bord** fournit le carnet de bord fiscal par véhicule
  et période avec les types de trajet et la part privée, ainsi que la
  **Comparaison 1 %**. Le **Justificatif temps de conduite** atteste les temps
  de conduite et de repos par conducteur. Les trois sont expliqués dans
  « Parc automobile, carnet de bord et temps de conduite ».
- **Matériels** et **Service d’astreinte** relèvent de « Exploitation : répartition du temps, procédures, matériel, astreinte ».
- **Réception de documents cloud** est expliquée dans le sujet « Entrée de
  documents cloud ».

### Finances et audit

- **Rentabilité**, **Comportement de paiement**, **Analyse des fournisseurs**
  et **Valeur fournisseur** ont leurs propres sujets du même nom.
- **Facturation** et **Chiffre d’affaires par produit** – voir « Facturation, frais, paiements et chiffre d’affaires ».
  Le **Chiffre d’affaires par produit** est calculé à partir des factures
  locales et des factures de systèmes connectés comme Lexoffice, aussi par
  catégorie d'article ; les avoirs et documents d'annulation réduisent le
  chiffre d'affaires.
- **Notes de frais** regroupe les frais par personne, catégorie et mois ;
  seuls les administrateurs ont la vue sur toutes les personnes. **Paiements
  externes** calcule la rémunération du personnel externe sans paie et n'est
  visible qu'avec le droit **Gérer les données du personnel et de la paie**.
  Les deux sont décrits dans « Facturation, frais, paiements et chiffre d’affaires ».
- **Rapports financiers** et **BWA & budget** n'existent qu'avec une
  comptabilité tenue localement. Ils ne lisent que des écritures définitives ;
  la **Prévision de trésorerie** en fait partie, sur treize semaines par
  défaut – voir « Clôture et analyses ».
- **Conformité du temps de travail** contrôle les temps de travail réels au
  regard de la loi allemande sur le temps de travail – voir « Conformité au
  temps de travail (ArbZG) ». Les onglets **Tableau de bord** et **Historique
  des infractions** de cette page sont décrits dans « Justificatifs : activité d’audit, conformité et salaire minimum ». De là,
  vous générez aussi les justificatifs pour les autorités de contrôle : le
  **Justificatif MiLoG (douane)** pour le contrôle du salaire minimum avec
  début, fin et durée par jour de travail – également dans « Justificatifs : activité d’audit, conformité et salaire minimum » –
  et, si votre organisation enregistre les temps de conduite, le
  **Justificatif temps de conduite** sur les temps de conduite et de repos par
  conducteur – voir « Parc automobile, carnet de bord et temps de conduite ».
- **Activité d’audit** résume le journal d'audit par événement, personne et
  type d'objet et n'est accessible qu'aux administrateurs – voir
  « Justificatifs : activité d’audit, conformité et salaire minimum ».
