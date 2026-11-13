---
title: "Gérer les projets"
topic: projects.manage
version: 9
keywords:
    - créer un projet
    - gestion de projet
    - liste des projets
    - jalons
    - temps du projet
    - facturation du projet
    - clôturer un projet
    - réaffecter des temps
    - feuilles de temps
    - tâches du projet
    - taux horaire
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

L’onglet **Temps** au-dessus de la liste des projets affiche les saisies
de temps de tous les projets sur la période choisie en haut, sans ouvrir
chaque projet. Les saisies sont regroupées par projet ; « Regrouper par »
vous permet de passer à la date ou à la personne. Chaque groupe indique le
nombre de saisies et le total pour toute la période et peut être replié.
Vous pouvez filtrer par terme de recherche (projet, tâche, description),
client, projet, collaborateur, tag et caractère facturable. Seules
l’administration, la comptabilité et les personnes autorisées à consulter
tous les temps voient les saisies des autres ; les autres voient les leurs.
Les temps sans projet n’apparaissent pas ici.

La même règle de visibilité s’applique sur un projet (suivi du temps,
feuilles d’heures, total des heures), dans le dossier d’une commande et
pour les valeurs de temps sur la fiche client : sans accès à tous les
temps, les listes et les totaux ne comptent que vos propres saisies et
portent la mention « uniquement vos propres temps ».

Pour corriger des temps mal attribués, par exemple après un import avec
le mauvais utilisateur, utilisez l’onglet **Temps** : cochez des saisies
ou des groupes entiers et attribuez-les à une autre personne avec
« Attribuer un utilisateur ». Cela nécessite les droits d’administration
ou le droit « Réattribuer des saisies de temps à d’autres utilisateurs »
et ne concerne que les temps que vous pouvez voir. Les temps facturés et
signés restent verrouillés ; une sélection n’est jamais enregistrée
partiellement.

## Exemple pratique

Pour une migration de serveurs, le projet « Migration DC » est créé
avec durée, taux horaire et deux responsables. Les techniciens
saisissent leurs temps directement sur le projet ; en fin de mois, la
vue de détail montre d'un coup d'œil ce qui reste facturable.

## Temps cibles par personne

Convenez avec le client d’un temps d’intervention par personne et par jour et gérez-le dans
l’onglet Temps sous **Gérer les temps cibles** : une **valeur par défaut du projet** par jour
de semaine s’applique à toutes les personnes assignables, une ligne personnelle prime, une
**exception à une date** prime sur le jour de semaine ; congés, maladie et jours fériés mettent
la cible à zéro. Le contrôle nocturne compare la cible au temps saisi et signale les écarts
au-delà de la tolérance (réglage d’organisation, 15 minutes par défaut) aux responsables du
projet, dans la file de travail et dans la clôture de journée de la personne ; les constats
figurent dans l’onglet Temps, où ils peuvent être **confirmés**, et une saisie tardive les
résout. Avec **Facturer les heures convenues** dans l’onglet Facturation, le transfert et la
facture ajoutent le temps manquant comme position « Temps d’intervention convenu » ; l’aperçu
de la facture affiche aussi ces compléments. Chaque complément n’est facturé qu’une fois, soit
dans une facture, soit dans un transfert ; une saisie ultérieure sur ce jour n’est alors plus
facturable. L’annulation de la facture ou du transfert, la suppression de la position ou
l’abandon du brouillon libère à nouveau le complément.

Si la règle de facturation du projet indique l'unité « forfait » ou « Pauschale »,
chaque commande facture une ligne de quantité 1 au prix de la règle ; le
complément jusqu'aux heures convenues est alors supprimé, car le forfait couvre la
commande quelle que soit sa durée. Toutes les autres unités, par exemple heure,
jour ou pièce, comptent selon la durée.

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

## Rentabilité et échéances

Avec le droit d'accès aux rapports, l'aperçu du projet affiche chiffre
d'affaires, coûts, marge sur coûts variables et – si un budget est défini – sa
consommation sur la période choisie ; un clic ouvre l'analyse de rentabilité
avec les mêmes chiffres. Les délais de garantie et cautions du projet
apparaissent en dessous. Les tâches reprises indiquent leur plugin, par
exemple « Depuis GitHub Issues ».

**Couleur du projet :** en haut à droite de la boîte de dialogue du projet, vous choisissez la couleur qui signale le projet dans les listes, les temps et l’aperçu mensuel.
