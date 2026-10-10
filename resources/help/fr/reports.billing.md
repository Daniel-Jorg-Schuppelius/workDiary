---
title: "Facturation, frais, paiements et chiffre d’affaires"
topic: reports.billing
version: 3
keywords:
    - créances ouvertes
    - balance âgée
    - temps non facturé
    - chiffre d’affaires par client
    - niveaux de relance
    - taux d’acceptation des devis
    - aperçu des frais
    - honoraires des externes
    - payer les freelances
    - prévoir les majorations
    - chiffre d’affaires par article
    - chiffre d’affaires par catégorie
audience:
    - admin
    - geschaeftsfuehrung
    - buchhaltung
    - personalverwaltung
    - teamleitung
related:
    - invoices.manage
    - finance.dunning
    - finance.incoming-invoices
    - travel-expenses.manage
    - org.members
    - admin.surcharge-rules
    - articles.master
    - reports.economics
---

Ces rapports regroupent l’argent lié aux prestations et au personnel : état
des factures et des créances ouvertes, temps pas encore facturé, notes de
frais, paiements aux collaborateurs externes, majorations attendues d’après
le planning des services et chiffre d’affaires par article. Vous trouverez la
plupart des pages sous **Rapports** → **Finances et audit**, la **Prévision
des majorations** sous **Rapports** → **Équipe**.

## Période et export

- Vous choisissez la période avec le sélecteur de période dans l’en-tête. La
  **Prévision des majorations** regarde en revanche vers l’avenir à partir du
  mois en cours.
- **PDF** télécharge une version imprimable ; **CSV** et **Excel** se trouvent
  sous **Export** ; les formats disponibles sont indiqués pour chaque
  rapport. Les exports reprennent les filtres définis. Chaque export est
  consigné dans le journal d’audit.

## Facturation

**Rapports** → **Finances et audit** → **Facturation** ouvre le **Rapport de
facturation**. Il s’ouvre pour les administrateurs et les rôles disposant du
droit **Voir toutes les saisies de temps** ; sans ce droit, l’accès est
refusé.

- Tuiles :
  - **Émis + payé (Σ brut)** : total brut des factures au statut **Émise**,
    **Partiellement payée** ou **Payée** dont la date de facture se situe dans
    la période (sans date de facture, la date de création compte).
  - **Créances ouvertes** : montant restant dû de toutes les factures au
    statut **Émise** ou **Partiellement payée**, indépendamment de la
    période. Les paiements reçus et les retenues de garantie ouvertes sont
    déduits ; les factures pro forma, avoirs et documents d’annulation ne
    comptent pas. La tuile devient rouge dès que l’une d’elles a plus de
    30 jours de retard ; l’indication en donne le nombre.
  - **Temps non facturé** : saisies de temps facturables de la période qui
    n’ont encore été consommées par aucun circuit de facturation, avec le
    nombre de saisies et le revenu attendu d’après les montants enregistrés.
- Graphiques : heures facturables et non facturables dans le temps, ainsi que
  **Chiffre d'affaires par client (top 15)** à partir des factures locales et
  des pièces reprises en miroir du logiciel comptable. Un clic sur un client
  ouvre **Clients et projets** pour ce client.
- **Factures par statut** : **Quantité**, **Net** et **Brut** par statut.
- **Ancienneté – postes ouverts** : les factures ouvertes selon le nombre de
  jours après l’échéance (sans échéance, à partir de la date de facture) dans
  les tranches **Actuel**, 1–7, 8–14, 15–30 et plus de 30 jours, chacune avec
  le montant restant dû, et **Total ouvert**.
- **Top clients (émis + payés sur la période)** : **Client**, **Factures** et
  **Brut** ; si des montants proviennent du logiciel comptable, une colonne
  supplémentaire **dont logiciel comptable** apparaît. Les factures
  partiellement payées sont incluses.
- **Factures électroniques entrantes (sur la période)** : entrées par statut
  avec nombre et montant brut, ainsi que le nombre transmis à la
  comptabilité.
- **Validation des entrées & niveaux de relance** : **Validation vérifiée**,
  **Validation réussie**, **Échec de la validation** et les factures ouvertes
  par niveau de relance 1 à 3.
- **Devis & chaîne documentaire (période)** : devis par statut, **Taux
  d'acceptation**, **Médiane création → décision** en jours, **Devis →
  facture**, **Pro forma → facture**, **Annulations / avoirs** et **Taux de
  correction**.

Filtres : **Client**, **Projet**, **Employé** et **Inclure les clients
masqués**. Le client et le projet s’appliquent aux factures, devis et temps,
l’employé uniquement aux temps. Les factures entrantes et les niveaux de
relance portent toujours sur toute l’organisation. Lorsqu’un projet est
choisi, les montants du logiciel comptable sont exclus, car ces pièces ne
connaissent pas de projet. Export en PDF, CSV et Excel.

## Notes de frais

**Rapports** → **Finances et audit** → **Notes de frais** ouvre le **Rapport
de frais** : frais par employé et par catégorie sur la période, calculés avec
les montants bruts selon la date de la dépense.

- Graphiques : frais par mois (ou semaine ou jour) par catégorie, les quatre
  plus grandes catégories séparément et le reste regroupé, ainsi que
  **Principaux émetteurs (top 15)**.
- Tuiles : **Total (brut)**, **Employés**, **Catégories** et **mois**.
- Tableau avec une ligne par **Employé** et **Catégorie**, une colonne par
  mois et le **Total**, suivi de **Principales catégories**.

Filtres : **Zone** (**Uniquement les miens** ou **Toute l’organisation**,
uniquement pour les administrateurs), **Employé**, **Équipe**, **Projet** et
**Statut**. Sans droits d’administrateur, vous ne voyez que vos propres
frais. La page ne propose pas d’export.

## Paiements externes

**Rapports** → **Finances et audit** → **Paiements externes** calcule les
montants à verser aux collaborateurs externes pour la période. L’entrée de
menu apparaît avec le droit **Gérer les données du personnel et de la paie**.

Sont pris en compte les employés dont le **Modèle de rémunération** est
réglé sur **Forfait** ou **Au temps passé** :

- **Forfait** avec l’intervalle **Mensuel** : **Montant forfaitaire (€)**
  multiplié par le nombre de mois de la période.
- **Forfait** avec l’intervalle **Par intervention** : montant forfaitaire
  multiplié par le nombre de jours comportant des saisies de temps.
- **Forfait** avec l’intervalle **Unique** : le montant forfaitaire une seule
  fois.
- **Au temps passé** : temps saisi multiplié par le **Taux de rémunération
  (€/h)**.

Le tableau affiche **Employé**, **Modèle**, **Base de calcul** et **Montant**
avec un total général. Des graphiques montrent les versements dans le temps
et **Versements par externe (top 15)**. Dans l’évolution, un forfait mensuel
apparaît une fois par mois, dans la section contenant le premier jour de ce mois
compris dans la période ; le total du graphique correspond ainsi au tableau. Tous les montants sont bruts, hors
impôts et charges sociales. Filtre : **Employé**. La page ne propose pas
d’export.

## Prévision des majorations

**Rapports** → **Équipe** → **Prévision des majorations** estime les minutes de
majoration par mois et par type de salaire sur la base des services
planifiés dans le **Planning des services**. L’entrée de menu apparaît pour
les administrateurs et avec le droit **Voir les rapports**.

- **Mois** : 3, 6 ou 12 mois à partir du mois en cours.
- **Employé** : tous les employés actifs ou une seule personne.
- Tableau : **Type de salaire**, **Règle**, une colonne par mois et
  **Total**, avec une ligne de total.

Le calcul utilise les **Règles de majoration** actives ; les services annulés
ne comptent pas. Il s’agit d’un simple aperçu sans contexte de site : les
règles qui dépendent du site ne s’appliquent qu’au pointage. Le décompte se
fait exclusivement via l’export des temps. Export en CSV et Excel.

## Chiffre d’affaires par produit

**Rapports** → **Finances et audit** → **Chiffre d’affaires par produit**
montre la quantité, le chiffre d’affaires net et la part par article.
L’entrée de menu apparaît pour les administrateurs et avec le droit **Voir
toutes les saisies de temps**.

- Base de données : lignes des factures locales, factures d’acompte et
  factures finales dont la date de facture se situe dans la période et dont
  le statut est **Émise**, **Partiellement payée** ou **Payée** ; les avoirs
  et pièces d’annulation diminuent les chiffres avec une quantité négative.
  S’y ajoutent les factures et avoirs repris en miroir du logiciel
  comptable. Les pièces transmises depuis une facture locale ne comptent
  qu’une fois, les brouillons et pièces annulées pas du tout.
- Tuiles : **Chiffre d’affaires net total**, **dont issu du logiciel
  comptable**, **Articles avec chiffre d’affaires** et **Part sans référence
  article** (lignes sans article choisi).
- Graphique des articles au chiffre d’affaires le plus élevé ; vous en
  fixez le nombre avec **Top N dans le graphique** (3 à 50, 10 par défaut).
  Un clic ouvre l’article.
- **Chiffre d'affaires par catégorie** : **Catégorie**, **Article**,
  **Chiffre d’affaires net** et **Part**.
- Tableau : **Numéro d’article**, **Article**, **Quantité**, **Unité**,
  **Chiffre d’affaires net**, **Part**, **Justificatifs** et **Source**
  (**local** ou le nom de la comptabilité raccordée). Les lignes sans article
  sont regroupées sous **sans référence article**.

Export en PDF, CSV et Excel.
