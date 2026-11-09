---
title: "Classifications & règles obligatoires"
topic: admin.classifications
version: 2
keywords:
    - listes de valeurs
    - catégories
    - listes déroulantes
    - nomenclatures
    - référentiels
    - types de commande
    - types de défaut
    - causes
    - champs obligatoires
audience:
    - admin
    - geschaeftsfuehrung
    - personalverwaltung
    - teamleitung
    - user
    - aussendienst
    - callcenter
    - support
related:
    - catalog.entry-types
    - diary-entries.create
    - admin.import
    - glossary.core
---

Les classifications sont des listes de valeurs à l'échelle de
l'organisation, par domaine (types de commande, activités, types de
défaut, causes, résultats, priorités, etc.) ; chacune comporte un code, un
libellé et, en option, couleur, icône et ordre de tri. Les valeurs fournies
par la plateforme sont disponibles pour toutes les organisations : vous
pouvez les surcharger, ajouter vos propres valeurs, réordonner un domaine
ou désactiver une valeur par défaut ; l'import CSV permet de créer ou de
mettre à jour de nombreuses valeurs d'un coup (colonnes obligatoires :
domaine, code, libellé). Les règles obligatoires lient un type de commande
à un domaine requis et fixent la phase où l'indication est exigée — à la
création, avant la clôture ou avant la signature — ainsi que le caractère
bloquant ou indicatif, le nombre minimal/maximal de valeurs et une
condition JSON.

D’autres domaines arrivent avec les profils sectoriels, par exemple types de
prestation, niveaux de dépendance et canaux de communication (soins), types
d’événement (traiteur) et supports de charge (transport). Le domaine
**Catégories d’actifs** complète les catégories informatiques générales du
formulaire des actifs : une valeur de l’organisation avec un code propre y
apparaît en plus, une valeur reprenant le code d’une catégorie générale en
remplace le libellé, et une valeur désactivée la masque. Une catégorie
utilisée par un actif peut seulement être désactivée, pas supprimée.
