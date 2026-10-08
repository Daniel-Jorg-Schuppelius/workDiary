---
title: "Rapprochement des paiements"
topic: finance.reconciliation
version: 2
keywords:
    - rapprochement bancaire
    - importer un relevé
    - lettrage
    - encaissements
    - facture payée
    - opérations bancaires
    - CAMT
    - MT940
    - escompte
    - paiement partiel
    - référence RF
audience: []
modules:
    - module.finance
related:
    - finance.transfers
    - roles.buchhaltung
    - glossary.core
---

Le **rapprochement des paiements** importe des relevés bancaires
(**CAMT.053** de préférence, **MT940** en secours), normalise les
opérations dans une zone de contrôle et propose des affectations aux
factures ouvertes ou frais approuvés. L'import seul ne modifie aucune
pièce : seule la **confirmation** marque une facture comme payée ou un
frais comme remboursé. Les doublons de fichiers et d'opérations sont
rejetés, l'escompte (3 % par défaut) et les écarts d'arrondi jusqu'à
2 centimes sont tolérés, et une affectation confirmée reste réversible —
l'opération bancaire elle-même n'est jamais modifiée. Les données
bancaires nominatives sont chiffrées et chaque action est journalisée de
façon inaltérable ; l'import et la confirmation requièrent le rôle
*Comptabilité*.

## Référence de paiement RF

Lorsque la référence de paiement RF est activée dans les paramètres de l'organisation, chaque facture porte une
référence créancier RF (ISO 11649) issue de son numéro — dans les
informations de paiement et comme référence structurée dans le GiroCode.
Si le client paie avec cette référence, le rapprochement reconnaît la
facture grâce à elle, même écrite par groupes de quatre.
