---
title: "Circulaires clients"
topic: circulars.overview
version: 1
keywords:
    - mailing
    - envoi groupé
    - e-mail groupé
    - newsletter
    - information clients
    - annonce
    - publipostage
    - prévenir les clients
    - désinscription
    - communication obligatoire
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

## Modèles et pièce jointe

À la création d’une circulaire, un **fichier** peut être joint à chaque e-mail. **Enregistrer comme modèle** (menu de la circulaire) crée une copie avec texte, filtres, options et pièces jointes. Les modèles figurent au-dessus de la liste ; **Créer à partir du modèle** ouvre la boîte de dialogue préremplie et reprend les pièces jointes du modèle. Un modèle n’est jamais envoyé lui-même.
