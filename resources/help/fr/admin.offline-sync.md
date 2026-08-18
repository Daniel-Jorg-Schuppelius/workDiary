---
title: "Synchronisation hors ligne"
topic: admin.offline-sync
version: 1
audience: []
related:
    - admin.metrics
---

Qui travaille en déplacement sans réseau enregistre dans une **outbox
d’appareil** ; dès que la connexion revient, l’appareil transmet les
commandes. Cette page montre **chaque commande transmise avec son résultat** —
la réponse à la question de savoir quelles données sont nées hors ligne et si
elles sont arrivées.

## Les quatre résultats

- **Appliqué** — la commande est dans les données. Le cas normal.
- **Doublon** — le même appareil a envoyé deux fois la même commande
  (typiquement après une coupure en pleine transmission). Pas une erreur : la
  commande a été appliquée la première fois, la répétition reconnue et
  écartée.
- **Conflit** — les données ont changé entre-temps ; la commande n’a **pas**
  été appliquée.
- **Rejeté** — la commande était invalide (par exemple un pointage dans un
  état non admis) ; la colonne d’erreur en nomme la raison.

**Conflit et Rejeté sont la raison d’être de cette page :** ces saisies ne
sont *pas* arrivées dans les données. Les compteurs du filtre de résultat
comptent toujours l’ensemble — un filtre posé ne les masque pas.

## Les deux horodatages

**Saisi (hors ligne)** est l’heure de l’appareil, **Transmis** l’arrivée sur
le serveur. L’écart entre les deux est la latence hors ligne — une journée est
normale en intervention, une semaine signale un appareil qui ne synchronise
pas.
