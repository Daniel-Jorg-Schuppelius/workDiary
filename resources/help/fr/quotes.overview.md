---
title: "Devis"
topic: quotes.overview
version: 1
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

**PDF & confirmation de commande :** chaque devis peut être téléchargé
en PDF (avec repérage des options et ventilation de la TVA) ; les devis
envoyés conservent durablement le design du moment de l'envoi. Pour les
devis (partiellement) acceptés, une confirmation de commande en PDF est
également disponible et confirme exactement les positions acceptées.
