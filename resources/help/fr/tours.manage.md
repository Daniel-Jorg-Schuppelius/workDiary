---
title: "Planifier des tournées"
topic: tours.manage
version: 3
keywords:
    - planification d'itinéraires
    - proposition de tournée
    - optimiser l'itinéraire
    - itinéraire
    - arrêts
    - ordre des passages
    - dispatching
    - vue carte
    - tournée de livraison
    - planning des interventions
    - visites clients
    - trajets vers le carnet de route
audience: []
modules:
    - module.planung
related:
    - contacts.manage
    - planning.shifts
    - travel-expenses.manage
---

Les tournées regroupent arrêts et interventions dans un ordre planifié ; la
vue carte aide au contrôle et à l'optimisation de l'itinéraire.

**Coordonnées :** les interventions ont besoin de coordonnées pour être
planifiables. Elles sont déterminées à partir de l'adresse de l'intervention
en quittant les champs d'adresse ; sans adresse propre, l'adresse du client
s'applique. Les arrêts sans coordonnées restent dans la tournée, en fin de
liste, et sont signalés comme tels.

**Proposition plutôt qu'automatisme :** « Calculer une proposition »
détermine un ordre avec les heures d'arrivée et de départ de chaque arrêt.
Sont pris en compte le temps de conduite, les créneaux horaires des
interventions, la plage de travail issue du poste ou de la disponibilité,
les pauses obligatoires et les qualifications. La proposition affiche la
comparaison avec l'état actuel, les temps d'attente, les pauses et les
interventions non planifiables avec leur motif. Seul « Adopter » écrit
l'ordre et les horaires dans la tournée ; « Rejeter » exige un motif. Chaque
proposition reste traçable dans l'historique de la tournée.

**Heure et point de départ :** l'heure de départ de la tournée et le point
de départ (domicile, site ou adresse) déterminent les heures d'arrivée.

**Trajets vers le carnet de route :** une fois la tournée démarrée, ses
tronçons peuvent être repris comme trajets. Les kilomètres routiers
proviennent de l'itinéraire calculé ; sans itinéraire, la distance à vol
d'oiseau s'applique et le trajet est marqué en conséquence. Pour un véhicule
en mode carnet de route, le relevé du compteur est demandé et les relevés
des trajets se suivent sans interruption. La reprise n'est possible qu'une
fois par tournée.

**En route :** la vue conducteur de la tournée affiche l'arrêt suivant avec la
navigation, « Démarrer la tournée », pour chaque arrêt « Arrivé », « Terminé »
ou « Absent » et « Terminer la tournée » ; les heures deviennent le réalisé
des arrêts. « Replanifier le reste » sur une tournée en cours conserve les
arrêts terminés et ne replanifie que les arrêts ouverts à partir du dernier
arrêt terminé.

**Prévu et réalisé :** le rapport « Tournées prévu/réel » compare l'arrivée
et la durée de présence prévues et réelles par arrêt, avec les écarts par
conducteur et type de commande ; il fournit des valeurs d'expérience pour la
durée d'intervention des propositions futures.

Concertez-vous avec les intéressés avant de modifier une tournée en cours.
