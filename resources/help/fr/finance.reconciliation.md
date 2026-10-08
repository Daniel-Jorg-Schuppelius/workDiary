---
title: "Rapprochement des paiements"
topic: finance.reconciliation
version: 3
keywords:
    - rapprochement bancaire
    - importer un relevé
    - lettrage
    - encaissements
    - facture payée
    - opérations bancaires
    - CAMT
    - MT940
    - escompte
    - paiement partiel
    - référence RF
audience: []
modules:
    - module.finance
related:
    - finance.transfers
    - roles.buchhaltung
    - glossary.core
---

Le **Rapprochement des paiements** importe des relevés bancaires au format
**CAMT.053** (de préférence) ou **MT940** (solution de repli), normalise
les opérations bancaires dans une **zone de vérification** et propose des
factures ouvertes ou des notes de frais validées pour l'affectation.
**L'import seul ne modifie aucune pièce** – seule la **confirmation** fait
passer `Facture → payée` (avec la date de paiement) ou marque une note de
frais comme remboursée.

## Déroulement

1. **Importer :** téléverser le fichier bancaire (en option, choisir un de
   vos propres comptes bancaires ; sinon, il est affecté automatiquement
   via l'IBAN). Les fichiers identiques sont rejetés comme doublons grâce
   au hash du fichier ; les opérations déjà connues sont ignorées lors d'un
   nouvel import.
2. **Vérifier :** dans le détail du relevé, chaque opération affiche un
   statut (Ouvert/Affecté/Mis de côté/Non affectable) et – si elle est
   ouverte – des **Suggestions d’affectation** avec un score de
   correspondance et une justification (numéro de facture, montant,
   escompte, correspondance IBAN, proximité de date).
3. **Confirmer :** avec *Confirmer*, l'affectation est créée et son effet
   est appliqué à la pièce. Sinon, *Mettre de côté* (par ex. frais
   bancaires) ou *Non affectable*.
4. **Annuler :** une affectation confirmée est **réversible** – elle est
   levée et l'effet sur la pièce (payée/remboursée) n'est annulé que si
   cette opération constituait le paiement. **L'opération bancaire
   elle-même n'est jamais modifiée.**

## Cas pratiques

- **Escompte :** un paiement insuffisant dans la limite de la tolérance
  d'escompte (3 % par défaut) est considéré comme un paiement complet.
- **Tolérance de centimes :** des écarts d'arrondi jusqu'à 2 centimes
  n'empêchent pas une suggestion.
- **Paiement partiel/trop-perçu :** ils sont gérés comme un type
  d'affectation distinct ; en cas de paiement partiel, la facture reste
  ouverte.
- **Chaîne des soldes :** le solde d'ouverture + la somme des opérations
  est contrôlé par rapport au solde de clôture ; les écarts sont signalés
  par un avertissement.
- **Devise étrangère :** les opérations dans une autre devise sont
  uniquement détectées et marquées pour une clarification manuelle.

## Protection des données

Les données bancaires à caractère personnel (nom, IBAN, motif du paiement
de la contrepartie) sont stockées sous forme **chiffrée**. Le
rapprochement s'effectue exclusivement à partir de dérivés non chiffrés
(hash de l'IBAN, numéros de facture extraits, montants, dates). Chaque
action d'affectation est consignée de manière probante dans une chaîne
de hachage.

## Autorisations

- **Importer un fichier bancaire** et **confirmer/annuler des
  affectations :** rôle *Comptabilité* (ainsi que l'administration).
- **Gérer ses propres comptes bancaires :** administration uniquement.

## Référence de paiement RF

Lorsque la référence de paiement RF est activée dans les paramètres de
l'organisation, chaque facture porte une référence créancier RF
(ISO 11649) issue de son numéro — dans les informations de paiement et
dans le GiroCode sous forme de référence structurée. Si le client effectue
son virement avec cette référence, le rapprochement des paiements
reconnaît la facture grâce à elle, même écrite par groupes de quatre.
