---
title: "Conflits Lexoffice"
topic: admin.lexoffice
version: 5
keywords:
    - Lexware Office
    - conflit de synchronisation
    - données divergentes
    - résoudre un conflit
    - garder valeurs locales
    - reprendre valeurs externes
    - rapprochement des données
    - miroir des pièces
    - synchronisation des pièces
audience:
    - admin
    - buchhaltung
related:
    - admin.plugins
    - articles.lexoffice
    - invoices.manage
    - admin.integration-inbox
    - inventory.conflicts
---

Cette page permet de résoudre les conflits de synchronisation avec
Lexoffice. Un conflit survient lorsqu'un enregistrement local
(WorkDiary) et le contact Lexoffice correspondant divergent sur un ou
plusieurs champs et que la **Stratégie de conflit** des paramètres
Lexoffice est réglée sur **Vérification manuelle** (valeur par défaut).
Les conflits se traitent dans la **Boîte de rapprochement** : l'appel de
cette page l'ouvre, filtrée sur la source **Lexoffice** et le cas
**Conflit de champ**.

Dans la Boîte de rapprochement :

- Pour chaque conflit, les champs divergents apparaissent côte à côte
  sous **Local** et **Remote**.
- Sont concernés les clients et les fournisseurs, c'est-à-dire les
  contacts issus de Lexoffice.
- Le filtre de statut permet de retrouver aussi les conflits déjà
  traités.

Solutions pour chaque conflit :

- **Reprendre le distant** : met à jour l'enregistrement local avec les
  valeurs Lexoffice des champs divergents.
- **Conserver le local** : conserve les valeurs locales ; les valeurs
  Lexoffice divergentes ne sont pas reprises.
- **Rejeter** : clôt le conflit sans modification (par ex. pour des
  données volontairement différentes) ; il reçoit le statut **Rejeté**.

Risques : **Reprendre le distant** écrase des valeurs locales. Vérifiez
soigneusement les données comparées avant de décider. Rappelez-vous que,
pour les factures, la souveraineté de facturation reste au programme
externe – WorkDiary se contente de lui livrer les données.

La stratégie de conflit s'applique aux contacts et aux articles. Les
conflits d'articles n'apparaissent pas dans la Boîte de rapprochement,
mais dans l'onglet **Conflits** du stock (**Stock** → **Conflits**) :
vous y choisissez **Conserver local**, **Reprendre l'état Lexoffice**
ou **Rejeter** — avec le droit **Gérer les articles**.

Miroir des pièces : la synchronisation des pièces reprend en miroir les
pièces Lexoffice dans WorkDiary. Les pièces de contacts Lexoffice qui ne
sont encore rattachés à aucun client ou fournisseur sont elles aussi
reprises – d'abord sans client ni fournisseur ; dès que le contact est
rattaché, la synchronisation suivante les affecte. Un client n'est attribué
qu'aux pièces de vente et un fournisseur qu'aux pièces d'achat, même si un
contact est rattaché aux deux. La synchronisation des lignes enregistre en
outre le montant net et, pour un avoir, sa référence à la facture.

Autorisation : la Boîte de rapprochement est ouverte aux administrateurs
et au rôle **Comptabilité**.
