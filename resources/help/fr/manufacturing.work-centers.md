---
title: "Capacités de fabrication (postes de travail)"
topic: manufacturing.work-centers
version: 1
audience: []
modules:
    - module.lager
related:
    - manufacturing.orders
    - inventory.stock
    - articles.master
---

Les postes de travail représentent les stations de fabrication où les
ordres sont traités : chacun porte un nom, un code optionnel, la capacité
journalière disponible en minutes et un temps de réglage forfaitaire. Le
tableau de capacité affiche, pour la période choisie dans l'en-tête, la
charge planifiée par poste — les minutes affectées, temps de réglage
inclus, comparées à la capacité journalière. L'affectation d'un ordre à un
poste s'effectue depuis sa page de détail. La lecture requiert
l'autorisation fabrication, la création de nouveaux postes l'autorisation
de mouvement de stock ; les capacités sont de pures grandeurs de
planification et n'affectent pas le stock.
