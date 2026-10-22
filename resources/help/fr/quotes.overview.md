---
title: "Devis"
topic: quotes.overview
version: 7
keywords:
    - créer un devis
    - estimation
    - proposition commerciale
    - offre de prix
    - envoyer un devis
    - accepter un devis en ligne
    - acceptation partielle
    - durée de validité
    - confirmation de commande
    - transformer un devis en facture
    - options
    - nouvelle version du devis
    - documents liés
    - chaîne de documents
    - numéro de devis
    - devis vers Lexoffice
    - numéro de confirmation
    - bon de livraison
    - livraison partielle
    - numéro de bon de livraison
audience: []
modules:
    - module.vertrieb
related:
    - invoices.manage
---

Les devis suivent un cycle de vie fixe : brouillon → validation → envoi →
décision du client → transformation en facture. La vue d'ensemble filtre
par client et par statut (brouillon, validé, envoyé, accepté,
partiellement accepté, refusé, expiré).

**Brouillon :** Un devis est créé avec un client, un projet optionnel, un
délai de validité et un texte de conditions. Les lignes (description,
quantité, unité, prix unitaire, taux de taxe) ne peuvent être ajoutées,
modifiées et supprimées qu'à l'état de brouillon ; les totaux sont alors
recalculés automatiquement. Certaines lignes peuvent être marquées comme
optionnelles — si le client ne refuse que celles-ci, il s'agit malgré
tout d'une acceptation intégrale.

**Validation & envoi :** Après la validation, le devis est marqué comme
envoyé. Un lien d'acceptation est alors créé pour le client : il n'est
affiché en clair qu'une seule fois, seule une empreinte de vérification
est enregistrée — le lien doit donc être copié immédiatement et transmis
avec le message d'accompagnement du devis (e-mail ou courrier). À partir
de l'envoi, l'état est immuable sur le plan métier ; les modifications
passent exclusivement par une nouvelle version qui référence la
précédente. La chaîne de versions complète reste visible sur le devis.

**Décision du client :** Via le lien, le client peut consulter le devis
sans connexion et l'accepter, l'accepter partiellement (sélection de
lignes individuelles) ou le refuser. Alternativement, l'opérateur
documente en interne une décision communiquée par téléphone ou par écrit,
avec un motif optionnel en cas de refus. Après l'expiration du délai de
validité, aucune acceptation n'est plus possible ; les devis expirés,
refusés ou envoyés peuvent être transformés en nouvelle version et
proposés à nouveau.

**Facture :** Les devis acceptés ou partiellement acceptés sont
transformés d'un clic en facture brouillon. Seules les lignes acceptées
sont reprises ; la facture suit ensuite le processus de facturation
normal (vérification, émission, envoi). Les factures qui en résultent
restent liées au devis, de sorte que le chemin du devis à la pièce reste
traçable.

Les brouillons de devis non envoyés peuvent être supprimés ; tout ce qui
suit l'envoi est conservé comme historique.

**Facturé dans le logiciel comptable :** si un devis accepté est facturé
dans le logiciel comptable et non ici, marquez-le via le menu de la page du
devis avec **Facturé dans le logiciel comptable**, au besoin avec le numéro
de facture utilisé là-bas. Le devis quitte alors la liste **Devis acceptés
sans facture** ; le marquage peut être supprimé. L’action est disponible
tant qu’aucune facture n’existe pour le devis.

**PDF :** chaque devis peut être téléchargé en PDF (avec repérage des
options et ventilation de la TVA) ; les devis envoyés conservent durablement
le design du moment de l'envoi. La confirmation de commande est un document
distinct (voir ci-dessous).

## Documents liés

En bas de page, **Documents liés** liste tous les documents rattachés à
celui-ci : le devis et ses versions, les factures, acomptes et la facture
finale, avoirs et annulations, bons de livraison, ordres de fabrication et
transmissions. Si un logiciel de comptabilité a repris un document, son
équivalent y figure avec le numéro du logiciel. Les liens signalés par le
logiciel lui-même, par exemple un acompte créé dans Lexoffice, apparaissent
aussi. La colonne **Lien** indique ce que le document représente pour
celui-ci ; « via … » nomme le document par lequel le lien existe.

## Numéro de devis du logiciel de comptabilité

Si vous choisissez un logiciel de comptabilité (Lexoffice, easybill ou orgaMAX) pour
**Devis** sur la page **Plages de numéros**, un nouveau devis porte d'abord un
numéro provisoire. Lors de l'**envoi**, WorkDiary crée le devis dans le
logiciel, l'y finalise et reprend son numéro ; le PDF n'est créé qu'ensuite.
Si le transfert échoue, le devis reste validé et vous pouvez renouveler
l'envoi. Une nouvelle version est un nouveau devis avec son propre numéro dans
le logiciel. Lexoffice marque les positions optionnelles comme optionnelles ;
easybill les reprend sans calcul, orgaMAX ne les reprend pas.

## Confirmation de commande

Pour un devis accepté, **Créer la confirmation de commande** crée un
document distinct avec les positions acceptées. La confirmation de commande a
sa propre plage de numéros (**Plages de numéros**, plage « Confirmation de
commande »). **Confirmer** lui attribue son numéro ; si un logiciel de
comptabilité (Lexoffice, easybill ou orgaMAX) attribue le numéro, WorkDiary la
transfère à ce moment. Ensuite, le PDF et l'envoi par e-mail sont disponibles.
L'annulation la retire dans WorkDiary ; elle reste dans le logiciel.

## Bon de livraison

Une confirmation de commande confirmée propose **Créer un bon de livraison**.
Le bon de livraison reprend les quantités non encore livrées, sans prix. Pour
une livraison partielle, ajustez les quantités sous **Modifier les
quantités** ; 0 retire une position de ce bon, elle reste ouverte pour le
suivant. **Livrer** fixe le numéro de la plage « Bon de livraison » ; si un
logiciel de comptabilité (Lexoffice ou easybill) attribue le numéro, WorkDiary
transmet le bon de livraison à ce moment. Ensuite, le PDF et l'envoi par
e-mail sont disponibles. Les bons de livraison de la fabrication naissent à
chaque livraison (voir ordres de fabrication).

## Consulté et signature

Lorsque le client ouvre le devis via le lien ou dans le portail client, l’en-tête indique **« Consulté par le client le … »** ; c’est la première ouverture d’un devis envoyé qui compte. Lors de l’acceptation, le client peut indiquer son **nom** et **signer**, de manière facultative. La signature est enregistrée comme image sur le devis et figure avec le nom et la date dans l’état d’acceptation figé ; l’acceptation sans signature reste possible.
