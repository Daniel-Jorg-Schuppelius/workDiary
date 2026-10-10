---
title: "Ordres de fabrication"
topic: manufacturing.orders
version: 2
keywords:
    - ordre de production
    - nomenclature
    - recette
    - besoins en matières
    - MRP
    - déclaration de production
    - rebut
    - sous-traitance
    - documents douaniers
    - facture pro forma
    - facture commerciale
    - bon de livraison
    - étiquette d'expédition
    - statut de l'envoi
    - annuler une expédition
audience: []
modules:
    - module.lager
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
livrer exigent le droit **Enregistrer des mouvements de stock**.

## Expédition sur la livraison

Avec une connexion d'expédition active (voir « Connexions d'expédition DHL,
UPS et FedEx »), vous créez sur une livraison avec client, via **Expédier**, un
ordre d'expédition avec son étiquette. La livraison affiche ensuite le statut,
par exemple **Expédition : Étiquette créée**, avec le transporteur et le numéro
de suivi. En survolant le statut avec la souris, vous voyez quand il a été
vérifié pour la dernière fois auprès du transporteur. À côté du statut se
trouvent :

- **Télécharger l’étiquette** : télécharge de nouveau l'étiquette d'expédition.
- **Consulter le statut de l’envoi** : interroge immédiatement le transporteur
  sur la situation actuelle – plus disponible pour **Livré** ou **Annulé**.
  WorkDiary vérifie en outre régulièrement les envois ouverts de lui-même.
- **Annuler l’expédition** : uniquement au statut **Brouillon** ou **Étiquette
  créée** et après confirmation. L'étiquette devient invalide. Vous pouvez
  ensuite créer un nouvel ordre d'expédition, et les colis de la livraison
  redeviennent modifiables.

Créer un ordre d'expédition, consulter le statut de l'envoi et annuler
l'expédition exigent le droit **Enregistrer des mouvements de stock**.

## Documents douaniers pour les envois hors de l’UE

Pour chaque livraison avec destinataire, « Documents douaniers » crée une
facture commerciale (en cas de vente) ou une facture pro forma (cadeau,
échantillon, marchandises retournées, réparation et autres motifs) au format
PDF. La boîte de dialogue indique si la destination se trouve hors de l’UE et
enregistre le motif choisi sur la livraison. Le document indique la
désignation, la nomenclature douanière, le pays d’origine, la quantité, le
poids net et la valeur ; le poids brut et le nombre de colis proviennent des
colis saisis. Renseignez la nomenclature douanière, le pays d’origine et le
poids net sur l’article, et le numéro EORI de l’expéditeur dans les paramètres
de l’organisation. S’il manque une donnée, la boîte de dialogue l’indique et
ne crée pas de document. Les documents douaniers ne remplacent pas une
déclaration d’exportation électronique.

La page **Bons de livraison** liste toutes les livraisons de la période choisie
— avec PDF du bon, envoi par e-mail, documents douaniers et état d'expédition,
sans passer par chaque ordre de fabrication. Le filtre « Non expédiées » montre
ce qui attend encore une étiquette.
