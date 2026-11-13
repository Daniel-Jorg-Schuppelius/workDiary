---
title: "Échéances clients"
topic: customers.deadlines
version: 1
keywords:
    - Échéances clients
    - Échéances
    - Déclaration anticipée de TVA
    - Prorogation permanente
    - Retenue sur salaires
    - État récapitulatif
    - Déclaration annuelle
    - Relevé des cotisations
    - Visite de conseil
    - Périmètre de prestations
audience: []
related:
    - contacts.manage
    - work-queue.overview
    - delegations.manage
    - accounting.filings
---

Certaines échéances ne dépendent pas d’un contrat mais des caractéristiques du
client et de la loi : la déclaration anticipée de TVA d’un client de cabinet,
le relevé des cotisations d’un client de paie, la visite de conseil d’un client
de soins. workDiary calcule ces **échéances clients** à partir des
caractéristiques et du périmètre de prestations du client, rappelle à temps et
les coche dès que la commande correspondante est terminée. L’interface parle de
« client » ; dans workDiary, « mandant » désigne l’organisation.

## Jeux de règles

Un **jeu de règles** contient les règles légales d’un secteur. Il est activé
lorsque le profil sectoriel l’apporte ou lorsque vous l’activez sous
**Échéances clients › Paramètres**.

- **Conseil fiscal :** déclaration anticipée de TVA (10e jour après la
  période, un mois plus tard avec prorogation permanente), acompte spécial
  (10 février pour les déclarants mensuels avec prorogation), retenue sur
  salaires (10e jour, jamais prolongée), état récapitulatif (25e jour, jamais
  prolongé), déclarations annuelles par impôt selon le périmètre (avec
  conseiller : fin février de la deuxième année suivante, sinon 31 juillet ;
  délais transitoires jusqu’en 2024) et – avec la prestation **paie** – relevé
  et échéance des cotisations sociales (antépénultième jour ouvré bancaire,
  relevé deux jours ouvrés bancaires avant). Si une échéance fiscale tombe un
  week-end ou un jour férié **au siège du centre des impôts**, le jour ouvré
  suivant s’applique ; avec plusieurs caisses maladie, la date la plus précoce
  de tous les Länder s’applique.
- **Soins à domicile :** visite de conseil selon le § 37, al. 3 SGB XI –
  obligatoire uniquement avec l’allocation de soins et un niveau 2 à 5, une
  fois par semestre ; aux niveaux 4 et 5, sur demande, une fois par trimestre.
  workDiary lit le niveau dans la classification du client.

## Gérer les caractéristiques

Dans le **cockpit du client**, la carte **Échéances clients** montre les
caractéristiques et les prochaines échéances ouvertes. **Caractéristiques des
échéances** ouvre la boîte de dialogue, préremplie comme la version en vigueur
(« comme l’an dernier »). Une modification s’applique à partir du jour choisi
et clôt la version précédente ; les échéances passées gardent leur base.
workDiary ne recalcule ensuite que les échéances **ouvertes** ; celles faites,
prolongées ou demandées par anticipation restent telles quelles. Rien de ce qui
était dû avant le début de la première version n’est créé.

Si le **Land du centre des impôts** ou des organismes collecteurs manque,
workDiary utilise la région de jours fériés de l’organisation et signale
l’échéance.

Le **périmètre de prestations** liste les prestations du client (type de
saisie et groupe de produits, obligatoire ou facultative, type de période,
validité). Le jeu conseil fiscal y lit les déclarations annuelles et la paie ;
le plan de prestations les affiche par période.

## Traiter les échéances

Un clic sur une échéance l’ouvre avec son historique. Vous pouvez la marquer
**faite**, la **prolonger** (nouvelle date et motif), la signaler **demandée
par anticipation** (le centre des impôts exige la déclaration plus tôt,
§ 149, al. 4 AO), la déclarer **sans objet** ou la **rouvrir**. Une commande
terminée avec période fait d’elle-même l’échéance correspondante, par exemple
une commande **visite de conseil** 2026-H1 la visite du premier semestre.

## Vue d’ensemble, rappels et remplacements

- **Échéances clients** liste toutes les échéances avec feu tricolore, filtres
  par jeu de règles, type, responsable, mois et statut, et export CSV ou Excel.
- La **personne responsable** reçoit un rappel groupé lorsque des échéances
  entrent dans le préavis (7 jours par défaut, réglable par jeu) et un autre en
  cas de retard. Les échéances jusqu’au week-end et celles en retard figurent
  dans sa **file de travail** et son **calendrier**.
- Pendant un **remplacement**, la personne remplaçante voit ces échéances et
  rappels avec la mention « Pour le compte de … ». En cas de départ ou de
  transfert définitif, la responsabilité passe au successeur.

Les personnes disposant du droit **Consulter les échéances des clients**
peuvent les voir ; caractéristiques, prestations et toutes les échéances sont
gérées par celles qui ont **Gérer les échéances des clients et leurs
caractéristiques**. La personne responsable traite aussi ses propres échéances
avec le droit de consultation.

## Ce que workDiary ne fait pas

Il ne transmet rien à ELSTER, à DATEV ni aux caisses de dépendance et ne tient
pas la comptabilité des clients. Les échéances convenues restent des
obligations contractuelles, celles des équipements des plans de contrôle.
