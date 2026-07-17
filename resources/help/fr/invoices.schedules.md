---
title: "Plans de facturation"
topic: invoices.schedules
version: 1
audience:
    - admin
related:
    - invoices.manage
---

Les **plans de facturation** créent des **brouillons** de factures
récurrentes (MVP-415) — l'émission et l'envoi restent des étapes manuelles.

- **Rythme** : semaine/mois/trimestre/an × nombre ; la période facturée est la
  période écoulée ou en cours (paiement anticipé).
- **Modèle de positions** : les variables `{zeitraum_von}` et `{zeitraum_bis}`
  sont remplacées à chaque exécution ; les remises sont reprises.
- **Contrat** : liaison facultative — si le contrat se termine, le plan aussi.
- **Idempotent** : au plus un brouillon par plan et par période ; les
  exécutions manquées sont rattrapées.
- **Souveraineté de facturation** : si un système externe gère les factures du
  client, le plan reste visiblement bloqué.
