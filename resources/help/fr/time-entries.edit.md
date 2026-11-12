---
title: "Modifier une saisie de temps"
topic: time-entries.edit
version: 4
keywords:
    - corriger un temps
    - changer une saisie
    - ajuster les heures
    - heure erronée
    - modifier début et fin
    - modifier la pause
    - changer de projet
    - demande de correction
    - saisie verrouillée
    - historique des modifications
    - temps administratif
    - saisir du temps interne
    - temps machine
    - durée sur plusieurs jours
    - lecture de disque
audience: []
related:
    - time-entries.start
    - reports.customer-analysis
---

Cliquez sur une ligne de la liste des temps pour modifier la saisie ;
chaque changement est consigné dans le journal d'audit avec personne,
horodatage et valeur précédente. Les saisies **déjà validées** sont
verrouillées — utilisez les **demandes de correction** pour les
rectifications ultérieures. Ne modifiez jamais seulement la fin d'un
service : ajustez **toujours** début, fin et pause ensemble, sinon les
analyses deviennent incohérentes. Le changement de projet est autorisé
tant que l'ancienne affectation n'a pas déjà été facturée.

## Temps administratif

Le temps administratif est du temps de travail sans projet : réunions,
formations, travaux internes, temps de déplacement, pauses et autres
activités. Vous le saisissez toujours pour vous-même – la saisie est
rattachée à votre propre compte. La saisie pour d'autres personnes n'est pas
prévue ici.

**Accès :**

- Dans la barre latérale via **Nouveau …** → **Activité quotidienne** →
  **Temps administratif**. La date est préremplie avec aujourd'hui.
- Dans la vue du jour (**Activité quotidienne** → **Saisie** →
  **Aujourd’hui**) via le bouton **Temps administratif** en haut à droite. La
  date est préremplie avec le jour affiché – y compris un jour antérieur si
  vous y êtes revenu avec **Jour précédent**.

**Champs de la boîte de dialogue « Saisir du temps administratif » :**

- **Date** et **Durée (minutes)** sont obligatoires. La durée est comprise
  entre 1 et 1440 minutes ; 30 minutes sont préremplies.
- **Type d’activité** (obligatoire) : **Administration** (par défaut),
  **Réunion**, **Formation**, **Interne**, **Déplacement**, **Pause** ou
  **Autre**.
- **Catégorie (facultatif)** : l'une des catégories d'activité actives de
  votre organisation ; la liste indique le type d'activité de chaque
  catégorie.
- **Période (facultatif)** : **Début (heure)** et **Fin (heure)**. Une fin sans
  début est refusée. Si la fin est antérieure au début, elle compte pour le
  jour suivant – c'est ainsi que vous saisissez des temps au-delà de minuit.
  Si le début et la fin sont indiqués, l'application calcule la durée à partir
  de ces heures et remplace le nombre de minutes saisi.
- **Description** (jusqu'à 500 caractères) et **Tags**.
- Si vous avez déjà un pointage à la date préremplie, la saisie y est liée.
  La boîte de dialogue affiche alors la mention « Sera lié au pointage
  (depuis …) ».

Après **Saisir**, vous revenez à la vue du jour de la date choisie. La saisie
y figure sous **Saisies de temps** avec son type d'activité et, le cas
échéant, sa catégorie.

**Différences avec la saisie de temps sur projet :**

- Il n'y a pas de champ projet ; le temps est classé par type d'activité et
  catégorie.
- Le début et la fin sont facultatifs – une durée suffit.
- Le type d'activité est limité aux catégories citées ci-dessus, sans lien
  avec un projet.

**Modifier et supprimer :** dans la vue **Aujourd’hui**, l'icône du crayon
(**Modifier**) sur la ligne d'un temps administratif ouvre la boîte de dialogue
**Modifier le temps administratif**. Dans cette boîte de dialogue, vous modifiez les mêmes champs, rédigez des **Commentaires**
et supprimez la saisie avec **Supprimer** (après une demande de confirmation).
Après l'enregistrement ou la suppression, la vue du jour de la date de la
saisie s'ouvre. Vous ne pouvez modifier et supprimer que vos propres saisies,
et seulement tant qu'elles ne sont pas verrouillées. Une saisie est
verrouillée si

- la fenêtre de correction a expiré (par défaut 7 jours après le jour de la
  saisie),
- le mois a déjà été validé pour vous,
- vous avez déjà clôturé la journée de la saisie (les modifications passent
  alors par **Demander une correction** dans la vue du jour),
- la feuille d'heures associée est signée ou verrouillée, ou
- la saisie a déjà été exportée.

La boîte de dialogue indique alors le motif ; les commentaires restent
possibles.

**Autorisation :** toute personne connectée peut saisir du temps
administratif pour elle-même. Le rôle **Administrateur** peut aussi modifier
et supprimer les saisies d'autres personnes et les saisies verrouillées ; la
boîte de dialogue signale alors que vous modifiez en tant qu'administrateur.

## Temps machine

Si un appareil ou un processus continue sans vous, par exemple la lecture
d'un disque dur défectueux pendant deux jours, saisissez-le comme **Type** →
**Temps machine** au lieu de travail :

- La durée peut atteindre **31 jours**, en mode durée aussi au-delà de 24
  heures (par exemple `51:37`) ou comme **De / À** sur plusieurs jours. Les
  saisies de type travail, déplacement et astreinte restent limitées à
  24 heures.
- Le temps machine **ne compte pas comme votre temps de travail** : il est
  exclu de l'horaire variable, du contrôle des pauses, de l'export paie, des
  versements, des quotas SLA et du temps de travail d'une feuille d'heures.
  Les listes de projet l'affichent sur une ligne « dont temps machine » et
  avec un badge sur la saisie.
- Il est facturé au **taux horaire temps machine** de l'organisation
  (Paramètres → Valeurs par défaut des factures) ; une **règle de
  facturation** du projet pour le type temps machine prime et peut indiquer
  un prix fixe ou une prestation propre. Les conditions client et les taux
  des personnes ne s'appliquent pas.
