---
title: "Dispatching et alertes de conflit"
topic: dispatch.overview
version: 2
keywords:
    - planification des interventions
    - affecter une intervention
    - planifier un technicien
    - double réservation
    - chevauchement
    - temps de repos
    - durée maximale de travail
    - réserver un véhicule
    - réservation de véhicule
    - confirmer le rendez-vous
audience: []
related:
    - diary-entries.edit
    - planning.shifts
    - assets.fleet
---

La planification détermine **qui traite quelle commande et quand** — en
complément de la machine d'états métier des commandes. Chaque commande porte
un **Statut de planification** :

- **Non planifié** : ni programmée ni affectée.
- **Planifié** : programmée ou affectée à un salarié.
- **Confirmé** : l'affectation a été confirmée de manière ferme.
- **En route** : l'intervention est en cours.
- **Terminé** : la commande est clôturée.

## Alertes de conflit avant la confirmation

Avant la confirmation du rendez-vous, WorkDiary contrôle l'affectation
prévue au regard des règles existantes de temps de travail et de
disponibilité (chevauchement avec d'autres postes ou commandes, temps de
repos, durée maximale de travail journalière/hebdomadaire, congés et
absences). Il existe deux niveaux de gravité :

- Les **conflits bloquants** empêchent la confirmation. Ils ne peuvent être
  outrepassés délibérément qu'avec une **justification documentée** ; ce
  forçage est consigné de manière probante.
- Les **avertissements** sont de simples indications et ne bloquent pas.

## Réservation de véhicule

Un véhicule peut être réservé pour une plage horaire depuis la commande. Si
le véhicule est déjà réservé pendant la période souhaitée, le système
empêche la double réservation. Les réservations de chaque véhicule peuvent
être consultées dans la liste des réservations et annulées.
