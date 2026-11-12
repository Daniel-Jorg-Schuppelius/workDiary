---
title: "Fichier articles"
topic: articles.master
version: 5
keywords:
    - articles
    - créer un article
    - catalogue produits
    - référence article
    - SKU
    - variantes
    - EAN
    - GTIN
    - unités de mesure
    - code douanier
    - nomenclature douanière
audience: []
modules:
    - module.lager
related:
    - articles.lexoffice
    - materials.manage
    - inventory.stock
    - warehouses.manage
---

Le fichier articles est le répertoire central de tous les produits,
matériaux et prestations du client ; il sert de base au stock, aux achats,
à la fabrication et à la vente. Un article porte le numéro d'article, le
type, le statut, l'unité de base, le GTIN ainsi que les prix d'achat et de
vente par défaut ; sur la vue détaillée, vous gérez les options avec leurs
valeurs, les unités supplémentaires avec facteur de conversion et les
variantes, chaque variante recevant automatiquement un SKU. La création et
la modification se font par boîte de dialogue ; désactivez les articles
(statut « Retired ») au lieu de les supprimer — la suppression n'est
possible que sans données dépendantes.

Pour les documents douaniers, les articles indiquent sous « Douane et
exportation » la nomenclature douanière (6 à 11 chiffres ; les espaces et les
points sont supprimés), le pays d’origine en code ISO et le poids net par
unité.

## Stock et valorisation

Sous « Stock et valorisation », vous définissez dans la fenêtre de l’article une **méthode de valorisation** propre ; sans choix, la méthode de l’organisation s’applique. Le passage à FIFO ou FEFO crée une couche initiale pour le stock existant ; le retour au coût moyen pondéré n’est possible que lorsqu’il ne reste plus de stock de l’article en couches.

Avec un **préfixe de numéros de série**, l’article compte ses propres numéros de série (préfixe et numéro courant, avec chiffre de contrôle si souhaité) ; sans préfixe, la plage de numéros de l’organisation s’applique.

Les **unités** supplémentaires s’appliquent aux mouvements de stock, au scan, à la commande et à la réception : la quantité peut être saisie dans l’unité, le mouvement est enregistré dans l’unité de base. La propriété « durée de conservation requise » exige un lot et une date limite de consommation à la réception.

Si une gestion commerciale gère les numéros d’article (page « Plages de numéros », plage « Article »), le numéro d’article et le GTIN sont en lecture seule dans la fenêtre ; les nouveaux articles et variantes reçoivent leur numéro avec la synchronisation.

**Classe fiscale :** dans le formulaire d’article, vous consignez la classe fiscale (normale, réduite, majorée). Elle est enregistrée avec l’article, reprise à l’import et comparée pour les doublons et les fusions ; le taux d’un document reste fixé sur la ligne.

## Fabrication

Sous « Fabrication », vous choisissez la **gamme de fabrication par défaut** de
l’article : une version publiée d’un modèle de procédure actif. Les ordres et la
planification de fabrication produisent ensuite l’article selon cette gamme, tant
qu’un ordre n’en choisit pas une autre ; le calcul des besoins la lit aussi. La
section apparaît dès qu’il existe des versions de procédure publiées.
