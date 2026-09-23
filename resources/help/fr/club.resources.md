---
title: "Installations sportives et ressources"
topic: club.resources
version: 1
audience: []
modules:
    - module.club
related:
    - club.events
    - club.matches
---

Les installations et ressources représentent salles, surfaces partielles
(moitié, tiers), tables, courts, couloirs et stands, bateaux et engins sous
forme d'**arborescence** : une surface partielle dépend de sa salle, une table
de la salle ou d'une moitié. Le contrôle de conflits est commun — réserver
toute la salle bloque toutes les surfaces et tables en dessous, différentes
surfaces libres sont utilisables en parallèle. Il n'y a pas de calendriers
isolés par sport.

**Salles et actifs :** Une ressource peut être liée à une salle existante ;
les rendez-vous avec cette salle et les réservations de la ressource (surfaces
comprises) partagent alors le même calendrier. Bateaux et engins peuvent être
liés à un actif : les blocages de l'actif (maintenance, défaut, contrôle)
empêchent la réservation — pas de seconde disponibilité pour le même objet ni
d'obligation de créer un contrat de location.

**Unités et marges :** Une ressource à plusieurs unités (p. ex. quatre
couloirs) peut être réservée partiellement ; la quantité par rendez-vous est
vérifiée contre les unités. Les marges de montage et démontage étendent la
fenêtre réservée.

**Réserver :** Les ressources se réservent sur le rendez-vous ou la journée —
avec quantité, fenêtre propre facultative, marges et personne utilisatrice. La
réservation vérifie capacité, salle/surfaces, calendrier des salles,
fermetures et blocages d'actifs en une transaction ; deux réservations
simultanées ne sont jamais toutes deux confirmées. Les lieux de match à
l'extérieur sont des indications de lieu et ne réservent rien.

**Déplacer et annuler :** Lorsqu'un rendez-vous est déplacé, ses réservations
suivent — en cas de conflit tout reste en l'état (ancienne heure, ancienne
réservation). Une annulation libère toutes les réservations.

**Fermetures :** Météo, maintenance ou occupation externe sont saisies comme
fermeture avec motif. Les réservations existantes sont **signalées** pour
replanification, pas supprimées ; les nouvelles réservations sur la période
sont bloquées. Lever la fermeture libère les réservations signalées.

**Habilitations :** Bateaux, engins et ressources similaires peuvent exiger une
habilitation d'initiation ou d'aptitude par membre, limitable dans le temps et
accordée par l'encadrement. Sans habilitation valide, la personne ne peut pas
être inscrite comme utilisatrice ; sur le rendez-vous, les participants
inscrits sans habilitation sont affichés.
