---
title: "SLA, contrats & niveaux de service"
topic: sla.overview
version: 3
keywords:
    - accord de niveau de service
    - délai de réponse
    - délai de résolution
    - contrat de service
    - violation de SLA
    - dépassement de délai
    - escalade
    - ticket en retard
    - taux de respect
    - rapport SLA
audience: []
related:
    - glossary.core
---

Les contrats SLA (Service Level Agreements) enregistrent, par client ou
sous forme de **Contrat par défaut** pour tous les clients, les délais de
réaction et de résolution convenus par priorité (**Délais par
priorité**), éventuellement avec des **Heures ouvrées** – sans elles, les
délais courent en temps calendaire. Vous trouvez les contrats sous
**Service desk** → **Contrats SLA**. À partir de ces valeurs cibles,
WorkDiary déduit le statut SLA d’un ticket de service et documente les
dépassements de manière inaltérable.

## Statut SLA sur le ticket

Chaque ticket de service doté d’un délai SLA affiche son statut de
résolution sous forme de badge :

- **SLA dans les temps** : il reste suffisamment de temps jusqu’au délai
  de résolution.
- **SLA menacé** : le temps restant représente au plus 20 % du délai
  total.
- **SLA dépassé** : le délai est dépassé (ou le ticket a été confirmé ou
  résolu trop tard).
- **SLA respecté** : le ticket a été résolu à temps.

Les tickets sans délai affichent « Aucun SLA ». Le délai de réaction est
évalué de la même manière et vérifié lors de la première confirmation.

## Registre des violations et détection

Les délais dépassés sont consignés dans un registre des violations –
exactement une fois par ticket et par type (« Temps de réaction » ou
« Temps de résolution »). Ils sont détectés :

1. lors du contrôle automatique des tickets ouverts, qui s’exécute par
   défaut toutes les cinq minutes,
2. lors des changements de statut, lorsque la première réaction ou la
   résolution intervient trop tard.

Chaque violation peut recevoir une **Cause** dans la **Liste des
violations** du rapport SLA et être marquée avec **Acquitter** ; cela
requiert le droit **Acquitter les violations de SLA**.

## Escalade

Le contrôle automatique notifie la personne assignée pour les tickets
menacés et dépassés. Si l’événement reste non traité, WorkDiary escalade
selon les **Règles de notification** de l’organisation vers le rôle
d’escalade qui y est défini (par défaut les chefs d’équipe). En outre,
WorkDiary applique les niveaux enregistrés sous **Escalade** dans le
contrat SLA.

## Rapport SLA

Le **Rapport SLA** (**Rapports** → **Projets et clients** → **SLA**)
affiche, pour la période choisie, les **Tickets avec SLA**, le **Taux de
respect** et les **Violations** – ventilées **Par type**, **Par
priorité**, **Par client** et **Par cause** – ainsi qu’une **Liste des
violations** avec accès direct au ticket et les **Quotas de temps
inclus**. Le rapport peut être exporté en PDF, CSV et Excel. Toute
personne disposant du droit **Consulter le statut et le rapport SLA**
peut le consulter.
