---
title: "Autofacturation"
topic: invoices.self-billing
version: 1
keywords:
    - autofacturation
    - facture émise par le client
    - avoir au fournisseur
    - règlement des sous-traitants
    - code type 389
audience:
    - admin
    - geschaeftsfuehrung
    - buchhaltung
modules:
    - module.vertrieb
related:
    - invoices.manage
    - finance.incoming-invoices
---
Avec l’**autofacturation**, votre organisation, en tant que bénéficiaire de la prestation, émet elle-même la facture pour la prestation du fournisseur (article 14, paragraphe 2, phrase 2 de la loi allemande sur la TVA) — par exemple pour des sous-traitants, des commissions ou des achats. Le document s’appelle **Gutschrift** et reste distinct de l’avoir commercial sur une de vos propres factures.

## Conditions

- La date de l’**accord d’autofacturation** est saisie sur le fournisseur (modifier le fournisseur). Sans accord, le fournisseur n’est pas proposé.
- Le fournisseur dispose d’un **numéro de TVA** ou d’un **numéro fiscal** et d’une adresse complète.
- Le document comporte au moins une ligne et une **période de prestation**.

S’il manque quelque chose, la page de détail indique les points ouverts ; **Émettre** n’apparaît que lorsque tout est réuni.

## Déroulement

1. **Créer l’autofacture** : choisir le fournisseur et la période de prestation.
2. **Ajouter une ligne** : description, quantité, unité, prix unitaire et taux de TVA. Le calcul est le même que pour les factures et les devis.
3. **Émettre** : attribue le numéro de sa propre série (par défaut `GS-année-numéro`, modifiable dans les séries de numérotation) et fige montants et contenu.

L’émission crée la facture électronique (XRechnung, code type 389), qui arrive dans les **factures entrantes**, déjà affectée au fournisseur. Elle passe ensuite en comptabilité comme toute facture entrante — vers DATEV ou le logiciel connecté. Le PDF porte la mention « Gutschrift », désigne le fournisseur comme prestataire et renvoie à l’accord.

## Correction et annulation

Une autofacture émise ne peut plus être modifiée. **Annuler** n’est possible que tant que la comptabilité ne l’a pas reprise ; l’entrée correspondante est alors rejetée. Ensuite, la correction passe par une nouvelle autofacture aux montants négatifs.

Droits : consulter avec « Lister les factures », créer avec « Créer une facture », émettre et annuler avec « Émettre une facture ».
