---
title: "Achats & commandes"
topic: procurement.orders
version: 6
keywords:
    - approvisionnement
    - bon de commande
    - commande fournisseur
    - réception de marchandises
    - livraison partielle
    - avis d'expédition
    - réapprovisionnement
    - seuil de réapprovisionnement
    - quantité minimale de commande
    - livraisons attendues
audience: []
modules:
    - module.lager
related:
    - inventory.stock
    - articles.master
    - manufacturing.orders
    - contacts.manage
---

Les commandes enregistrent l’achat d’articles auprès d’un fournisseur
pour un entrepôt cible. Vous les trouvez sous **Ventes et facturation** →
**Achats & catalogues** → **Commandes**. **Nouvelle commande** crée
d’abord un brouillon avec **Fournisseur**, **Entrepôt** et,
facultativement, **Date de livraison** et **Note**. Avec **Ajouter une
ligne**, vous remplissez les lignes de commande (**Article**,
**Quantité**, facultativement **Prix unitaire**), puis vous passez la
commande avec **Commander**. Seuls les articles marqués **Achetable**
peuvent être commandés. Le statut passe par « Brouillon », « Commandé »,
« Partiellement reçu », « Reçu » ou « Annulé ».

La réception (**Réceptionner**) est enregistrée ligne par ligne et
augmente le stock de façon valorisée ; les livraisons partielles et
excédentaires sont prises en charge, et les colonnes **Commandé** et
**Reçu** indiquent l’avancement. Vous pouvez aussi, avec **Saisir un avis
de livraison**, consigner les quantités annoncées pour une commande, puis
reprendre la réception plus tard avec **Enregistrer la réception**.
L’onglet **Réceptions attendues** ouvre la vue « Réceptions attendues » ;
elle liste les lignes ouvertes des commandes passées, triées par date de
livraison.

L’onglet **Suggestions de commande** détermine, après **Choisir un
entrepôt**, le **Besoin** à partir du seuil de commande et des demandes
ouvertes et propose des quantités (**Suggestion**) en tenant compte de
la quantité minimale de commande et du fournisseur préféré. **Créer les
commandes** en tire des brouillons par fournisseur, à vérifier avant de
commander. La création, la commande et l’enregistrement exigent le droit
**Enregistrer des mouvements de stock** ; **Annuler** une commande est
irréversible.

## Feu des justificatifs

Si des justificatifs obligatoires sont exigés du fournisseur (par exemple attestation d’exonération ou assurance), l’en-tête de la commande affiche leur **feu** : vert si tout est valable, jaune si une échéance approche, rouge s’ils manquent ou ont expiré. Un clic ouvre la vue d’ensemble des justificatifs. Le blocage de la transmission en cas de rouge reste régi par le paramètre des justificatifs.

## Réponse du fournisseur via GAEB

Outre la confirmation openTRANS, **Importer la réponse du fournisseur** lit aussi les fichiers commerciaux GAEB. Un **X97** (confirmation de la commande X96) enregistre la quantité, le prix et la date confirmés par ligne et signale les écarts sans modifier la commande. Un **X94** (offre de prix à la demande X93) reprend les prix unitaires proposés dans la commande tant qu’elle est en brouillon. Les lignes sont rapprochées par la référence article du fournisseur ; le fichier doit correspondre au numéro de la commande. La demande de prix elle-même (**Demande de prix (GAEB X93)**) et le champ pour l’offre se trouvent dans la commande en brouillon.

## Unité de commande et réception

Si l’article possède d’autres unités, vous choisissez l’**unité de commande** en ajoutant la ligne (par exemple rouleau au lieu de mètre). La quantité et le prix unitaire s’entendent alors par unité de commande, de même que les quantités commandée et livrée de la ligne. La réception convertit en unité de base : le stock reçoit la quantité en unité de base, le prix de revient est tenu par unité de base, et le journal de stock affiche la saisie dans l’unité de commande. Le conditionnement d’un article fournisseur reste une grandeur d’arrondi des propositions de commande en unité de base.

Les articles portant la propriété « durée de conservation requise » exigent un lot et une date limite de consommation à la réception ; un lot déjà connu apporte sa date.

## Types de justificatifs et proposition de commande

Sous **Justificatifs obligatoires → Types de justificatifs**, vous gérez les types propres à l’organisation : désignation, validité standard, préavis, comportement en cas de justificatif manquant et caractère obligatoire. Avec des étiquettes, l’obligation ne vaut que pour les fournisseurs qui en portent une (corps de métier, type de prestation). Les types du catalogue servent de modèle et peuvent être repris comme type propre ; les types propres sont désactivés au lieu d’être supprimés.

Leur gestion requiert le droit **Gérer les types de justificatifs des fournisseurs** (par défaut : administration et direction). Le droit de modifier les fournisseurs ne suffit pas, car un type de justificatif peut bloquer l’envoi des commandes à tous les fournisseurs de l’organisation.

La **proposition de commande** arrondit au conditionnement de l’article fournisseur. Si l’article possède une unité ayant exactement ce facteur, la proposition commande dans cette unité et convertit le prix par unité de commande.
