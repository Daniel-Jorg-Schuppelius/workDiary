---
title: "Diagnostic"
topic: admin.diagnostics
version: 1
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
