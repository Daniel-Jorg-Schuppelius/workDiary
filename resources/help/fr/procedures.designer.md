---
title: "Concepteur de procédures"
topic: procedures.designer
version: 4
keywords:
    - instruction de travail
    - créer une check-list
    - mode opératoire
    - procédure standard
    - modèle de processus
    - workflow
    - étapes obligatoires
    - principe des quatre yeux
    - étape conditionnelle
    - publier une version
audience: []
related:
    - procedures.run
---

Le **Concepteur de procédure** vous permet de définir des déroulements
obligatoires (instructions de travail, check-lists) exécutés ensuite sur
les ordres. Les modèles se trouvent sous **Système** → **Règles et
processus** → **Modèles de procédure** ; **Modifier** ouvre le
concepteur d’un modèle.

## Modèle et versions

- Un **Modèle** (**Nouveau modèle**) possède un **Code** unique, un
  **Nom**, un **Domaine** facultatif (p. ex. `it`, `hvac`) et une
  **Description** ; le concepteur y ajoute le **Niveau de risque**.
- Les étapes appartiennent toujours à une **Version**. Tant qu’une
  version est un **Brouillon**, vous pouvez modifier librement les étapes
  et les conserver avec **Enregistrer** ; une **Note de modification**
  consigne ce qui a changé.
- **Publier** rend la version valide et **immuable**. Les corrections
  exigent une **Nouvelle version** – les ordres en cours et anciens
  conservent la version utilisée à l’époque.

## Étapes

**Ajouter une étape** ou **Insérer depuis la bibliothèque** (depuis la
**Bibliothèque d'étapes**) ajoute des étapes. Chaque étape possède un
**Code**, un **Libellé**, une **Description** facultative et un
**Type**, par exemple « Confirmation », « Texte », « Nombre/mesure »,
« Choix », « Photo », « Fichier », « Enregistrement de sauvegarde »,
« Signature », « Saisie de matériel », « Série de mesures »,
« Approbation (double contrôle) » ou « Temps d’attente ». Également
paramétrables :

- **Obligatoire** : doit avoir un statut final avant la clôture de
  l’exécution.
- **Bloquant** : bloque les étapes suivantes jusqu’à ce qu’elle soit
  terminée.
- **Quatre yeux** : exige la contresignature d’une deuxième personne.
- **Preuve** (« Sauvegarde », « Fichier », « Photo », « Mesure »,
  « Signature » ou « Aucun ») ainsi que, facultativement, **Rôle requis**
  et **Qualification**.
- **Condition : étape** et **Condition : valeur** (si-alors) : l’étape ne
  devient pertinente que si une autre étape a une valeur donnée.

## Configuration par type d'étape

Selon son **Type**, une étape affiche d'autres champs :

- « Choix » : **Options de sélection**, séparées par des virgules. Sans
  options, l'étape accepte un texte libre pendant l'exécution.
- « Nombre/mesure » et « Série de mesures » : **Unité**, **Limite
  inférieure** et **Limite supérieure** (tolérance).
- « Temps d’attente » : **Temps d'attente fixe (secondes)** ; vide, la
  durée est saisie pendant l'exécution.
- « Enregistrement de sauvegarde » : **Périmètre**, **Stockage**, **Mode de
  vérification**, **Taille minimale (Ko)** et **Âge maximal de la
  sauvegarde (minutes)**. « Choisi pendant l'exécution » laisse
  l'indication à la personne exécutante.
- Toute autre étape : **Sauvegarde préalable requise** la bloque jusqu'à
  ce qu'une étape de sauvegarde de la même exécution soit terminée et sa
  preuve confirmée, avec un âge maximal si souhaité.
- Étapes à double contrôle : **Rôle de la seconde personne**,
  **Qualification de la seconde personne** et **La personne exécutante
  peut contresigner elle-même** (désactivé en règle générale).

**Qualification** désigne l'abréviation d'une qualification des employés,
à défaut son nom ; le contrôle porte sur sa validité à ce jour. La
**Bibliothèque d'étapes** propose les mêmes champs sous **Configuration
par type d'étape**. Les indications issues de profils sectoriels ou
d'imports que le concepteur n'affiche pas sont conservées à
l'enregistrement.

Le niveau de risque « Critique » exige au moins une étape à double
contrôle : sans elle, la version ne peut pas être publiée, et une
exécution n'est clôturée qu'avec une validation à double contrôle
effectuée.

## Attribution automatique

Un modèle est proposé lorsque **tous** les critères définis correspondent :
**Types d'ordre**, **Étiquettes**, **Clients**, **Catégories
d'équipement**, **Produits** et **Contrats SLA**. Catégorie, produit et SLA
se rapportent à l'équipement de l'ordre. Sans critère, le modèle vaut pour
tout ordre.

- Sur la page de détail de l'ordre, les modèles publiés correspondants
  apparaissent dans la carte **Procédures** sous « Procédures suggérées
  pour cet ordre : » comme bouton de démarrage.
- Si le modèle indique une catégorie d'équipement, un produit ou un
  contrat SLA, il est aussi proposé sur la fiche de l'équipement sous
  « Procédures prévues pour cet équipement : ».
- **Peut être exécutée sur des équipements bloqués** autorise le démarrage
  sur un équipement bloqué, par exemple pour les procédures de réparation
  et de contrôle.
