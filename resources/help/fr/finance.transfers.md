---
title: "Transfert de facturation"
topic: finance.transfers
version: 1
audience: []
modules:
    - module.finance
related:
    - exports.payroll
    - admin.surcharge-rules
    - roles.buchhaltung
    - glossary.core
---

Le transfert de facturation transmet les **temps** et **matériaux**
facturables au système de facturation maître : la facture est créée dans
le programme externe (DATEV ou Lexoffice), WorkDiary ne fournit que des
positions vérifiées avec justificatif de transfert. Créez un transfert
(« Brouillon ») en choisissant le canal — « Prestations/Temps » ou
« Produits/Matériel » — et la destination (Lexoffice, DATEV ou export
CSV), vérifiez les positions, **confirmez** puis **exécutez**. Le statut
**« Transféré »** est définitif et verrouille les positions ; les
corrections passent par des transferts d'annulation ou de différence,
jamais par une remise à zéro silencieuse. Les transferts de temps et de
matériel sont protégés par des droits distincts.
