---
title: "Migration de l'ancien système"
topic: admin.legacy-migration
version: 2
keywords:
    - données historiques
    - reprise de données
    - migration des données
    - importer anciennes données
    - système précédent
    - bascule
    - migration
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

Le **portail support** est repris dans une **organisation cible** au
choix : les accès clients sont rapprochés des clients existants par le
nom de l'entreprise ou créés comme clients ; les commandes et les archives
deviennent des tickets de service numérotés KP-… et conservent
rendez-vous, technicien, réponse et note interne. Les mots de passe ne
sont pas repris ; l'accès au portail client s'accorde par invitation dans
le nouveau système. Une nouvelle exécution met à jour les tickets déjà
repris. Les heures de l'ancien système sont des heures locales et sont
converties à l'import. Seule l'exploitation de la plateforme ouvre cette
page, car la base de l'ancien système appartient à toute l'installation.
