---
title: "Conflits Lexoffice"
topic: admin.lexoffice
version: 10
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

La stratégie de conflit s'applique aux contacts. Lexoffice fait foi pour
les articles : la synchronisation reprend chaque version plus récente de
Lexoffice, il n'y a donc pas de conflits d'articles.

**Adresses avec « Lexoffice l’emporte » :** Lexoffice gère les adresses sans
identifiant propre ; WorkDiary les associe selon leur type et leur ordre. Une
adresse de facturation ou de livraison créée dans WorkDiary reprend lors de la
première synchronisation l’adresse Lexoffice correspondante au lieu d’être
créée une seconde fois : l’adresse de facturation principale reprend la
première de Lexoffice. Ensuite, seule celle-ci est principale ; les autres
adresses de WorkDiary restent comme adresses supplémentaires.

**Type de taxe lors du transfert :** les factures et brouillons de facture de
la remise de facturation portent le type de taxe de leur cas fiscal :
prestation à une entreprise de l'UE comme « prestations externes § 13b UE »,
§ 13b national comme « travaux de construction § 13b », livraison
intracommunautaire, livraison à l'exportation, prestation non imposable en
Allemagne comme « prestation vers pays tiers », petite entreprise comme
« exonéré » — à 0 % par position et avec la mention comme note fiscale.
Hormis « exonéré », Lexoffice n'accepte ces types de taxe qu'avec un contact
créé chez lui. Si une prestation non imposable se situe dans l'UE (par
exemple un bien immobilier dans un autre pays de l'UE), Lexoffice n'a pas de
type de taxe adapté : le transfert est bloqué avec le motif. Le justificatif
issu des temps sur le client est une pièce comptable : par taux, le montant
net, la taxe et la catégorie comptable du cas fiscal (« Dienstleistungen »,
« Fremdleistungen § 13b », « Dienstleistungen an Drittländer », « Einnahmen
als Kleinunternehmer »), avec le numéro de pièce
`ZE-<numéro client>-<fin de prestation>-<numéro d'ordre>`. Pour le § 13b
national, les opérations exonérées selon le § 4, plusieurs cas fiscaux ou
une devise étrangère, il est bloqué avec le motif ; ces temps passent par la
remise de facturation.

Miroir des pièces : la synchronisation des pièces reprend en miroir les
pièces Lexoffice dans WorkDiary. Les pièces de contacts Lexoffice qui ne
sont encore rattachés à aucun client ou fournisseur sont elles aussi
reprises – d'abord sans client ni fournisseur ; dès que le contact est
rattaché, la synchronisation suivante les affecte. Un client n'est attribué
qu'aux pièces de vente et un fournisseur qu'aux pièces d'achat, même si un
contact est rattaché aux deux. La synchronisation des lignes enregistre en
outre le montant net et, pour un avoir, sa référence à la facture. Vous
lancez l'exécution manuellement dans les **paramètres Lexoffice** (Plugins →
Lexoffice) avec **Synchroniser les pièces maintenant** : elle récupère en
arrière-plan les pièces nouvelles et modifiées, la synchronisation complète
quotidienne est conservée ; le bouton exige le droit **Synchroniser les
justificatifs avec Lexoffice** et des identifiants enregistrés.

Autorisation : la Boîte de rapprochement est ouverte aux administrateurs
et au rôle **Comptabilité**.

**Souveraineté des données :** si, selon la souveraineté des données, un autre programme est maître des clients et fournisseurs, la synchronisation des contacts ne reprend aucun champ et ne crée aucun contact ; la liaison et le numéro de contact Lexoffice restent. L’état retenu attend dans la boîte d’intégration **Reprendre une fois**.
