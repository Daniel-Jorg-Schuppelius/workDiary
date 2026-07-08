---
title: "Automatisations"
topic: admin.automations
version: 1
audience:
    - admin
related:
    - admin.handbook
    - admin.notification-rules
    - admin.webhooks
---

Les automatisations sont des règles suivant le schéma **événement →
condition → action** : lorsqu'un événement déclencheur survient et que les
conditions correspondent, les actions associées sont exécutées, strictement
dans le périmètre de l'organisation. La vue d'ensemble liste les règles par
priorité et par nom ; vous pouvez **créer** une règle (conditions et
actions saisies en JSON dans le MVP actuel), l'**activer/désactiver**,
consulter ses dernières exécutions dans la vue détaillée ou la
**supprimer**. La **priorité** détermine l'ordre lorsque plusieurs règles
correspondent (valeur la plus basse d'abord) et tout JSON invalide est
rejeté. Pour de simples notifications, préférez les **règles de
notification** ; pour les systèmes externes, voir les **webhooks**.
