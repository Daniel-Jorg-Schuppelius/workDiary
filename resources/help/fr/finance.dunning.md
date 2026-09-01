---
title: "Relances"
topic: finance.dunning
version: 1
audience:
    - admin
    - buchhaltung
modules:
    - module.finance
related:
    - invoices.manage
    - finance.reconciliation
    - finance.open-times
---

Les relances suivent les **factures impayées** sur trois niveaux au maximum.
Deux voies : la **relance individuelle** depuis la facture et la **campagne
de relance**, qui reprend d’un coup tous les postes échus.

## Ce qu’une relance est — et n’est pas

Une relance est une **lettre, pas une pièce comptable**. Elle ne crée
**aucune écriture** ni nouvelle facture ; elle modifie seulement le statut de
relance de la facture existante. C’est important pour le rapprochement : le
montant ouvert reste le même, même après le troisième niveau.

**Les intérêts de retard sont indiqués, non comptabilisés.** Si un taux
supérieur à zéro est configuré, le système le calcule au jour près depuis
l’échéance (base 365 jours) et l’indique dans la lettre. Savoir s’il sera
effectivement réclamé relève de la comptabilité — c’est pourquoi aucune
créance n’en découle.

## Niveaux et délai de grâce

Les jours de grâce, les frais et le délai de paiement par niveau proviennent
des réglages de l’organisation. Le délai de grâce évite qu’une facture soit
relancée le lendemain de son échéance alors que le virement est encore en
route.

## Avant de relancer

Vérifiez le **rapprochement bancaire**. La relance évitable la plus fréquente
part vers quelqu’un qui a payé depuis longtemps — l’encaissement n’avait
simplement pas encore été affecté.
