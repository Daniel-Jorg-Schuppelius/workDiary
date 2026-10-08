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
