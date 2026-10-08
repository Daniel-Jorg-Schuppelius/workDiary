---
title: "Gestion des licences"
topic: admin.license
version: 2
keywords:
    - clé de licence
    - forfait
    - abonnement
    - changer de forfait
    - mise à niveau
    - modules complémentaires
    - limite utilisateurs
    - période test
    - licence expirée
    - compte bloqué
    - feature flags
    - données de facturation
audience:
    - admin
    - geschaeftsfuehrung
related:
    - admin.handbook
    - admin.tenants
---

La page de licence montre le **plan** (free/pro/enterprise), les
**limites d'utilisateurs et d'organisations**, les **modules** activés
et la **date d'expiration**. La licence est la source du plan et des
modules additionnels ; une licence liée à l'organisation peut être
installée ou retirée, la licence globale servant de repli, et sans
licence valide l'installation fonctionne en plan Free. Vous pouvez
vérifier le statut, surcharger des **drapeaux de fonctionnalités** et,
si autorisé, émettre de nouvelles licences (saisies comme clés
signées, sans téléversement de fichier). Le **statut de tenant** (essai,
actif, bloqué) peut être défini manuellement ou dérivé ; en cas de
blocage, les actions en écriture sont désactivées et la limite
d'utilisateurs est appliquée à la création de membres.

## Données de facturation et changement d'offre

Sous « Données de facturation », vous gérez le destinataire de la facture,
l'e-mail, l'adresse, le numéro de TVA et la référence de commande pour la
facturation par l'exploitant. Vous y demandez aussi une autre offre ou des
modules complémentaires ; chaque organisation a une demande ouverte, que
vous pouvez retirer. L'exploitant la traite en émettant une nouvelle
licence.
