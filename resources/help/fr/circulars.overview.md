---
title: "Circulaires clients"
topic: circulars.overview
version: 1
audience: []
related:
    - contacts.manage
    - invoices.manage
---

Les circulaires sont des communications commerciales adressées à un ensemble
filtré de clients — révision tarifaire, fenêtre de maintenance, horaires
d’astreinte modifiés. Pas une newsletter : ni pixel espion, ni liens
réécrits.

**Destinataires :** L’ensemble est déterminé par les filtres clients
existants (recherche, ville, début du code postal, uniquement les clients
avec un projet actif). Avant l’envoi, le nombre de destinataires s’affiche
avec la liste complète — un courriel à tous les clients ne doit pas partir
par mégarde.

**Refus des envois groupés :** Les clients pour lesquels l’option *Pas
d’envois groupés* est active sont ignorés. Les circulaires marquées comme
*communication obligatoire* leur parviennent malgré tout ; cela est réservé
aux informations légalement requises.

**Preuve :** Chaque destinataire génère une ligne — y compris les ignorés,
avec le motif (une adresse e-mail manquante, par exemple). La communication
est en outre classée comme note dans le dossier client et, sur demande,
apparaît dans le portail client.

**Espaces réservés :** `:firma`, `:kunde` et `:ansprechpartner` sont
remplacés pour chaque destinataire. Si une valeur manque, l’emplacement
reste vide — rien n’est inventé.
