---
title: "Enquêtes"
topic: sales.surveys
version: 2
keywords:
    - satisfaction client
    - enquête de satisfaction
    - sondage
    - questionnaire
    - NPS
    - Net Promoter Score
    - recueillir des avis
    - retour client
    - enquête anonyme
    - recommandation
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

## Enregistrer une opposition

Vous définissez l'opt-out sur le client : dans la boîte de dialogue du client, sous **Portail et divers**, cochez **Pas d'enquêtes**. La personne invitée peut aussi s'opposer elle-même – chaque invitation adressée à un client et la page de l'enquête portent le lien **Se désabonner des enquêtes**. Il mène, sans connexion, à une demande de confirmation ; après confirmation, la case est cochée sur le client et l'opération est consignée dans l'historique des modifications du client. Le lien fonctionne aussi lorsque l'enquête a déjà reçu une réponse ou a expiré. Les invitations sans lien avec un client ne comportent pas de lien de désinscription. Dans l'export et l'import des clients, la valeur figure dans la colonne `survey_opt_out`.

## Modifier un questionnaire

**Modifier** sur la page du questionnaire change le titre et le texte
d'introduction. L'anonymat ne peut être changé que jusqu'à la première
invitation — ensuite il reste tel qu'il a été promis aux participants.
