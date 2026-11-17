---
title: "Vérification interne des commandes"
topic: diary-entries.review
version: 1
keywords:
    - vérification
    - principe des quatre yeux
    - validation
    - question
    - soumettre à vérification
    - vérification obligatoire
    - période
audience: []
related:
    - diary-entries.edit
    - diary-entries.follow-up
    - carry-forward.notes
---

Les commandes d’un type d’entrée avec **vérification obligatoire** passent par
une vérification interne avant la clôture : la personne qui la traite soumet la
commande, une seconde personne la valide ou pose une question. Personne ne
valide son propre travail.

## Déroulement

1. **Soumettre à vérification** – cette étape remplace « Clôturer » dans la
   commande. La commande passe **En vérification**.
2. La personne vérificatrice **valide** ou **pose une question** (texte
   obligatoire). La question figure dans l’historique ; la commande passe à
   **Question** et retourne à la personne qui la traite.
3. Après une question, **soumettre à nouveau**. Tant que personne n’a décidé,
   une soumission peut aussi être **retirée**.
4. Après la validation (**Validée**), la personne qui traite la commande la
   clôture – par exemple après la transmission. Lorsque la validation est
   elle-même la clôture, la personne vérificatrice utilise **Valider et
   clôturer**.

## Qui vérifie

Peut vérifier toute personne ayant le droit **Vérifier des commandes** ou
désignée dans la commande comme **personne vérificatrice** – jamais celle qui
a soumis. Si une personne est désignée, la soumission n’apparaît que chez
elle ; sinon chez toutes les personnes ayant le droit.

Une personne seulement désignée comme personne vérificatrice, sans droit de
validation, trouve la soumission dans la tuile **À vérifier** et la valide via
la file de vérification ; la boîte des validations lui reste alors fermée.

## Où apparaissent les soumissions

- **À vérifier** dans le registre des commandes : la file de vérification, par
  échéance, puis par date de soumission.
- Dans la **boîte des validations** et la tuile **À vérifier** du tableau de
  bord. Vos propres soumissions n’y apparaissent jamais.
- Dans la **file de travail**, vos commandes figurent dans l’ordre de
  traitement : question, validée, en cours, acceptée.

## Courses, rondes, tournées et Kanban

Si le travail se termine en dehors de la commande – course terminée, ronde
achevée, arrêt de tournée effectué –, l’application soumet à vérification une
commande qui l’exige au lieu de la terminer ; l’étape figure dans l’historique.
Sur le tableau Kanban, soumettre, valider et poser une question passent par la
commande : déplacer une carte vers les colonnes de vérification ou vers
**Terminé** vous l’indique.

## Vérification obligatoire et période

- La vérification obligatoire vient du type d’entrée. Avec le droit **Lever
  l’obligation de vérification d’une commande**, elle peut être levée ou
  activée pour une commande avant la soumission ; cela figure avec le motif
  dans l’historique et le journal d’audit.
- Si le type d’entrée exige une **période** (année, semestre, trimestre,
  mois), elle est obligatoire à la création. Format : `2026`, `2026-H1`,
  `2026-Q1`, `2026-03`.
