---
title: "Factures & pièces"
topic: invoices.manage
version: 3
audience: []
modules:
    - module.vertrieb
schema: process
related:
    - contacts.manage
    - projects.manage
    - finance.datev-bookings
    - finance.transfers
    - travel-expenses.manage
---

## Objectif et contexte

La vue des factures gère les factures locales et les pièces
raccordées. Le circuit directeur dépend de l'organisation et de
l'intégration de facturation utilisée : par période, c'est WorkDiary
qui émet les factures ou exactement un système externe — jamais les
deux à la fois.

## Prérequis

- Données de base vérifiées : client, adresse du destinataire,
  informations fiscales.
- **Période de prestation et lien projet** des positions à facturer.
- Le droit de créer des factures ; pour les relances, le rôle
  finances correspondant.

## Déroulement recommandé

1. Choisir client et période — le dialogue de création affiche un
   **aperçu** des positions à venir (nombre, durée en format horaire
   et décimal, montant, alerte retardataires).
2. Exclure au besoin des saisies de temps par case à cocher — elles
   restent ouvertes et reviennent au prochain passage.
3. Vérifier et compléter le brouillon ; par position, les **saisies
   sources** sont dépliables (1,50 h = 1:30 h).
4. Émettre ou envoyer — PDF, envoi et synchronisation externe sont
   des sorties du même état documenté.
5. En cas de retard de paiement, utiliser la **relance** : le niveau 1
   crée un rappel de paiement en PDF distinct avec récapitulatif des
   créances, frais optionnels et échéance ; le courriel contient la
   lettre et la facture d'origine. Aucune nouvelle pièce n'est créée.

## Exemple pratique

En fin de mois, la comptabilité choisit « Müller GmbH » et le mois
précédent : l'aperçu montre 14 positions et signale deux temps
retardataires. Une saisie contestée est exclue et repart
automatiquement au prochain passage — la facture part sans débat.

## Erreurs fréquentes

- **Modifier en silence des pièces envoyées ou remises :** les pièces
  émises, comptabilisées ou remises sont immuables — les erreurs
  passent par l'annulation ou la correction.
- **Écraser numéros de pièce ou montants** au lieu de corriger — la
  traçabilité est détruite.
- **Double souveraineté de facturation :** si un système externe mène
  la facturation, les factures locales n'existent volontairement pas
  en parallèle.

## Effets et prochaines étapes

Les factures émises alimentent postes ouverts, relances et remise
comptable. Ensuite : vérifier encaissements et lettrage, puis créer
le lot DATEV pour le cabinet.
