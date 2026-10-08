---
title: "Équipements & parc de véhicules"
topic: assets.fleet
version: 2
keywords:
    - gestion de flotte
    - gestion des véhicules
    - inventaire
    - matériel
    - prêt de matériel
    - sortie de matériel
    - retour de matériel
    - carnet de carburant
    - journal de recharge
    - entretien
    - signaler un défaut
    - cycle de vie
audience: []
modules:
    - module.fuhrpark
related:
    - documents.manage
    - travel-expenses.manage
    - reports.overview
---

Les actifs et les véhicules représentent des objets d'exploitation avec
leur statut, leurs responsabilités, leurs documents et leurs
informations de maintenance. Les journaux de carburant et de recharge
complètent l'historique de consommation.

Saisissez les données de base et les identifiants uniques, associez un
site ou des responsables et tenez à jour les intervalles de maintenance
ainsi que les documents pertinents. Les changements de statut doivent
refléter le cycle de vie réel.

Avant une suppression ou une mise au rebut, vérifiez si des
maintenances, opérations, trajets ou documents ouverts y sont liés. Un
historique critique doit être archivé et ne doit pas être perdu par
écrasement.

## Attribution et restitution

Le panneau « Attribution / restitution » de la page de détail de
l'actif permet d'attribuer un appareil à une personne ou à une équipe,
éventuellement avec une référence à une commande et une date de
restitution prévue. Chaque actif ne peut avoir qu'une seule attribution
ouverte ; un actif déjà attribué ou bloqué pour cause de défaut ne peut
pas être attribué à nouveau. Lors de la restitution, l'actif redevient
disponible. Si une attribution dépasse la date de restitution prévue,
un avertissement de retard s'affiche et le scanner d'échéances avertit
la personne qui l'a emprunté ou le responsable d'équipe.

## Défauts et blocages

Le panneau « Défauts / blocages » permet de saisir des défauts avec un
niveau de gravité. Si « Verrouiller l'actif (aucun retrait possible) »
est coché, le défaut ouvert bloque toute nouvelle attribution jusqu'à
ce qu'il soit résolu ou sorti. Une note de résolution est obligatoire
pour résoudre ou sortir un défaut.

## Dossier d'objet (cycle de vie)

Le « Dossier d'objet » regroupe l'ensemble du cycle de vie d'un actif
dans une vue cohérente et imprimable : données de base, site et local,
statut de cycle de vie dérivé (en service, remplacé ou désaffecté),
mise en service, mise hors service et garantie. En dessous figurent
les maintenances, les attributions et restitutions, les défauts et
blocages, les commandes liées, les procès-verbaux, les consommations de
matériel, les points ouverts et les pièces jointes ainsi que
l'historique complet du cycle de vie.

Le dossier est accessible via le bouton « Dossier du site » de la page
de détail de l'actif et peut être sorti sous forme de document via la
fonction d'impression du navigateur (l'ajout de « ?print=1 » ouvre
directement la boîte de dialogue d'impression). Le statut de cycle de
vie est dérivé du statut, de la mise hors service et de la garantie –
il n'y a pas de saisie séparée.
