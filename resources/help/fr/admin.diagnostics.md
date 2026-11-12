---
title: "Diagnostic"
topic: admin.diagnostics
version: 2
keywords:
    - état du système
    - contrôle de santé
    - vérification système
    - dépannage
    - e-mail de test
    - vérifier envoi mail
    - queue
    - planificateur
    - tâches planifiées
audience:
    - admin
related:
    - admin.security
    - admin.metrics
    - admin.handbook
---

Le diagnostic fournit un rapport d'état du système avec un feu tricolore
par domaine de contrôle : **version**, **licence**, **file d'attente**,
**planificateur**, **mail**, **stockage** et **sauvegarde**, chaque
domaine recevant le statut OK, avertissement, critique ou inconnu. Le
rapport est aussi disponible en JSON pour l'exploitation automatisée. Vous
pouvez en outre déclencher un **e-mail de test** vers votre propre adresse
pour vérifier la configuration mail. Les consultations et les tests sont
consignés dans le journal d'audit ; l'affichage exige le droit de
diagnostic, le déclenchement de contrôles un droit distinct.

**Accès de supervision :** pour une supervision externe, créez un accès sous « Accès de supervision ». La supervision récupère le rapport sans connexion à l’adresse /api/monitoring/diagnostics et envoie le jeton dans l’en-tête « Authorization: Bearer … ». Le jeton n’est affiché qu’une fois et n’est conservé que sous forme d’empreinte ; la révocation le bloque immédiatement. Le rapport ne contient ni noms ni adresses, comme le titulaire de licence ou l’adresse d’expédition. Les appels sont journalisés au plus une fois par heure et par accès.
