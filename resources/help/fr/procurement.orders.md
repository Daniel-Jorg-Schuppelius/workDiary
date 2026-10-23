---
title: "Achats & commandes"
topic: procurement.orders
version: 3
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
