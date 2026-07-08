---
title: "Migration de l'ancien système"
topic: admin.legacy-migration
version: 1
audience:
    - admin
related:
    - admin.import
    - admin.data-transfer
    - admin.handbook
---

La migration reprend les données de l'ancien système dans WorkDiary et
affiche l'avancement par domaine de données ; elle nécessite une connexion
configurée à la base de l'ancien système, sinon la zone apparaît comme
« non configurée ». La vue d'ensemble compare, pour les **utilisateurs**,
**entrées de journal**, **astreintes** et **interventions d'urgence**, le
nombre d'enregistrements présents dans l'ancien système et déjà importés.
L'import se lance par domaine et exécute en arrière-plan la commande
`legacy:import` ; les enregistrements déjà importés sont liés par un
identifiant hérité, de sorte que les exécutions répétées ne créent pas de
doublons. L'écriture dépend de la configuration (`legacy_write_enabled`) ;
en cas d'échec, consultez les fichiers journaux.
