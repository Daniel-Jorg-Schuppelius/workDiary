---
title: "Location de matériel"
topic: rental.overview
version: 2
keywords:
    - louer du matériel
    - location d'outils
    - location d'engins
    - parc locatif
    - contrat de location
    - caution
    - réservation de matériel
    - retour de matériel
    - état des lieux
    - calendrier de disponibilité
    - tarif de location
    - télématique
audience: []
modules:
    - module.rental
related:
    - claims.overview
---

Le module gère la location d'appareils et de machines sous forme de
dossiers traçables — de la réservation à la restitution, caution et
facturation comprises.

**Parc d'appareils :** un profil de location rend un actif louable
(groupe, temps tampons, accessoires, grille tarifaire par défaut).

**Disponibilité :** le calendrier affiche réservations, locations et
fenêtres de maintenance. Les doubles réservations et les appareils
bloqués sont empêchés de manière visible.

**Dossier de location :** chaque opération reçoit un numéro (VER-…) ;
la version de la grille tarifaire appliquée est figée en instantané.

**Remise & retour :** des protocoles distincts documentent état,
accessoires, compteurs, photos et signature ; le retour porte la décision
de suite (nettoyage, réparation/blocage, réclamation).

**Facturation :** les postes sont validés puis facturés localement ou
transmis au système de facturation principal. La caution est une
opération financière distincte.

**Lieu d'intervention et géorepérage :** Lorsque des positions d'appareils
arrivent par import (par exemple d'un export télématique), WorkDiary
vérifie pendant une location si l'appareil se trouve sur le lieu
d'intervention : au site de la location avec le rayon défini dans les
paramètres, sinon dans les géorepérages du client. S'il le quitte, la
personne responsable et la direction d'équipe sont informées.
