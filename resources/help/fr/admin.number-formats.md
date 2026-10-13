---
title: "Formats de numérotation"
topic: admin.number-formats
version: 5
keywords:
    - plage de numéros
    - numéro de facture
    - numéro client
    - numéro article
    - numérotation séquentielle
    - préfixe
    - numéro de départ
    - compteur annuel
    - réinitialiser compteur
    - numérotation par la comptabilité
    - numéro client Lexoffice
    - numéro provisoire
    - séparateur
    - voie de facturation
    - numéro de devis du logiciel
    - numéro de facture du logiciel
    - facture vers Lexoffice
    - facture vers sevDesk
    - facture vers easybill
audience:
    - admin
    - teamleitung
related:
    - invoices.manage
    - admin.classifications
    - glossary.core
---

Les formats de numérotation définissent les séries de numéros
consécutives par organisation, par exemple pour tickets, actifs,
articles, ordres de fabrication, numéros de série, commandes, clients,
fournisseurs, factures et avoirs. Pour chaque format, vous configurez
le préfixe et son séparateur, l'inclusion optionnelle de l'année, le
nombre de chiffres de remplissage, la valeur de départ et la remise à
zéro annuelle du compteur ; un aperçu montre le prochain numéro
généré. Modifiez format ou valeur de départ avec prudence : les
numéros déjà attribués sont conservés.

## Enregistrer et séparateurs pour toutes les plages

Vous enregistrez toutes les modifications de la page ensemble avec
**Enregistrer** dans l'en-tête. Les plages que vous ne modifiez pas restent
sur la valeur standard (« Default »). Si une saisie est invalide, le message
nomme la plage et vos saisies sont conservées.

Au-dessus du tableau se trouvent les champs **Séparateur après le préfixe**
et **Séparateur après l'année** pour toutes les plages. **Appliquer à toutes
les plages** définit les deux séparateurs dans chaque plage attribuée par
WorkDiary et enregistre la page. Un champ vide signifie : aucun séparateur.
Les numéros déjà attribués restent inchangés.

## Qui attribue les numéros de client et de fournisseur ?

Dans la colonne **Attribué par**, vous choisissez pour **Client** et
**Fournisseur** si WorkDiary attribue le numéro ou un logiciel de
comptabilité connecté. Sont proposés les logiciels actifs capables
d'attribuer des numéros : Lexoffice (client et fournisseur), sevDesk et
easybill (client). orgaMAX n'attribue pas de numéros pour WorkDiary.

Si un logiciel attribue les numéros :

- Un nouveau client reçoit d'abord un numéro provisoire (`ENTWURF-…`). Créé
  dans le formulaire, il est aussitôt transféré au logiciel et reçoit son
  numéro. Un traitement horaire rattrape les clients issus d'imports et les
  transferts échoués.
- Le champ **Numéro client** est verrouillé pour ces clients.
- La colonne **Prochain numéro** indique le dernier numéro repris, combien
  de contacts attendent encore leur numéro et, pour sevDesk, le prochain
  numéro du logiciel.
- Si le numéro du logiciel appartient déjà à un autre client, l'ancien
  numéro reste et la **boîte de rapprochement** affiche le cas. Après
  renumérotation de l'autre client, la synchronisation suivante reprend le
  numéro.
- Les clients existants conservent leur numéro.
- Si la comptabilité gère les données de base, WorkDiary ne transfère rien ;
  le numéro arrive dès que la synchronisation des contacts relie le client.

Si WorkDiary attribue les numéros, chaque transfert vérifie si le numéro
appartient déjà à un autre contact dans le logiciel et s'arrête alors avec
un message au lieu d'écraser ce contact.

## Facture, avoir et annulation

Dans la colonne **Attribué par**, vous choisissez aussi pour **Facture**,
**Avoir** et **Annulation** qui attribue le numéro : WorkDiary ou un logiciel
connecté – Lexoffice et easybill pour les trois, sevDesk pour la facture et
l'annulation. Si un logiciel attribue, le brouillon porte un numéro
provisoire ; **Émettre** transmet le document et reprend le numéro du
logiciel, l'avoir et l'annulation avec référence à la facture transmise.
Créez alors les factures d'acompte et de solde dans le logiciel.

Si la **voie de facturation** des paramètres de l'organisation est réglée sur
un logiciel de comptabilité, celui-ci tient entièrement les factures ; la
colonne **Attribué par** le nomme. Si certains clients facturent par une autre
voie, leur nombre figure en dessous.

## Plages verrouillées

Si un logiciel attribue les numéros d'une plage, il en définit aussi le
format. Préfixe, séparateurs, année, nombre de chiffres, remise à zéro
annuelle et début sont alors verrouillés ; les valeurs enregistrées
s'appliquent de nouveau dès que WorkDiary attribue. Si un client est facturé
dans WorkDiary malgré une voie de facturation externe, facture, avoir et
annulation restent ouverts, car ses factures reçoivent des numéros locaux.
La sélection **Attribué par** reste modifiable ;
les champs se libèrent après l'enregistrement.

## Devis, confirmation de commande et bon de livraison

Pour **Devis**, **Confirmation de commande** et **Bon de livraison**,
choisissez Lexoffice ou easybill dans la colonne **Attribué par**. Le numéro
naît alors à l'envoi du devis, à la confirmation de la commande et à la
livraison du bon, lorsque WorkDiary transmet le document au logiciel.
