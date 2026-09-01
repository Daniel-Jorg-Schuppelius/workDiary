---
title: "Descriptifs de prestations GAEB"
topic: boq.overview
version: 1
audience: []
modules:
    - module.bau
related:
    - projects.manage
    - invoices.manage
---

Les descriptifs de prestations (LV) représentent les prestations de
construction de manière structurée — de l'échange de données GAEB importé
au métré et au calcul, jusqu'à l'export de l'état actuel.

**Import avec preflight :** Sont lus les fichiers GAEB-DA-XML de la
version 3.x dans les phases d'échange X81 à X86 (descriptif de
prestations, estimation des coûts, appel d'offres, remise d'offre, offre
variante, attribution du marché). Avant toute écriture, un preflight
vérifie la version, la phase d'échange, la structure, l'unicité des
numéros d'ordre ainsi que la plausibilité des quantités et des unités.
Les constats bloquants ne produisent qu'un protocole d'import — rien
n'est écrit. Un réimport dans un LV existant s'interrompt s'il devait
écraser des positions liées à une exécution ou à un décompte.

**Structure & états de prix :** Un LV se compose d'un en-tête, de
sections hiérarchiques avec numéros d'ordre et de positions avec texte
court et texte long, quantité, unité et prix unitaire. Chaque import
dépose des snapshots de prix, de sorte que les états de prix antérieurs
restent traçables. Un LV peut être rattaché à un projet ; les positions
peuvent être liées à des articles ou à du matériel.

**Métré & calcul a posteriori :** Les avancements sont saisis de manière
additive par position (quantité, source, note). Les positions ayant un
premier métré passent automatiquement à « en cours ». Le calcul a
posteriori confronte le prévu (quantité prévue × prix unitaire), le
réalisé (quantité métrée × prix unitaire), le reste à réaliser et le taux
d'avancement — il s'agit d'une analyse qui ne remplace aucune
facturation.

**Workflow :** Le LV et ses positions individuelles suivent des
transitions de statut dirigées, de l'appel d'offres à l'exécution et à la
clôture en passant par l'offre et la commande ; les sauts invalides sont
rejetés. Les avenants sont gérés comme des positions propres, la vue du
reste à réaliser montre ce qui est encore ouvert.

**Export :** L'état actuel du LV peut être téléchargé au format
GAEB-DA-XML dans une phase d'échange au choix (par défaut : attribution
du marché). L'export est déterministe et journalisé avec un hachage du
contenu — le même état produit de manière reproductible le même hachage.
