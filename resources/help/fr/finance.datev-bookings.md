---
title: "Lots d'écritures DATEV"
topic: finance.datev-bookings
version: 1
audience: []
related:
    - finance.transfers
    - finance.reconciliation
    - roles.buchhaltung
    - glossary.core
---

Le **lot d'écritures DATEV** transmet les factures émises, avoirs et —
en option — les frais approuvés d'une période close sous forme de fichier
DATEV vérifiable (format V700) au cabinet comptable. WorkDiary ne fait pas
de comptabilité : si un logiciel de facturation externe est maître, ces
factures sont automatiquement exclues. Configurez d'abord la
**configuration comptable** (numéros de conseiller/dossier, plan comptable
SKR03/SKR04, comptes de produits, clés de TVA), puis créez un brouillon
pour la période, vérifiez l'aperçu des écritures et finalisez : le lot
devient **immuable**, une somme de contrôle SHA-256 est enregistrée et le
fichier CSV peut être téléchargé. Réservé au rôle *Comptabilité* et aux
administrateurs.
