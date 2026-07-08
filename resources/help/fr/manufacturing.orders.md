---
title: "Ordres de fabrication"
topic: manufacturing.orders
version: 1
audience: []
related:
    - manufacturing.work-centers
    - procurement.orders
    - articles.master
    - inventory.stock
---

Les ordres de fabrication modélisent la production d'un article à partir de
sa nomenclature ou recette : seuls les articles marqués fabricables sont
sélectionnables, et le système déduit le besoin en matières de la quantité
cible, de la variante et de la nomenclature. La libération fige un snapshot
de la nomenclature, si bien que des modifications ultérieures n'affectent
plus l'ordre en cours. Le déroulement suit une machine à états (brouillon,
libéré, en cours, en attente, bloqué, terminé, annulé) : « **Réserver** »
bloque la matière sur le stock, le démarrage journalise l'exécution, les
déclarations partielles saisissent les quantités produites, bonnes, rebutées
et à retoucher, et « **Livrer** » entre les produits finis en stock (variante
et entrepôt requis). Depuis la page de détail, l'ordre peut être affecté à un
poste de travail ou sous-traité à un fournisseur (crée une commande) ; la
vue de planification montre le calcul des besoins multi-niveaux (MRP) et
les indicateurs qualité. L'annulation est irréversible ; créer, déclarer et
livrer exigent l'autorisation de mouvement de stock.
