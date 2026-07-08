---
title: "Rapprochement des paiements"
topic: finance.reconciliation
version: 1
audience: []
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
