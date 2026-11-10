---
title: "Factures & pièces"
topic: invoices.manage
version: 13
keywords:
    - créer une facture
    - établir une facture
    - facture client
    - facturation
    - annuler une facture
    - avoir
    - facture électronique
    - XRechnung
    - Peppol
    - lien de paiement
    - paiement en ligne
    - surcharge cuivre
    - Stripe
    - Mollie
    - SumUp
    - documents liés
    - chaîne de documents
    - transmettre la facture à Lexoffice
    - numéro provisoire
    - demande sur facture
    - justificatif de prestations
    - feuille d’heures
    - pièces justificatives
    - administration publique
    - Leitweg-ID
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

## Paiement en ligne

Si un prestataire de paiement (Stripe, Mollie ou SumUp) est configuré
comme plugin, les factures émises portent un lien de paiement avec un QR
code ; la même adresse figure dans l'e-mail via l'espace réservé
{{payment_link}} et dans le portail client. Le lien passe toujours par
workDiary : la page de paiement du prestataire n'est créée qu'à
l'ouverture, pour le montant alors restant dû, si bien qu'un code imprimé
reste juste après des paiements partiels.

Dès que le prestataire confirme le paiement, la facture est considérée
comme payée ou partiellement payée — comme avec une opération bancaire
affectée ; un remboursement l'annule. Avec la comptabilité locale, le
paiement apparaît dans la boîte de comptabilisation (fonds en transit
contre la créance, la commission du prestataire en frais bancaires et de
paiement) ; vous comptabilisez le versement ultérieur du prestataire sur
le compte de fonds en transit. Un acompte en ligne issu de la location est
enregistré dès la date du paiement comme acompte reçu, avec TVA ; la
facture qui l'impute solde l'acompte et ne porte en créance que le reste
ouvert. Vous désactivez le lien sur la facture et dans l'e-mail dans les
paramètres de l'organisation, sous « Paiement en ligne » ; il reste dans
le portail client.

## Annuler et facturer à nouveau

Un brouillon est annulé directement, une facture émise par une pièce
d'annulation avec son propre numéro. Les deux voies libèrent tout ce que la
facture a facturé, comme l'annulation d'une transmission : temps saisis,
matériel, déplacements, débours, positions de location, livraisons et le
complément jusqu'au temps cible du projet. Un acompte en ligne de la location
revient à sa réservation et est imputé sur la facture suivante. Les positions
de la facture annulée restent inchangées ; la facture suivante pour la période
contient de nouveau les heures et le complément. Une facture payée est
corrigée par une facture rectificative. Elle ne libère rien, car elle peut ne
créditer qu'une partie de la facture.

Une facture émise que seul un acompte en ligne couvre en partie s'annule elle
aussi par une pièce d'annulation : l'acompte revient à sa réservation et est
imputé sur la facture suivante. Si un autre paiement couvre la facture en
partie – une opération bancaire dans le rapprochement des paiements (y compris
un escompte accepté), un paiement en caisse, un paiement en ligne de la
facture ou un lettrage du poste ouvert –, l'annulation reste bloquée et la
facture en indique la raison. Annulez d'abord ce paiement ; une fois la
facture entièrement payée, corrigez-la par une facture rectificative.

Avec la comptabilité locale, la pièce d'annulation solde exactement le poste
ouvert de la facture annulée, après un acompte le reste après l'acompte ; les
autres postes ouverts du client restent intacts. L'acompte est ensuite de
nouveau ouvert et se solde avec la facture suivante. Comptabilisez donc la
facture avant sa pièce d'annulation ; sinon la pièce d'annulation attend dans
la boîte de comptabilisation avec un avertissement. Une facture annulée avec
pièce d'annulation y apparaît aussi pour être comptabilisée, car les deux
pièces font partie du journal. La pièce d'annulation ne compte pas comme un
paiement, pas même dans les aperçus fondés sur les encaissements
(recettes-dépenses, TVA sur les encaissements).

## Documents liés

En bas de page, **Documents liés** liste tous les documents rattachés à
celui-ci : le devis et ses versions, les factures, acomptes et la facture
finale, avoirs et annulations, bons de livraison, ordres de fabrication et
transmissions. Si un logiciel de comptabilité a repris un document, son
équivalent y figure avec le numéro du logiciel. Les liens signalés par le
logiciel lui-même, par exemple un acompte créé dans Lexoffice, apparaissent
aussi. La colonne **Lien** indique ce que le document représente pour
celui-ci ; « via … » nomme le document par lequel le lien existe.

## Numéro du logiciel de comptabilité

Si un logiciel de comptabilité attribue les numéros de facture (page
**Plages de numéros**), le brouillon porte un numéro provisoire et la facture
affiche une remarque. **Émettre** – aussi l'envoi d'un brouillon par e-mail –
transmet la facture au logiciel et reprend son numéro avant que le PDF et
l'objet ne soient créés. Lexoffice et easybill reprennent la confirmation de commande ou le devis comme document précédent ; orgaMAX crée la facture via une commande et la verrouille. L'avoir et l'annulation sont
transmis avec référence à la facture transmise ; pour sevDesk et easybill, la facture doit se trouver dans le logiciel ; orgaMAX ne reprend ni l'un ni l'autre. Si le logiciel refuse, le document
reste un brouillon et le message en indique la raison. Créez dans ce cas les
factures d'acompte et de solde dans le logiciel.

## Demandes concernant des factures

Lorsqu'un client répond à une facture par e-mail, WorkDiary enregistre lors
de la relève de la boîte aux lettres une **demande sur facture** : dans
l'historique de la facture, comme note de communication chez le client et
dans la section **Demandes** de la page de la facture. Sont reconnues la
réponse à l'e-mail de facture envoyé par WorkDiary ainsi qu'un numéro de
facture ou de document dans l'objet ou le texte – y compris pour les
documents émis par Lexoffice, sevDesk, easybill ou un autre logiciel
connecté. Pour un numéro, l'expéditeur doit appartenir au dossier (client,
interlocuteur, compte portail ou destinataire de l'e-mail de facture) ; sinon
l'e-mail arrive marqué dans la boîte d'affectation, de même en présence de
plusieurs numéros. Chaque message n'est enregistré qu'une seule fois, les
messages d'absence jamais.

La liste de travail **Demandes sur factures** (bouton dans le flux des
documents) affiche les demandes ouvertes et répondues de toutes les factures
et de tous les documents ; **Marquer comme répondue** clôt une demande,
**Rouvrir** la ramène. Les deux figurent dans l'historique de la facture.

## Pièces justificatives et justificatif de prestations

Les feuilles d’heures et justificatifs de prestations voyagent intégrés à la
facture électronique : dans la XRechnung, dans le PDF ZUGFeRD et via Peppol.
Les administrations et le fisc l’exigent : une pièce jointe séparée ou un lien
ne fait pas partie de la facture. La carte **Pièces justificatives** de la page
de facture propose trois voies tant que la facture est un brouillon :

- **Créer le justificatif de prestations** produit un PDF à partir des temps
  facturés : par position la date, la personne, l’heure, la durée et
  l’activité, avec les validations des feuilles d’heures signées. Sur demande,
  les feuilles signées et un tableau CSV s’y ajoutent (organisation, section
  **Facture électronique : pièces et destinataires**).
- **Ajouter une pièce** accepte votre propre fichier : PDF, PNG, JPEG, CSV, XLSX
  ou ODS sans mot de passe. Les destinataires n’acceptent pas d’autres formats.
- **Ajouter un lien** sert seulement aux portails, comme les grandes annexes
  d’OZG-RE. Sur le plan fiscal, un lien ne remplace pas une pièce intégrée.

WorkDiary nomme les fichiers lui-même (`nachweis-1-…`) pour qu’ils soient
uniques. **Émettre** fige les pièces ; un justificatif généré est recréé avec le
numéro définitif. Ensuite, les pièces ne peuvent plus être modifiées ni
retirées. Un envoi en PDF joint les pièces comme fichiers séparés au même courriel.

La facture électronique indique la date de prestation dans sa partie
structurée : la date ou la période des positions, sinon la date de facture. La
description des prestations reste dans les positions ; le justificatif la
complète.

### Clients publics

Dans la fiche client, réglez le **profil de destinataire de la facture
électronique** sur « Administration publique ». Alors :

- La Leitweg-ID (référence de l’acheteur) est obligatoire.
- Par courriel ne part que la XRechnung, sans PDF à côté ; la boîte d’envoi ne
  propose que ces formats. L’envoi électronique émet d’abord le brouillon pour
  que le XML porte les pièces figées.
- Le justificatif de prestations est joint automatiquement à l’émission si la
  facture porte des temps.
- La facture électronique avec pièces peut peser au plus 10 Mo ; le contrôle le
  signale au-delà. La limite se règle par client, de même que l’envoi du
  justificatif toujours ou jamais.

Si un logiciel de comptabilité attribue le numéro, les pièces restent dans
WorkDiary : WorkDiary ne les transmet pas à Lexoffice, sevDesk, easybill ou
orgaMAX. Qui envoie la facture depuis le logiciel les y joint à la main ; la
boîte des intégrations le rappelle. Le transfert propose **Justificatif de
prestations** au téléchargement.
