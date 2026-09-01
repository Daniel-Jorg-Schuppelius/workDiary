---
title: "Gérer les projets"
topic: projects.manage
version: 2
audience: []
modules:
    - module.vertrieb
schema: process
related:
    - contacts.manage
    - time-entries.start
    - timesheets.manage
    - finance.transfers
---

## Objectif et contexte

Les projets regroupent tout ce qui appartient à une affaire : client,
durée, responsables, tâches, jalons, temps saisis et règles de
facturation. Ils font le lien entre saisie des temps et facturation —
ce qui est bien réglé au projet n'a jamais besoin d'être corrigé
saisie par saisie.

## Prérequis

- Un client existant (voir clients & fournisseurs).
- Le droit de gérer les projets.
- Pour la facturation : des règles clarifiées (taux horaire,
  forfaits, facturable oui/non).

## Déroulement recommandé

1. Créer le projet avec **client et période**.
2. Définir **responsabilités et statut**.
3. Planifier **tâches ou récurrences**.
4. Saisir les prestations et suivre l'avancement dans la vue de
   détail.
5. Avant la clôture, contrôler tâches ouvertes, temps, feuilles
   d'heures et positions facturables — ne fermer qu'ensuite.

![Liste des projets avec client, statut et durée](media/kunden/projektliste.png)
*La liste des projets : chaque projet avec client, statut et durée.*

## Exemple pratique

Pour une migration de serveurs, le projet « Migration DC » est créé
avec durée, taux horaire et deux responsables. Les techniciens
saisissent leurs temps directement sur le projet ; en fin de mois, la
vue de détail montre d'un coup d'œil ce qui reste facturable.

## Erreurs fréquentes

- **Fermer trop tôt :** un projet fermé n'accepte plus de saisies —
  vérifier d'abord temps et positions ouverts.
- **Changer les règles de facturation rétroactivement** en espérant
  que les anciennes saisies suivent : les règles valent pour
  l'avenir.
- **Tout saisir sans projet :** sans lien projet, analyses et remise
  propre à la facturation manquent ensuite.

## Effets et prochaines étapes

Règles de facturation et statut du projet déterminent quels temps et
matériels partent dans la remise. Ensuite : configurer la saisie des
temps sur le projet et vérifier la remise à la facturation en fin de
période.
