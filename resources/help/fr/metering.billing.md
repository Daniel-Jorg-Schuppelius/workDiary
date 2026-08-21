---
title: "Facturation au compteur"
topic: metering.billing
version: 1
audience: []
related:
    - invoices.manage
    - assets.fleet
    - contacts.manage
---

La facturation au compteur traite les contrats liés à la consommation —
copies sur un multifonction, heures de fonctionnement, kWh.

**Convention :** Chaque compteur définit le client facturé, la périodicité,
l’abonnement, le quota inclus et le prix unitaire. Des tarifs par paliers
sont possibles ; le palier s’applique à la consommation de la période.

**Consommation :** On facture l’écart entre le dernier relevé *avant* la
période et le dernier relevé *de* la période — et non la somme des
consommations enregistrées, qui inclurait la période antérieure. Si un
relevé manque, la période n’est pas facturée plutôt qu’estimée.

**Exécution :** L’exécution mensuelle crée des brouillons de facture, pas
des factures envoyées — la validation reste humaine. Chaque période n’est
facturée qu’une fois ; une seconde exécution ne crée pas de doublon.
