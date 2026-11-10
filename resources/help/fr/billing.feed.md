---
title: "Flux de documents"
topic: billing.feed
version: 6
keywords:
    - factures
    - devis
    - factures clients
    - factures fournisseurs
    - avoirs
    - notes de frais
    - postes ouverts
    - relance
    - factures en retard
    - facture électronique
    - liste des documents
    - recettes
    - filtre projet
audience: []
modules:
    - module.vertrieb
    - module.finance
related:
    - billing.chain
    - invoices.manage
    - quotes.overview
    - finance.incoming-invoices
    - travel-expenses.manage
---

Le flux de documents présente **tous les documents dans une seule liste** :
devis, factures de vente et d'achat, avoirs, pièces reprises d'une
comptabilité connectée et notes de frais. Les anciennes pages « Devis » et
« Factures » mènent ici – ce sont désormais des onglets de la même liste.

**Période :** la liste suit le filtre de dates de l'en-tête. Si un document
manque, vérifiez d'abord la période choisie. Un résultat de la recherche
globale ouvre la liste à la date du document ; ce jour remplace alors la
période de l'en-tête.

**Onglets :** « Tous », « Devis », « Factures de vente », « Factures
d'achat », « Avoirs » et « Notes de frais » sont des filtres enregistrés, pas
des pages distinctes. Le nombre sur l'onglet indique les documents de la
période. « Autres » (confirmations de commande, bons de livraison, divers)
n'apparaît que s'il contient quelque chose. La recherche et les filtres sont
conservés lorsque vous changez d'onglet.

**Indicateurs :** les tuiles portent sur l'ensemble filtré, pas seulement sur
la page affichée – par devise et sans conversion.

Un clic sur **En cours** filtre les pièces en cours (un second clic retire le filtre) ; les tuiles de totaux n’ont pas de cible.

- **Produits**, **Charges (externes)** et **Solde** comparent les documents
  sortants et entrants.
- **Mes notes de frais** indique vos propres notes de frais et la part encore
  en cours de contrôle.
- **En cours** et **dont en retard** : le retard est un sous-ensemble de
  l'en-cours, les deux montants ne s'additionnent pas. Un clic sur la tuile
  filtre les documents en retard.
- **Sans effet monétaire :** devis, confirmations de commande et bons de
  livraison ne comptent qu'en nombre.

**Filtres :** la recherche trouve le numéro, le client et le fournisseur.
S'y ajoutent l'origine (créé dans WorkDiary ou issu d'un système connecté),
l'attribution (client ou fournisseur), le statut (Brouillon, En cours,
Clôturé, Annulé), « En retard uniquement » et « Inclure les archivés ». Le
sens ne se choisit que dans « Tous » et « Avoirs », car les autres onglets le
fixent déjà. Dans l'onglet « Notes de frais », « Sans pièce comptable »
affiche les notes sans pièce associée ; l'administration y bascule entre
« Les miennes » et « Toutes ».

**Lignes :** le numéro mène là où l'opération se traite – la facture, le
devis, la facture d'achat ou le justificatif de frais. Les pièces reprises
sans page propre n'ont pas de lien. Pour les documents en cours, la colonne
« Échéance » indique les jours de retard et le niveau de relance atteint.
Vous relancez vos propres factures en retard directement depuis la ligne avec
« Relancer ».

**Documents reflétés :** si le système connecté ne conserve plus un document,
celui-ci est marqué **Supprimé dans la source**. Il n'entre alors dans aucun
indicateur et n'apparaît qu'avec « Inclure les archivés ». Pour les documents en
devise étrangère, la contre-valeur dans la seconde devise figure sous le
montant.

**Nouveaux documents :** en haut à droite, vous créez un devis ou une facture
ou convertissez un fichier de facture en facture électronique. « À facturer
et à relancer » ouvre la chaîne de documents avec tout ce qui reste en
attente.

**Visibilité :** le flux n'affiche que ce que vos droits dans chaque domaine
autorisent déjà. Sans droit sur les devis, par exemple, l'onglet et ses
lignes sont absents.

## Pièces en double et original

**L’externe fait foi :** si une facture a été transmise à un logiciel comptable
ou si le logiciel a attribué son numéro (Lexoffice, orgaMAX, sevDesk,
easybill), elle n’apparaît qu’une fois dans la liste – comme pièce reflétée.
Cela vaut pour chaque logiciel connecté dès qu’une pièce sortante porte le même
numéro et appartient au même client : une entreprise n’attribue un numéro de
facture qu’une seule fois. Une pièce de même numéro mais d’un autre client ne
remplace pas votre facture ; les pièces entrantes et les pièces supprimées dans
la source non plus.

**Dépenses :** dès qu’une pièce entrante est associée à une dépense, seule la
pièce compte dans les charges – avec Lexoffice comme avec sevDesk, Akaunting,
Invoice Ninja et les autres logiciels disposant d’un miroir des pièces.

**Totaux :** les pièces entrantes reflétées comptent dans les charges, les
avoirs les réduisent ; les brouillons et les pièces annulées ne comptent pas.

**Original :** le numéro d’une pièce reflétée ouvre l’original dans le
logiciel lorsqu’il existe une adresse (Invoice Ninja, Akaunting). Pour les
factures et avoirs sevDesk, les documents easybill et les factures orgaMAX,
**PDF original** en fin de ligne charge le PDF directement depuis le logiciel ;
il faut pour cela le droit de consulter les pièces et, pour le PDF orgaMAX,
l’accès à l’administration. Les pièces sevDesk sans facture (ticket de caisse,
pièce fournisseur) et les dépenses orgaMAX n’ont pas de PDF.

**orgaMAX et sevDesk :** orgaMAX reflète les factures, les factures d’acompte
et les dépenses ; les dépenses figurent comme pièces entrantes dans les
charges. Les avoirs de sevDesk figurent comme avoirs sortants et réduisent le
chiffre d’affaires – y compris les avoirs de commission et de redevance, car
sevDesk ne renvoie pas la catégorie comptable à la lecture. Les modèles récurrents d’un logiciel ne sont pas des pièces
et n’apparaissent pas dans le flux ; ils servent au rapprochement des
abonnements et licences.

## Filtrer par projet

Le filtre **Projet** n'affiche que les documents d'un projet. Les documents
provenant de logiciels de comptabilité n'ont pas de projet et disparaissent avec
ce filtre.
