---
title: "Classifications & règles obligatoires"
topic: admin.classifications
version: 1
audience:
    - admin
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
