---
title: "Inventaire"
topic: inventory.counts
version: 4
keywords:
    - comptage des stocks
    - inventaire physique
    - inventaire tournant
    - inventaire annuel
    - feuille de comptage
    - compter le stock
    - écarts d'inventaire
    - comptabiliser les écarts
    - classe ABC
audience: []
modules:
    - module.lager
related:
    - inventory.stock
    - warehouses.manage
    - articles.master
---

Cette page permet de réaliser des inventaires par emplacement de stockage.
Ouvrez un inventaire complet ou un comptage cyclique partiel par classe
ABC ne couvrant que les variantes échues, puis saisissez les quantités
comptées ligne par ligne dans la vue détaillée, au besoin par scan. Tant
que l'inventaire est ouvert, les résultats restent modifiables. La
comptabilisation des écarts crée des écritures de correction dans le stock
et clôture l'inventaire — cette action exige un droit d'approbation
spécifique et est irréversible : vérifiez les valeurs avant d'appliquer
les écarts.

## Comptage en unités et validation

Si un article possède d’autres unités, la quantité comptée peut être saisie dans l’une d’elles par ligne dans la liste de comptage, lors du scan et dans le comptage mobile ; elle est enregistrée dans l’unité de base. Compter et valider sont des droits distincts. Si la validation à quatre yeux est activée, les écarts à partir du seuil ne sont pas validés par la personne qui les a comptés.

**Compter hors ligne avec une unité :** même sans réseau, la vue mobile compte dans l’unité choisie (par exemple carton). À l’envoi, le serveur la convertit dans l’unité de base, comme en ligne.

## Terminer, rouvrir ou abandonner le comptage

**Terminer le comptage** fige les quantités comptées pour la vérification ; plus rien ne peut ensuite être saisi. La personne qui valide les écarts peut **rouvrir le comptage** pour corriger, comptabiliser les écarts ou **abandonner l'inventaire** – un inventaire abandonné ne comptabilise rien. L'étape de vérification est facultative ; la validation est aussi possible directement depuis le comptage.
