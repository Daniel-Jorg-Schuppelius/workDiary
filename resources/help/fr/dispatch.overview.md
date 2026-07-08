---
title: "Dispatching et alertes de conflit"
topic: dispatch.overview
version: 1
audience: []
related:
    - diary-entries.edit
    - planning.shifts
    - assets.fleet
---

Le dispatching détermine **qui exécute quelle commande et quand**, en
complément de la machine à états métier ; chaque commande porte un
**statut de dispatching** (Non planifié, Planifié, Confirmé, En route,
Terminé). Avant la confirmation, WorkDiary vérifie l'affectation prévue
contre les règles de temps de travail et de disponibilité
(chevauchements, repos, durées maximales, congés et absences). Les
**conflits durs** bloquent la confirmation et ne peuvent être outrepassés
qu'avec une **justification documentée**, consignée de manière
infalsifiable ; les **avertissements** sont de simples indications. Un
véhicule peut être réservé pour un créneau sur la commande : le système
empêche la double réservation, et les réservations par véhicule se
consultent et s'annulent dans la liste dédiée.
