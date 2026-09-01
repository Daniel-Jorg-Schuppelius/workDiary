---
title: "Lot d'écritures DATEV"
topic: finance.datev-bookings
version: 2
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
