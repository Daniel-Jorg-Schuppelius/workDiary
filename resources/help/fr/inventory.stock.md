---
title: "Stocks et scan"
topic: inventory.stock
version: 1
audience: []
related:
    - warehouses.manage
    - inventory.counts
    - inventory.labels
    - articles.master
---

La vue des stocks affiche, par emplacement, les quantités disponibles,
physiques et réservées des variantes, le prix moyen pondéré, la valeur de
stock et le seuil de réapprovisionnement. Avec le droit de mouvement, vous
saisissez des mouvements manuels (entrée, sortie, réservation, libération)
et définissez les stocks minimum et de réapprovisionnement ; les sorties
en négatif ne sont possibles que si vous les autorisez explicitement. Les
lots se gèrent dans la liste des lots (division et fusion possibles) ; la
vue de scan résout un code (numéro de série, lot, GTIN ou SKU) et
comptabilise directement une action (entrée, sortie, transfert). Tous les
mouvements sont inscrits dans le journal continu et sont irréversibles —
les corrections se font par contre-écritures.
