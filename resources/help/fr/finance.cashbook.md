---
title: "Livre de caisse"
topic: finance.cashbook
version: 1
audience:
    - admin
related:
    - invoices.manage
---

Le **livre de caisse** documente les recettes et dépenses en espèces de façon
conforme GoBD (MVP-414). workDiary n'est pas un système de caisse (pas de POS,
pas d'obligation TSE).

- **Immuable** : les écritures reçoivent un numéro séquentiel et une chaîne de
  hachage ; modifier et supprimer sont techniquement impossibles.
- **Contre-écriture au lieu de suppression** : les corrections sont des
  contre-écritures avec motif obligatoire ; l'original est conservé.
- **Clôture journalière** : comptage avec attendu/compté/écart ; ensuite toutes
  les écritures jusqu'à la date de clôture sont verrouillées.
- **Paiement en espèces** : une recette peut référencer une facture — la
  couverture totale la marque comme payée.
- Le livre de caisse fait partie de l'**export GoBD Z3** (kassenbuch.csv).
