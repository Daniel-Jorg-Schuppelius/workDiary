---
title: "Formats de numérotation"
topic: admin.number-formats
version: 2
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

Les numéros de facture, d'avoir et d'annulation se règlent dans les
paramètres du plugin Lexoffice.
