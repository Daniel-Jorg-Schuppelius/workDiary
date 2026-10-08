---
title: "Règles de notification"
topic: admin.notification-rules
version: 3
keywords:
    - escalade
    - configurer les notifications
    - notification e-mail
    - notification push
    - destinataires
    - rappels
    - alertes de retard
    - suivi des échéances
    - canaux de notification
audience:
    - admin
    - geschaeftsfuehrung
    - teamleitung
related:
    - admin.handbook
    - communication.notes
    - glossary.core
---

Les règles de notification définissent, pour chaque type d'événement,
**qui** est informé et sur **quels canaux** – et à quel moment une
escalade intervient. La liste affiche pour chaque **Événement** les
colonnes **Actif**, **Canaux**, **Destinataires** et **Escalade** ; les
événements sans règle propre portent la mention **Standard (pas encore
personnalisé)**.

Déroulement type :

1. Choisir **Modifier** pour l'événement (par ex. point ouvert
   assigné/bientôt échu/en retard, action de suivi échue, document
   arrivant à expiration, demande de correction, validation mensuelle
   soumise, certificat ISMS arrivant à expiration, mesure corrective en
   retard, revue des risques échue). La boîte de dialogue **Modifier la
   règle de notification** s'ouvre.
2. Sous **Actif**, activer **Notifications activées pour cet
   événement** et choisir les **Canaux** : **Dans l’application**,
   **E-mail**, **Push**, **Microsoft Teams**, **Mattermost** ou
   **Calendrier**. Pour les événements critiques (par ex. **Alerte de
   crise**, **Astreinte attribuée**, **Événement de sécurité
   critique**), **SMS** est également disponible.
3. Définir les **Destinataires** : **Notifier la personne concernée (p.
   ex. assignée ou demandeuse)**, **Rôles destinataires** (par ex.
   responsable d'équipe) et **Destinataires fixes supplémentaires**.
4. Pour les événements de retard, configurer en option l'**Escalade** :
   activer **Escalade activée** ; après **Escalader après (heures)**
   (1–720), le **Rôle d’escalade** est notifié en plus. **Niveau
   d’escalade 2** et **Niveau d’escalade 3** notifient chacun, après des
   heures supplémentaires, leurs propres rôles et destinataires fixes.

Bon à savoir :

- Sans règle propre, c'est la valeur par défaut affichée de l'événement
  qui s'applique (canaux, indicateur de personne concernée, rôles) –
  vous ne devez configurer que les cas qui s'en écartent.
- **Microsoft Teams** et **Mattermost** publient dans le canal de
  discussion configuré de l'organisation ; **Calendrier** inscrit les
  événements datés dans les calendriers connectés de l'organisation
  (CalDAV/Microsoft 365/Google). Les **SMS** ne parviennent qu'aux
  personnes dont le numéro de mobile est confirmé et sont facturés à
  l'unité.
- L'escalade n'existe que pour les événements de retard ou
  d'expiration.
- Certains événements sont déclenchés immédiatement (par ex.
  l'assignation), d'autres sont détectés par le scanner d'échéances
  (par ex. « bientôt échu »).

Autorisation : les personnes disposant du droit **Voir les règles de
notification** voient la liste ; seules celles qui disposent de
**Modifier les règles de notification** peuvent la modifier.
