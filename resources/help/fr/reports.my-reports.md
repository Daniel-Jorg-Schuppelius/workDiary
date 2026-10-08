---
title: "Mes rapports"
topic: reports.my-reports
version: 1
keywords:
    - Mon mois
    - Mon année
    - bilan de travail
    - mes heures
    - relevé d'heures
    - vue mensuelle
    - vue annuelle
    - vérifier les heures supplémentaires
    - comparaison prévu réel
    - imprimer le relevé
    - solde
audience: []
related:
    - reports.overview
    - reports.attendance
    - time-accounts.flex
    - attendance.manage
    - time-entries.edit
---

Sous **Rapports** → **Personnel**, chaque personne dispose de trois rapports sur
son propre temps : **Mon mois**, **Mon année** et **Bilan de travail**. Ils
n'affichent que vos propres saisies. Ils reposent sur vos saisies de temps ; le
bilan de travail utilise en plus vos pointages et votre modèle de temps de
travail. Les rapports ne sont pas une source de données à part : si un chiffre
est faux, corrigez la saisie de temps ou le pointage – le rapport se recalcule
à la prochaine ouverture.

## Choisir la période

Les trois pages suivent la période choisie dans l'en-tête. Cliquez sur l'icône
de calendrier (**Choisir la période**) et sélectionnez sous **Sélection
rapide** par exemple **Ce mois-ci**, **Mois dernier** ou **Cette année**. Les
flèches **Période précédente** et **Période suivante** font avancer ou reculer
d'une période ; sur les grands écrans, vous pouvez aussi saisir vos propres
dates de début et de fin dans l'en-tête et confirmer avec **Appliquer**. La
période active est rappelée dans la barre de filtres de la page.

- **Mon mois** affiche toujours le mois civil dans lequel la période commence.
- **Mon année** affiche l'année civile dans laquelle la période commence.
- **Bilan de travail** évalue la période exactement du premier au dernier jour.

## Mon mois

**Rapports** → **Personnel** → **Mon mois** liste toutes vos saisies de temps du
mois, jour par jour.

- Chaque jour commence par une ligne d'en-tête avec la date, le total de durée
  et le total de revenu du jour ; les dimanches sont mis en évidence en rouge.
- En dessous figurent les saisies avec les colonnes **Temps** (début et fin),
  **Type**, **Client / projet**, **Activité / description** (tâche et
  description), **Durée** et **Revenu**. Le revenu est le montant calculé pour
  la saisie ; le temps non facturable apparaît avec 0 €.
- Au-dessus du tableau figurent les totaux mensuels des heures et du revenu, à
  la fin la ligne **Total**.
- Deux graphiques : **Heures par jour** sous forme de courbe sur le mois et
  **Heures par semaine selon le type**, empilées par type pour chaque semaine
  calendaire.

Filtres : **Client**, **Projet** et **Type** avec **Tous**, **Travail**,
**Déplacement** et **Astreinte**. Une sélection s'applique immédiatement ;
**Réinitialiser** supprime tous les filtres.

Export : le bouton **PDF** génère la liste journalière avec les totaux et un
graphique des heures par jour. Le menu **Export** propose **CSV** et **Excel**
avec une ligne par saisie : date, début, fin, type, client, projet, tâche,
description, minutes et revenu. Tous les exports reprennent les filtres
définis.

## Mon année

**Rapports** → **Personnel** → **Mon année** montre vos heures sur toute
l'année civile.

- La tuile **Total annuel** indique les heures de l'année.
- La carte de chaleur **Heures par jour** comporte une ligne par mois et une
  colonne par jour (1 à 31). Plus une case est colorée, plus il y a d'heures ;
  l'échelle de couleur se base sur la valeur journalière la plus élevée de
  l'année. Au survol apparaissent la date et les heures, les dimanches sont
  marqués en rouge.
- Le graphique à barres **Heures par mois** montre les totaux mensuels.
- Un clic sur le nom d'un mois dans la carte de chaleur ou sur une barre ouvre
  **Mon mois** pour exactement ce mois, avec les mêmes filtres.

Filtres : **Client**, **Projet** et **Type** comme dans **Mon mois**. Cette page
ne propose pas d'export.

## Bilan de travail

**Rapports** → **Personnel** → **Bilan de travail** compare pour la période
choisie la cible, la présence et le temps saisi. Les tuiles en haut :

- **Cible** : temps de travail cible issu de votre modèle de temps de travail.
  Les jours fériés et les jours de congé approuvé n'ont pas de cible.
- **Présence** : vos pointages moins les pauses. Les pointages annulés ne
  comptent pas ; un pointage en cours est compté jusqu'au moment présent.
- **Saisi** : vos saisies de temps des types travail et déplacement.
  L'astreinte ainsi que les saisies avec l'activité **Pause** ou **Absence** ne
  comptent pas.
- **Non attribué** : présence qui n'est pas encore couverte par des saisies de
  temps (présence moins temps saisi, jamais négatif).
- **Solde** : temps saisi moins cible – en vert si positif, en rouge si négatif.

En dessous se trouvent les graphiques **Heures réelles et cibles par jour**
(pour les périodes de plus de 62 jours **Heures réelles et cibles par semaine
calendaire**) et **Heures réelles et cibles par mois**, chacun avec une ligne de
médiane. Le bloc **Répartition par activité** indique les heures saisies par
activité. Le tableau affiche par jour **Date**, **Cible**, **Présence**,
**Pause**, **Saisi**, **Non attribué** et **Solde** ainsi que la ligne
**Total** ; les jours sans cible, sans présence et sans saisie sont omis. Un
clic sur un en-tête de colonne trie le tableau.

Export : **PDF** avec les indicateurs et le tableau journalier.

## Qui voit quoi

- **Mon mois** et **Mon année** n'affichent toujours que vos propres saisies,
  y compris pour les administrateurs.
- Le **Bilan de travail** affiche par défaut votre propre bilan. Seuls les
  administrateurs voient une barre de filtres avec **Employé** et **Équipe** et
  peuvent ainsi ouvrir le bilan d'une autre personne de la même organisation ;
  **Équipe** ne fait que restreindre la liste des employés proposés.
- Le bilan de travail ne calcule que la période choisie. Il n'affiche pas le
  solde cumulé de votre compte de temps de travail – voir pour cela « Compte de
  temps de travail & validation mensuelle ».
