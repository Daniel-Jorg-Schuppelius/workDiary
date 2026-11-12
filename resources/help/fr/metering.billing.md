---
title: "Facturation au compteur"
topic: metering.billing
version: 1
keywords:
    - facturation à l'usage
    - facturation de la consommation
    - paiement à l'usage
    - coût par copie
    - prix à la page
    - facturation des copies
    - forfait inclus
    - prix dégressifs
    - abonnement de base
    - heures de fonctionnement
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

## Barème, statut et projet

**Barème par tranches :** la boîte de dialogue de l'accord propose des lignes
avec **à partir de la quantité** et **prix par unité**. La première tranche
commence à 0 ; les lignes vides sont ignorées. Avec un barème, le prix de
chaque tranche s'applique à la place du prix unitaire, et le calcul est
progressif sur la consommation après déduction de la franchise — une unité de
plus ne rend jamais la facture moins chère. La liste affiche alors « Barème »
à la place du prix unitaire.

**Statut :** *Actif* est facturé ; le traitement ignore *En pause* et
*Terminé*, et la facturation immédiate n'est proposée que pour les accords
actifs. Après une pause, le traitement rattrape à partir de la date de
**prochaine facturation** — si la période de pause ne doit pas être facturée,
avancez la date avant de réactiver.

**Projet :** le projet choisi est rattaché aux brouillons de facture du
traitement.
