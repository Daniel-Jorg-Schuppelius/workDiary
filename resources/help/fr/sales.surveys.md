---
title: "Enquêtes"
topic: sales.surveys
version: 1
audience: []
modules:
    - module.vertrieb
related:
    - contacts.manage
---

Un **moteur d’enquêtes** sobre pour NPS et questionnaires libres — pas
d’automatisation marketing. Types de questions : **NPS (0–10)**, échelle
(1–5), choix, texte libre. La participation passe par un **lien unique**
(valable 30 jours), sans connexion au portail.

## Trois règles obligatoires

- **Protection anti-lassitude :** au plus une invitation par adresse e-mail
  en 90 jours — tous questionnaires confondus. Le déclencheur automatique
  saute en silence, l’envoi manuel est refusé avec un message.
- **Opt-out par client :** qui s’est opposé n’est plus invité.
- **L’anonymat est une propriété de stockage :** pour les questionnaires
  anonymes, la réponse ne porte aucun lien d’invitation et l’invitation aucun
  horodatage de réponse — une jointure de ré-identification n’a pas de
  champs. C’est pourquoi le réglage ne peut plus changer après la première
  invitation.

## Déclencheurs

Manuellement par client — ou automatiquement **après clôture de ticket**
(activable sur le questionnaire). Un échec d’invitation n’empêche jamais le
changement de statut du ticket.

## Évaluation

**Score NPS** = %promoteurs (9–10) − %détracteurs (0–6). Sans réponses, pas
de score — pas de valeur signifie « rien à calculer », pas zéro. La CSAT des
tickets (note au portail) reste indépendante.
