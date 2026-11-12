---
title: "Descriptifs de prestations GAEB"
topic: boq.overview
version: 3
keywords:
    - DPGF
    - DQE
    - bordereau des prix
    - importer un descriptif
    - GAEB DA XML
    - X83
    - X86
    - consultation
    - métré
    - avenant
    - prix unitaire
    - remise des offres
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

## Remarques préliminaires et contrôle avant remise

Si le fichier GAEB contient des **remarques préliminaires** (textes contractuels avant les postes), la vue du bordereau les affiche dans une section dépliable, un texte par bloc et tel que livré. Une réimportation les remplace par celles du fichier. Elles sont lues depuis GAEB 90 et depuis DA XML (textes complémentaires du bloc d’attribution).

Le **contrôle avant remise** signale en outre les postes dont le prix est dans une autre devise que le bordereau et une TVA qui ne correspond pas au taux appliqué au total net ou qui, ajoutée à celui-ci, ne donne pas le total brut (au centime près).

## Commentaires du soumissionnaire

Dans la demande d’offre, le maître d’ouvrage décide si les soumissionnaires peuvent commenter les postes. La vue du bordereau indique ce que dit le fichier. Si les commentaires ne sont pas autorisés, l’import n’en reprend aucun (le journal d’import en indique le nombre) et l’offre n’en contient pas. Réimporter l’offre ne modifie pas ce que fixe la demande.

## Avancement avec pièce et constats de l’import

Outre la saisie rapide de quantité, l’icône du mètre ruban d’une position ouvre la fenêtre **Déclarer l’avancement**. Vous y choisissez en plus une **consommation de matériel** du projet ou un **procès-verbal** du projet. La pièce choisie détermine la source de la déclaration et y reste attachée ; la fenêtre affiche les déclarations précédentes avec leur référence. Une consommation de matériel ne peut être affectée qu’à une seule position ; ses coûts sont imputés à cette position dans l’analyse de rentabilité. Pour une simple affectation de coûts, saisissez la quantité 0.

L’**historique des imports** affiche les constats de chaque exécution. Un réimport refusé y figure avec le statut « conflit » et indique, pour chaque position protégée, la quantité et le prix unitaire existants à côté des valeurs du fichier. Il ne modifie rien dans le devis quantitatif lui-même.

Le procès-verbal lui-même propose aussi, dans son menu, **Déclarer l’avancement d’une position du devis quantitatif** lorsque le projet du procès-verbal possède un devis quantitatif avec des positions ouvertes. La déclaration reçoit la source « procès-verbal » et renvoie à ce procès-verbal.
