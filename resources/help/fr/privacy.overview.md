---
title: "Aperçu de la gestion de la protection des données"
topic: privacy.overview
version: 1
keywords:
    - RGPD
    - registre des traitements
    - sous-traitant
    - contrat de sous-traitance
    - mesures techniques et organisationnelles
    - droits des personnes
    - demande d'accès
    - violation de données
    - notification sous 72 heures
    - politique de conservation
    - legal hold
audience: []
modules:
    - module.datenschutz
related:
    - documents.manage
    - isms.overview
    - glossary.core
    - privacy.portal
---

Le module de protection des données couvre le registre des traitements
(art. 30 RGPD, versionné avec instantanés immuables à chaque
validation), les sous-traitants et contrats (art. 28), les demandes des
personnes concernées (art. 15–21, délai de **30 jours**, vérification
d'identité, décision documentée), les mesures techniques et
organisationnelles ainsi que les violations de données (délai de
72 heures ; la notification à l'autorité et la communication aux personnes
concernées (art. 34) sont consignées séparément). Les contenus des demandes sont stockés **chiffrés** avec une
clé propre à chaque dossier ; il n'existe **volontairement aucun
contournement admin** — les droits doivent être attribués explicitement.
Attention : après le délai de conservation, la destruction de la clé
(crypto-shredding) rend les contenus **irrécupérables**, et les versions
validées du registre ne sont plus modifiables. Les justificatifs se
gèrent dans le module **Documents**.

**Conservation, suppression et gel juridique :** Sous **Conservation et
suppression**, le concept de suppression propose les données échues ; rien
n'est supprimé ni anonymisé sans confirmation en deux étapes. Si une procédure
(personne concernée ou contentieux) est en cours, vous posez sous **Gel
juridique** un gel sur la personne ou le client – avec motif obligatoire et
référence de dossier facultative. Tant qu'il est actif, aucune proposition de
suppression n'est créée, les suppressions confirmées, l'anonymisation et la
suppression de comptes ou de clients sont refusées, et les points de
localisation de la personne sont conservés. Lors d'une fusion de clients, le
gel passe au client cible. Il n'est levé qu'avec un motif ; les deux restent
visibles dans le journal de la personne ou du client.

Le rapport d’accès (art. 15/20) peut aussi être créé pour les **membres du
club** si votre organisation utilise la gestion associative : données de base
avec représentants légaux, adresses et coordonnées bancaires, ainsi qu’un
aperçu des données du club (périodes d’adhésion, groupes, présences,
cotisations, dons, grades, performances et autres) avec un extrait par
domaine. La recherche trouve les membres par nom, e-mail ou numéro de membre.
