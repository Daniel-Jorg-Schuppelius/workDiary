---
title: "Factures et pièces"
topic: invoices.manage
version: 2
audience: []
related:
    - contacts.manage
    - projects.manage
    - finance.transfers
    - travel-expenses.manage
---

La vue des factures gère les factures locales et les pièces liées ; le
canal maître dépend de l'organisation et de l'intégration de facturation
utilisée. Avant de créer une facture, vérifiez le client, la période de
prestation, le projet, les positions, la TVA et l'adresse du destinataire.
Les brouillons peuvent être complétés, mais les pièces envoyées,
comptabilisées ou transmises en externe ne doivent jamais être modifiées
silencieusement : en cas d'erreur, utilisez le processus d'annulation ou
de correction prévu au lieu d'écraser numéros ou montants. Le PDF, l'envoi
et la synchronisation externe reflètent le même état documenté.

Depuis MVP-462, le dialogue de création affiche un **aperçu** des
positions générées (nombre, durée aux formats horloge et décimal,
montant, avertissement de saisies tardives) dès que le client et la
période sont choisis. Des saisies individuelles peuvent y être
**exclues** du cycle par case à cocher — elles restent ouvertes et
réapparaissent au cycle suivant. Sur la facture, les **saisies de
temps sources** de chaque position sont dépliables ; les quantités
d'heures apparaissent aussi au format horloge (p. ex. 1,50 h = 1:30 h).

**Lettre de relance :** la relance génère un PDF de lettre de relance
dédié (niveau 1 = rappel de paiement) avec récapitulatif de la créance,
frais de relance facultatifs et échéance de paiement ; l'e-mail
contient la lettre et la facture originale en pièces jointes. Aucune
nouvelle pièce comptable n'est créée.
