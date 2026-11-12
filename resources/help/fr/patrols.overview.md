---
title: "Rondes de surveillance"
topic: patrols.overview
version: 2
keywords:
    - ronde de sécurité
    - contrôle de ronde
    - points de contrôle
    - scanner un point de contrôle
    - preuve de passage
    - gardiennage
    - agent de sécurité
    - rondier
    - QR code
    - point manqué
audience: []
modules:
    - module.planung
related:
    - dispatch.overview
---

Une **ronde** est une liste ordonnée de **points de contrôle** avec fenêtres
cibles relatives au départ (« point 3 : +20 min ± 10 »). Le **scan atteste
point et heure** — la preuve fiable envers les donneurs d’ordre
(surveillance, facility, service hivernal).

## Jetons

Chaque point de contrôle reçoit un **jeton** (imprimé sur le tag/en QR). Seul
le hachage est stocké ; le texte en clair apparaît exactement une fois — à la
création. **Un tag perdu** se remplace via « réémettre le jeton » : nouveau
jeton, même parcours, l’ancien est immédiatement sans valeur.

## Exécution

Démarrer la ronde → scanner les jetons (le scanner caméra tape comme un
clavier, ou saisie manuelle) → terminer. Au plus une ronde par parcours à la
fois ; les doubles scans comptent une fois.

## Interruption

Une ronde en cours peut être **interrompue** — uniquement avec un **motif**.
Elle ne compte alors pas comme terminée ; les points de contrôle confirmés
restent acquis comme preuve, et le rapport indique l’interruption avec motif,
personne et heure. Les points ouverts sont transmis au poste de contrôle
comme **point ouvert**, comme pour un écart. Le parcours est ensuite de
nouveau libre.

## Écarts

Les points manqués ou scans hors fenêtre sont **montrés, jamais lissés** — et
la clôture exige alors une **justification**. De plus, un **point ouvert**
est créé pour le poste de contrôle (échéance le lendemain) — l’escalade passe
par le système existant, pas de canal séparé.

Les horaires cibles sont une **preuve, pas une métrique de pression** : pas
de données de position au scan ni d’évaluation durable par personne.

## Gérer les itinéraires et les points de contrôle

**Modifier** change la désignation, le site et la **personne responsable** de
l'itinéraire. Un itinéraire peut être **désactivé** — une ronde en cours peut se
terminer, aucune nouvelle ne peut être lancée. Les points de contrôle peuvent
être modifiés (lieu, consigne, tolérance ; les scans enregistrés conservent
leur évaluation) et **désactivés** : dès lors, ils ne sont plus attendus et ne
peuvent plus être scannés. Les rondes antérieures restent inchangées : une
ronde commencée avant la désactivation attend toujours le point.

## Escalade

Le point ouvert est créé **dès l'écart** — au premier scan hors fenêtre ou dès
qu'un point échu a été sauté — et va à la personne responsable de
l'itinéraire, à défaut au responsable d'équipe de la personne qui effectue la
ronde. Il y a un point ouvert par ronde ; il est mis à jour jusqu'à la fin. Si
une ronde est encore en cours après la durée prévue plus la tolérance du
dernier point, le contrôle des échéances la signale une fois comme **non
terminée** ; le poste de contrôle peut alors l'interrompre avec un motif.
