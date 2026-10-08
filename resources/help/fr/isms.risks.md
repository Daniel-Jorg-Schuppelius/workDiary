---
title: "Registre des risques"
topic: isms.risks
version: 3
keywords:
    - analyse des risques
    - évaluation des risques
    - matrice des risques
    - ajouter un risque
    - cartographie des risques
    - traitement des risques
    - risque résiduel
    - acceptation du risque
    - probabilité
    - risque brut
    - risque net
audience: []
modules:
    - module.isms
related:
    - isms.controls
    - isms.overview
    - isms.audits
    - glossary.core
---

Dans le **Registre des risques**, vous saisissez, évaluez (5×5) et
traitez les risques de sécurité de l’information par périmètre. Vous le
trouvez sous **SMSI** → **Pilotage** → **Registre des risques**.

Déroulement type :

1. **Ajouter un risque** : **Titre**, **Catégorie** (« Organisationnel »,
   « Technique », « Physique », « Personnel », « Fournisseur »),
   **Référence (système/processus/site)**, **Menace** (la menace ou
   vulnérabilité sous-jacente), **Responsable** et **Revue prévue**.
2. **Évaluer** : **Probabilité** (1–5) × impact (1–5) donne le **Score**
   (1–25). Feux de la matrice des risques : Faible (score ≤ 6), Moyen
   (score 7–12), Élevé (score > 12).
3. Choisir un **Traitement** : « Éviter », « Réduire », « Transférer » ou
   « Accepter » – et affecter des mesures sous **Mesures liées**.
4. Faire évoluer le **Statut** via **Changer le statut** le long de la
   chaîne : « Identifié » → « Analysé » → « Traité »/« Accepté » →
   « Clôturé ». Un risque clôturé peut être remis à « Analysé ».

Historique des évaluations :

- **Saisir une évaluation** crée une évaluation ; le **Type
  d'évaluation** est « Brut », « Net » ou « Cible ». Chaque évaluation a
  une **Justification**, facultativement une date **Valable jusqu’au**
  (date d’expiration ou de revue) et passe de « Brouillon » à
  « Approuvée » (**Approuver**).
- **Les évaluations approuvées sont immuables.**
- La dernière évaluation **nette** approuvée détermine les valeurs
  affichées sur le risque. Si vous modifiez la probabilité ou l’impact
  directement sur le risque, une évaluation directe approuvée est créée
  automatiquement – l’historique reste complet.

Règle importante : le passage à **« Accepté »** (acceptation du risque
résiduel) exige une évaluation nette approuvée **avec une date
« Valable jusqu’au »**.

Autorisations : la consultation requiert le droit **Voir les registres
SMSI (risques, mesures, DdA)** ; les modifications requièrent **Gérer le
SMSI (risques, mesures, import du catalogue)**.

Étapes suivantes : lorsque la date **Valable jusqu’au** de la dernière
évaluation nette approuvée d’un risque ouvert approche ou est dépassée,
la personne responsable est notifiée ; les **Règles de notification**
permettent en plus une escalade. Le champ **Revue prévue** du risque
sert à la planification et au tri, mais ne déclenche lui-même aucune
notification.
