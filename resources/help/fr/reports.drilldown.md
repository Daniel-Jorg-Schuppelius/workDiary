---
title: "Drill-down de l'indicateur aux cas individuels"
topic: reports.drilldown
version: 3
keywords:
    - détail des indicateurs
    - explorer un KPI
    - données sous-jacentes
    - liste détaillée
    - ventiler un indicateur
    - points ouverts par client
    - protocoles de défaut
    - défauts récurrents
    - causes
    - exporter le rapport
    - analyse détaillée
    - remonter aux données
audience: []
related:
    - reports.customer-analysis
    - reports.entry-type-analysis
    - reports.operations
---

Dans l'**analyse client**, l'**analyse des types de commande** et
l'**analyse produit**, les chiffres sont cliquables. Le drill-down liste
les cas individuels derrière le chiffre – avec la période de l'en-tête
de page et les filtres actifs au moment du clic. L'en-tête indique le
client, le type de commande ou le périmètre et la période ; **Vers
l'analyse client**, **Vers l'analyse des types de commande** ou **Vers
l'analyse produit** ramène à l'analyse. Les pages exigent la permission
« Voir les rapports ».

## Points ouverts

**Drilldown : points ouverts** affiche les points encore ouverts – sur
le client lui-même, sur ses commandes et projets, sur les commandes d'un
type de commande ou sur les actifs du périmètre choisi (avec alors la
colonne **Actif**). Colonnes : **Titre**, **Statut**, **Severity**,
**Échéance** et **Attribué** ; les points modifiés en dernier sont en
haut. Un clic sur la colonne **Escaladé** ouvre la liste avec
**Uniquement escaladé** : seuls les points bloqués apparaissent alors.

## Protocoles de défaut

**Drilldown : protocoles de défaut** liste les protocoles de défaut sur
les commandes de la période ; dans l'analyse client, il se trouve
derrière la colonne **Reprise**. Colonnes : **Titre**, **Statut**,
**Moment**, **Créé par** et **Commande** – un clic ouvre la commande.
Les protocoles les plus récents sont en haut.

## Causes et défauts récurrents

**Analyse détaillée : cause (type de commande)** affiche deux listes
pour une cause issue de l'analyse des types de commande : **Reprises
avec ce motif** (date, commande, employé, minutes) et **Réclamations
avec cette cause racine** (numéro, commande, statut).

**Exploration : défauts récurrents** indique par actif les signalements de
défaut de la période et des **12 mois** écoulés ainsi que les degrés de
gravité. À partir de trois défauts en douze mois, la ligne porte la marque
**Défaut récurrent** ; le nom de l'actif ouvre le dossier de l'objet. Vous y
accédez par le chiffre de la colonne **Défauts récurrents** de l'analyse
produit : elle n'affiche alors que les cas de défauts récurrents de la ligne
cliquée, et l'en-tête indique la zone et **Défauts récurrents uniquement**.

## Export

Vous exportez les points ouverts et les protocoles de défaut en PDF, CSV
ou Excel, les défauts récurrents en CSV ou Excel. L'export contient les
mêmes lignes que la liste et indique la période et les filtres. La page
des causes n'a pas d'export.
