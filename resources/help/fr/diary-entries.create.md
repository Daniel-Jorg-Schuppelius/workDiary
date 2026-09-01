---
title: "Créer une commande"
topic: diary-entries.create
version: 2
audience: []
schema: process
related:
    - protocols.create
    - time-entries.start
    - projects.manage
    - reports.entry-type-analysis
---

## Objectif et contexte

Les entrées de commande forment le journal des interventions de
WorkDiary : chaque maintenance, panne ou montage reçoit une entrée
avec client, type et statut. L'entrée est l'ancrage des
procès-verbaux, des temps et de la facturation — et ses transitions de
statut tracent le cycle de vie de la commande.

## Prérequis

- Un **client** existant (obligatoire), un **projet** en option.
- Des **types d'entrée** adaptés (maintenance, panne, montage…) —
  gérés par l'administration.
- Le droit de créer des entrées de commande.

## Déroulement recommandé

1. Ouvrez **« Nouvelle entrée »** dans la barre supérieure ou l'action
   rapide du tableau de bord.
2. Saisissez le **client** (obligatoire) et le **projet** le cas
   échéant.
3. Choisissez le **type d'entrée** et décrivez le **contenu** en une à
   deux phrases.
4. En option : une **durée prévue** en minutes.
5. Les transitions de statut passent ensuite par la **fenêtre de
   détail** — pas de mise à jour en masse depuis la liste.

![Liste de travail du journal des commandes avec compteurs de statut et entrées](media/auftraege/arbeitsliste.png)
*La liste de travail : compteurs de statut en haut, puis les commandes avec statut et actions.*

## Exemple pratique

Une panne est signalée par téléphone : le bureau crée en moins d'une
minute une entrée de type « panne » avec client et description courte.
Le technicien trouve la commande dans sa liste, y démarre le temps et
joint le procès-verbal plus tard.

## Erreurs fréquentes

- **Attendre des changements de statut en masse :** les transitions se
  font volontairement une à une via la fenêtre de détail — la piste
  d'audit reste propre.
- **Utiliser un client « divers » :** sans vrai lien client, analyses
  et facturation manquent ensuite.
- **Écrire un roman :** une à deux phrases suffisent — les détails
  vont dans le procès-verbal.

## Effets et prochaines étapes

Avec l'entrée, l'ancrage de tout le reste existe : y saisir le temps,
créer un procès-verbal si besoin et mener le statut jusqu'à la
clôture. L'analyse par types montrera plus tard où passe vraiment le
temps de l'entreprise.
