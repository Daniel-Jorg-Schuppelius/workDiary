---
title: "Factures & pièces"
topic: invoices.manage
version: 6
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
   sources** sont dépliables (1,50 h = 1:30 h). Pour un article avec poids
   de cuivre, la case **supplément cuivre** du dialogue de ligne ajoute le
   supplément au cours DEL du jour comme ligne distincte.
4. Émettre ou envoyer — PDF, envoi et synchronisation externe sont
   des sorties du même état documenté.
5. En cas de retard de paiement, utiliser la **relance** : le niveau 1
   crée un rappel de paiement en PDF distinct avec récapitulatif des
   créances, frais optionnels et échéance ; le courriel contient la
   lettre et la facture d'origine. Aucune nouvelle pièce n'est créée.

**Facture électronique.** La XRechnung est générée en syntaxe UBL ; si un
destinataire exige CII, choisissez sur le client ou lors de l’envoi le format
de livraison « XRechnung (XML, syntaxe CII) ». Via Peppol, c’est toujours UBL.
Sans numéro de TVA — par exemple en tant que petite entreprise selon le § 19
UStG — le numéro fiscal des données de facturation électronique suffit : il est
également indiqué comme identifiant du vendeur, exigé par le contrôle du
destinataire.

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

## Facture libre sans temps

Dans la boîte de dialogue de création, **« Composer les lignes soi-même »** est
sur un pied d'égalité avec la reprise des temps ou de la consommation de
matériel. Le brouillon ne demande que le client (projet, client final et délai
de paiement facultatifs) et démarre vide : il peut être enregistré et complété
plus tard, mais ni émis ni envoyé tant qu'il n'a aucune ligne — cela vaut pour
l'émission, l'e-mail, Peppol et le transfert Lexoffice. Un double clic sur
« Créer le brouillon » ne crée pas de seconde facture.

**Articles, matériel et prestations.** Une ligne est un article (avec variante
facultative), du matériel ou du texte libre — par exemple « Montage forfait »
ou une fabrication spéciale sans ordre de fabrication. Désignation, numéro
d'article, unité et prix sont figés comme valeurs du document ; les
modifications ultérieures de la fiche article ne changent pas le document. Un
prix manquant doit être saisi consciemment (0,00 est admis comme ligne
gratuite) ; un prix d'article en devise étrangère n'est jamais converti en
silence. Les lignes d'article et de texte libre ne mouvementent **aucun
stock** ; les livraisons passent par stock/livraison.

**Facturer des livraisons de fabrication.** Avec le module stock actif,
« Reprendre une livraison » importe les livraisons effectuées du client avec
cible de facturation locale — chacune entièrement comme une ligne, quantité
liée à la source et prix de vente de la livraison (pas le coût de
fabrication). Une livraison ne peut figurer que dans un seul brouillon à la
fois ; l'émission la marque facturée, retirer la ligne, abandonner le brouillon
ou une annulation totale la libèrent, et l'origine reste visible sur le
document. Les avoirs partiels ne libèrent rien ; le stock reste inchangé pour
toutes les opérations de facturation.
