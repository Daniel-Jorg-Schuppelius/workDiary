---
title: "Fidélisation client"
topic: reports.customer-retention
version: 2
audience: []
related:
    - reports.customer-value
    - reports.customer-analysis
---

Ce rapport montre **dans quelle mesure l'entreprise conserve ses
clients** — et d'où se nourrit la base client.

## Lire la matrice des cohortes

Les clients sont regroupés par **année de première prestation** (à
l'échelle de l'organisation, indépendamment du filtre de période). Chaque
ligne est une cohorte, chaque colonne « +n » la n-ième année suivante.
Exemple : ligne **2028 (n=12)**, colonne **+2** = 75 % → sur les 12
clients arrivés en 2028, 9 ont encore acheté des prestations en 2030.
Si une ligne chute vite, les clients partent tôt après l'entrée.
**Un clic sur une ligne ou une cellule** ouvre la liste nominative.

## Pont de la base client — définitions

« **Actif** » à une date signifie : prestation dans le seuil configuré
avant cette date (365 jours par défaut, filtre « Perdu après »). Le pont
s'équilibre exactement :

Base au début **+ nouveaux clients** (première prestation dans la période)
**+ regagnés** (inactifs avant, de nouveau actifs)
**− nouveaux redevenus inactifs** (premiers achats sans suite)
**− perdus** (actifs au début, plus à la fin)
= base à la fin.

Un clic sur une étape du pont saute à la liste nominative en dessous ;
chaque nom mène au rapport clients & projets.

## Indicateurs

- **Taux de retour** : part des clients actifs l'an dernier qui le sont
  aussi cette année — l'indicateur de fidélisation le plus honnête.
- **Ancienneté client moyenne** : années depuis la première prestation,
  moyennées sur les clients actifs à la fin.

## Qu'en faire ?

- La cohorte s'effondre en année +1 → revoir l'onboarding / la deuxième
  commande.
- Les clients perdus s'accumulent → collecter les causes (prix, qualité,
  interlocuteur), lancer une reconquête ciblée.
- Taux de retour sous ~70 % dans une activité récurrente → mettre en
  place des mesures de fidélisation (contrats de maintenance, points
  réguliers).
