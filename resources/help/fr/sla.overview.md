---
title: "SLA, contrats & niveaux de service"
topic: sla.overview
version: 9
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

Vous gérez les niveaux dans le contrat sous **Niveaux d’escalade** : pour
chaque niveau, les minutes après l’échéance de résolution et le destinataire –
le responsable du ticket, un rôle ou une personne.

## Quel contrat s’applique

Pour un nouveau ticket, le premier contrat correspondant s’applique dans cet
ordre : contrat de l’actif, contrat du projet, contrat du client, **SLA par
défaut** de la file, **contrat par défaut** de l’organisation. Le contrat de
l’actif est l’engagement le plus étroit – il vaut pour cet objet précis et se
choisit dans le formulaire de l’actif (thème « Équipements & parc de
véhicules »). Il ne s’applique que s’il est actif et appartient au client du
ticket ou n’a pas de client ; sinon, l’ordre restant s’applique. Un
changement de file le laisse en place. Si le contrat ne définit pas
d’heures d’ouverture, celles de la file s’appliquent ; la région des jours
fériés de la file remplace celle de l’organisation. L’état du contrat est
figé sur le ticket – les modifications ultérieures du contrat ne
réinterprètent pas les tickets existants.

## Pauses

Sous **Pause du SLA sur**, le contrat définit dans quels états d’attente
l’horloge s’arrête. À la reprise – également par une réponse du client – les
délais ouverts sont décalés de la durée de la pause.

## OLA

Un contrat avec **OLA (objectif interne)** et une équipe n’est pas un
engagement envers le client : il n’est jamais utilisé comme SLA d’un ticket
et ne peut être ni contrat par défaut ni lié à un client ou à un projet. Les
tickets des files de l’équipe affichent en plus l’objectif interne sur
l’horloge SLA.

Si un ticket ouvert dépasse l’objectif interne, le responsable de l’équipe OLA
reçoit une notification unique (à défaut, la personne assignée) ; ensuite
s’applique l’escalade de la règle de notification **Objectif interne (OLA)
dépassé**. WorkDiary ne tient pas de registre des violations pour les OLA.

## Maintenances contractuelles obligatoires

La page du contrat liste sous **Maintenances contractuelles
obligatoires** les plans de maintenance rattachés à ce contrat – avec
l'actif, la prochaine échéance et l'action **À l'échéance**. Les plans
avec **Obligation contractuelle** portent la marque du même nom. Vous
définissez le rattachement dans la boîte de dialogue du plan de
maintenance de l'actif (sujet « Équipements & parc de véhicules »). Avec
l'action **Créer un ticket de service**, un ticket est créé pour chaque
échéance, auquel ce contrat s'applique.

## Contingents de temps inclus

Sur le contrat SLA, **Ajouter un contingent** fixe par période (mois, trimestre,
année) les heures incluses, le **seuil d’alerte** en pourcentage à partir
duquel la surveillance des délais signale, et à titre de justificatif le
**forfait** et le **taux des heures supplémentaires**. La consommation est le
temps facturable des personnes pour le client du contrat dans la période. La
page du contrat et le rapport SLA affichent la consommation, le forfait et le
montant des heures supplémentaires (heures au-delà du contingent × taux).
workDiary n’en tire pas de facture – la facturation se fait dans le logiciel de
comptabilité. Modifier et supprimer requièrent le droit de gérer les contrats
SLA.

## Rapport SLA

Le **Rapport SLA** (**Rapports** → **Projets et clients** → **SLA**)
affiche, pour la période choisie, les **Tickets avec SLA**, le **Taux de
respect** et les **Violations** – ventilées **Par type**, **Par
priorité**, **Par client** et **Par cause** – ainsi qu’une **Liste des
violations** avec accès direct au ticket et les **Quotas de temps
inclus**. Le rapport peut être exporté en PDF, CSV et Excel. Toute
personne disposant du droit **Consulter le statut et le rapport SLA**
peut le consulter ; l'export nécessite en outre le droit **Exporter les
rapports** – sans ce droit, les boutons d'export n'apparaissent pas. Les
administrateurs peuvent toujours faire les deux.

## Suggestion de matériel

Si un équipement avec nomenclature est rattaché au ticket, celui-ci affiche les **pièces d’usure à échéance** (selon l’intervalle de remplacement et la date de montage) comme suggestion pour l’intervention — ce qui est réellement remplacé, c’est le technicien qui le décide. La même carte apparaît sur les ordres avec équipement.

**Tickets confidentiels dans le rapport :** pour les tickets confidentiels que
vous ne pouvez pas ouvrir, la liste des violations de SLA n'affiche ni le ticket ni
le client ; dans la répartition par client, ils comptent sous « Ticket
confidentiel ».
