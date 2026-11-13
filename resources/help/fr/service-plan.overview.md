---
title: "Plan des prestations par client et période"
topic: service-plan.overview
version: 1
keywords:
    - plan des prestations
    - liste annuelle
    - liste mensuelle
    - périmètre des prestations
    - sans objet
    - documents reçus
    - mes périodes
audience: []
related:
    - customers.deadlines
    - diary-entries.follow-up
    - diary-entries.review
    - service-plan.import
---

Le **plan des prestations** montre quelles prestations récurrentes reçoivent
vos clients et où elles en sont, sans tenir une deuxième liste. L’état
provient des commandes avec **période** ; les prestations d’un client figurent
dans son **périmètre des prestations**.

## Deux vues

- **Vue annuelle :** les lignes sont les clients ayant une prestation
  annuelle, les colonnes les prestations (p. ex. bilan annuel, impôt sur le
  revenu). Choisissez les colonnes via **Prestations** dans la barre de
  filtres ; sous **Filtres enregistrés**, vous conservez la sélection comme
  vue. « Année précédente » et « année en cours » suivent dans les vues
  enregistrées.
- **Vue par périodes :** les lignes sont client × prestation par mois,
  trimestre ou semestre, les colonnes les douze mois. Un trimestre couvre trois
  mois, un semestre six. Devant figurent les caractéristiques du jeu de règles
  des échéances clients (p. ex. périodicité TVA, niveau de dépendance).

## Ce que montre une cellule

- S’il existe une commande pour la période, la cellule montre son état :
  créé, accepté, en cours, en attente, en vérification, terminé.
- Sans commande, une **prestation obligatoire** affiche « manquant » ; une
  **prestation facultative** reste discrète.
- Marquez **sans objet** avec un motif tant qu’il n’y a pas de commande.
- L’**échéance** vient de la commande, sinon de l’échéance client
  correspondante ; en cours d’année, en dernier lieu de la fin de période. Ce
  qui la dépasse est marqué en rouge.
- Chaque cellule conserve **documents reçus le** et une **remarque** ; là où
  le jeu de règles le connaît, **demandé par anticipation le** apparaît aussi.

Un clic sur la cellule l’ouvre : **Créer la commande** préremplit la boîte de
dialogue avec client, type d’entrée, groupe de produits, période, responsable
et échéance ; si la commande existe, ouvrez-la depuis là.

## Compteurs et filtres

Les compteurs comptent des cellules (manquant, ouvert, en vérification,
terminé, sans objet, en retard) et filtrent les clients concernés. Autres
filtres : personne responsable, groupe de clients, recherche par nom ou numéro
de client.

## Créer les périodes de l’année

Dans la vue par périodes, **Créer les périodes de l’année** crée les commandes
de toutes les périodes d’une prestation, chacune comme suite de la précédente,
avec responsable, obligation de vérification, procédure et notes. Les
commandes existantes et « sans objet » restent ; les clients sans
prédécesseur reçoivent leur première commande via la cellule.

## Responsabilité et suppléance

Le responsable est la personne de la commande, sans commande la personne
responsable des caractéristiques d’échéance. **Les miens** montre aussi les
cellules des personnes que vous suppléez aujourd’hui. Sans le droit « Voir le
plan des prestations », vous ne voyez que ces cellules. La carte **Mes
périodes** du tableau de bord et la file de travail (« Périodes en retard »)
montrent ce qui reste à faire.

## Modèles de prestations

Les profils sectoriels apportent des **modèles de prestations** (p. ex.
déclarations et bilan annuel, assistance mensuelle, contrôle qualité, visite
de conseil). À la création d’un client, la boîte de dialogue les propose
comme périmètre des prestations.
