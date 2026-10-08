---
title: "Concepteur de procédures"
topic: procedures.designer
version: 3
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

## Attribution automatique

Via les **Types d'ordre** et les **Étiquettes**, vous définissez pour
quels ordres le modèle est proposé automatiquement. Sur la page de détail
de l’ordre, les modèles publiés correspondants apparaissent dans la carte
**Procédures** sous « Procédures suggérées pour cet ordre : » comme
bouton de démarrage.
