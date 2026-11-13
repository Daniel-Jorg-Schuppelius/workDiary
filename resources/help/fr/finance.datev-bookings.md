---
title: "Lot d'écritures DATEV"
topic: finance.datev-bookings
version: 7
keywords:
    - export DATEV
    - expert-comptable
    - exporter les écritures
    - EXTF
    - plan comptable
    - SKR03
    - SKR04
    - numéro de débiteur
    - clé de comptabilisation
    - compte de produits
    - export comptable
    - écritures comptables
    - contrôle fiscal
    - numéro de TVA manquant
audience: []
modules:
    - module.finance
schema: process
related:
    - invoices.manage
    - finance.transfers
    - finance.reconciliation
    - roles.buchhaltung
---

## Objectif et contexte

Le lot d'écritures DATEV remet au cabinet comptable les factures
émises, avoirs et, en option, les notes de frais approuvées d'une
période close, sous forme de fichier DATEV vérifiable (format V700).
Principe : WorkDiary ne crée **pas** de comptabilité, mais un lot de
remise propre. Si un logiciel de facturation externe (DATEV ou
Lexoffice) mène les factures, celles-ci n'entrent **pas** dans le lot
local — elles sont exclues automatiquement et signalées dans la vue
de contrôle.

## Prérequis

L'administration enregistre une fois la configuration comptable de
l'organisation :

- numéros de conseiller et de dossier,
- plan comptable (SKR03 ou SKR04) et longueur des comptes,
- compte de produits par défaut et compte distinct pour les ventes à
  0 % / exonérées,
- la base de la plage de numéros débiteurs,
- l'affectation des taux de TVA (19 %, 7 %, 0 %) aux clés d'écriture
  DATEV,
- l'indicateur de verrouillage (GoBD) et le jeu de caractères
  (habituellement ISO-8859-1).

Un numéro de débiteur peut être tenu par client ; à défaut, il est
dérivé de façon déterministe de la base de la plage et du numéro
client. Créer, finaliser et télécharger les lots revient au rôle
**comptabilité** (et aux administrateurs) ; la configuration relève
des administrateurs.

Pour la comptabilité locale, la section **Comptes** contient en outre le
**compte d'escompte** et le **compte de passage en perte (créances
irrécouvrables)** : les contreparties utilisées lorsqu'un poste ouvert est
soldé par escompte ou passé en perte. Laissés vides, la valeur par défaut du
plan comptable s'applique (SKR03 8736 et 2400, SKR04 4736 et 6930). Pour les dettes, il existe le
**compte d'escomptes obtenus** (3736 ou 5736) et le **compte d'annulation de
dettes** (autre produit, 2709 ou 4839). S'il existe
un plan comptable local, un compte saisi doit y exister ; si un compte effectif
manque, la page le signale.

## Déroulement recommandé

1. **Créer le lot :** choisir la période, inclure au besoin les notes
   de frais approuvées — un **brouillon** avec les pièces prêtes à
   écrire apparaît.
2. **Contrôler :** l'aperçu montre l'écriture par pièce — sens
   débit/crédit, comptes débiteur et de produits, clé d'écriture,
   numéro de pièce, montant TTC — avec le total. Les données
   manquantes apparaissent en **avertissement**, les clés manquantes
   en **erreur** bloquante.
3. **Finaliser :** le fichier DATEV n'est créé qu'à ce moment ; une
   empreinte SHA-256 est consignée et les pièces valent remises. Un
   lot finalisé est **immuable**.
4. **Télécharger** et transmettre au cabinet.

![Lots d’écritures DATEV avec indicateurs, configuration et création de lot](media/buchhaltung/datev-stapel.png)
*La vue des lots : indicateurs, configuration, données EXTF et « Créer un lot ».*

## Exemple pratique

En début de mois, la comptabilité crée le lot du mois précédent :
deux pièces avertissent d'un numéro de débiteur manquant — après la
saisie chez le client, les avertissements disparaissent, le lot est
finalisé et le CSV part au cabinet avec son empreinte.

## Erreurs fréquentes

- **Vouloir remettre deux fois la même facture :** les pièces
  finalisées sont verrouillées — les corrections passent par avoir ou
  pièce corrective dans le lot suivant.
- **Ignorer les avertissements :** les données manquantes ressortent
  sinon au cabinet.
- **Attendre les justificatifs dans le lot :** PDF/photos n'en font
  pas partie ; ils restent au dossier et partent séparément.

## Effets et prochaines étapes

Sont prises en compte les factures émises et payées dont la date de
pièce est dans la période ; les avoirs deviennent des écritures
inversées. Après la remise : tenir le lettrage des paiements et
n'exporter la période suivante qu'après sa clôture.

## Transmission et rapport d’accompagnement

Après la finalisation, **Saisir la transmission** consigne ce qu’il advient du lot : **transmis** au cabinet, puis **confirmé** ou **refusé** par celui-ci. Un lot refusé est transmis à nouveau une fois le point éclairci ; les corrections de pièces passent toujours par le lot suivant. Chaque étape figure avec la date, la personne et une remarque facultative dans l’historique du lot, et la liste affiche l’état actuel. Les remarques doivent rester sans données personnelles, car l’historique est immuable.

Le **rapport d’accompagnement (PDF)** résume le lot pour le cabinet : période, numéros de conseiller et de client, nombre d’écritures et de contre-passations, total, somme de contrôle du fichier, totaux par compte de produits et par code fiscal, ainsi que l’historique.

## Transfert DATEV depuis le journal

Si WorkDiary tient le grand livre, le lot d’écritures DATEV est issu des
écritures validées du journal (carte **Transfert DATEV** dans **Clôture des
périodes**). Chaque écriture devient des écritures DATEV face à une ligne
d’ancrage – le plus souvent le compte client, fournisseur ou de trésorerie –,
de sorte que DATEV la comptabilise une seule fois. Une ligne avec code de taxe
part en brut avec sa **Clé de taxe DATEV** ; les lignes de taxe disparaissent,
car DATEV comptabilise la taxe lui-même. En autoliquidation (§ 13b,
acquisition intracommunautaire), le montant reste hors taxe et DATEV
comptabilise TVA collectée et déductible. Sur un compte automatique (champ
**Automatisme DATEV** du compte), DATEV calcule la taxe d’après le compte et le
lot n’y met aucune clé. Si l’automatisme ne correspond pas à l’écriture, la clé
40 le suspend et la taxe reste une écriture distincte. Un escompte avec
correction de taxe part aussi en une seule écriture.

## Ventes sans taxe

Si une facture n'indique pas de taxe, le lot la comptabilise selon son régime
fiscal, comme la comptabilité : livraison intracommunautaire (SKR03 8125 / SKR04
4125), prestation vers un autre pays de l'UE (8336/4336), autoliquidation nationale
(8337/4337), exportation (8120/4120), vente non imposable dans un pays tiers
(8338/4338). Ce sont des comptes automatiques DATEV ; le lot n'y indique pas de clé
de taxe. Les ventes exonérées nationales restent sur le compte configuré pour les
produits exonérés.

## Contrôle fiscal avant la finalisation

L'aperçu d'un brouillon affiche le **contrôle fiscal** du lot avec un lien vers
la pièce. Les **erreurs** bloquent **Finaliser** : un enregistrement avec taux
de TVA mais sans clé BU sur un compte qui n'est pas automatique ; une
livraison intracommunautaire exonérée ou une autoliquidation dans un autre
pays de l'UE sans numéro de TVA du client ; un compte désactivé du plan
comptable local. Les **remarques** signalent les enregistrements dont le taux
diffère du code du compte automatique et l'autoliquidation nationale sans
numéro de TVA. Sont des comptes automatiques ceux qui portent un code de TVA
par défaut dans le plan comptable local ; sans plan local, ceux du modèle du
plan configuré. Si une erreur est justifiée dans un cas particulier, une
personne disposant de **Lever le contrôle fiscal avant l'export DATEV** la
lève avec un motif ; la levée figure dans le journal du lot et vaut aussi
lorsque la même pièce figure dans un nouveau brouillon. Le contrôle ne modifie
pas le lot.
