---
title: "Leads"
topic: sales.leads
version: 6
keywords:
    - prospects
    - prospection
    - pipeline commercial
    - entonnoir de vente
    - CRM
    - qualifier un prospect
    - convertir en client
    - clients potentiels
    - relance
    - Calendly
    - doublons
audience: []
modules:
    - module.vertrieb
related:
    - contacts.manage
---

Un **lead** est un prospect **avant** le statut client — issu d’une
recommandation, du web, d’un salon ou d’un appel. La fiche lead tient contact,
source et besoin ; la qualification passe par les **notes de communication**
avec relance, comme chez un client.

**Leads issus de prises de rendez-vous.** Lorsqu’une personne sans fiche client
réserve via Calendly, un lead avec la source « Prise de rendez-vous » peut être
créé. Chaque organisation l’active dans les paramètres Calendly ; c’est désactivé
par défaut. Si un client existant peut correspondre, aucun lead n’est créé — la
réservation attend alors dans la boîte d’attribution. Une nouvelle réservation
de la même adresse est rattachée au lead ouvert.

## Le pipeline

**Nouveau → Contacté → Qualifié → Converti / Écarté.** Un lead écarté peut
être requalifié ; un converti vit ensuite comme client.

## Convertir — avec contrôle des doublons

Avant la création, la fiche montre les **clients existants possibles** (nom,
e-mail). Relier est l’option équivalente — un second fichier clients par la
porte de derrière est exactement ce que le nettoyage des doublons répare
ensuite péniblement. La fiche lead subsiste après conversion et pointe vers le
client : l’historique d’acquisition appartient à l’acquisition.

## Délai de conservation

Les leads sont des données personnelles **sans contrat**. Les leads non
convertis sont anonymisés **6 mois après le dernier contact** — via le scan de
conservation du module de protection des données (file de revue, pas de
suppression silencieuse). L’indicateur de pipeline (source, statut, période)
reste, la personne disparaît.

## Fiche

Les leads portent des champs personnalisés (Administration › Champs
personnalisés), des étiquettes et des pièces jointes. Lors de l'anonymisation
après le délai de suppression, pièces jointes, champs personnalisés et
étiquettes sont supprimés aussi. Les documents de la gestion documentaire ne
sont rattachés qu'au client.

## Cockpit

La fiche lead est le cockpit du prospect : en-tête avec statut, source et
étiquettes, en dessous contact et besoin, les notes de communication avec
rappel, les champs personnalisés, les pièces jointes, les devis créés à partir
du lead et l'historique. « Devis à partir du lead » crée un devis avec les
coordonnées ; les devis figurent avec leur statut dans la fiche, et
l'évaluation des leads les compte par source et conversion. Comme pour le
client, le fournisseur et le projet, les panneaux des autres modules ne
montrent que ce qui existe pour le lead — un module sans données pour ce lead
n'apparaît pas. L'historique liste les entrées d'audit du lead, devis compris ;
les réservations de rendez-vous sont rattachées au lead ouvert.

Toute personne autorisée à ouvrir la page « Demandes de rendez-vous » voit dans le panneau **Rendez-vous** les rendez-vous réservés et demandés du prospect (par exemple via Calendly) avec date, lieu et statut ; un clic ouvre les demandes de rendez-vous.

## Dernier contact

Le délai court à partir du **dernier contact**. Il avance à chaque note de
communication sur le lead (à sa date), à chaque modification de contenu, à
chaque changement de statut et à chaque devis créé depuis le lead. Changer
seulement la personne responsable ne compte pas comme contact.
