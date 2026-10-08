---
title: "Journal d'audit"
topic: audit.log
version: 2
keywords:
    - historique des modifications
    - journal des activités
    - journal des changements
    - traçabilité
    - qui a modifié quoi
    - inviolable
    - chaîne de hachage
    - GoBD
    - conformité
    - journal de traçabilité
audience:
    - admin
related:
    - admin.security
    - admin.handbook
    - privacy.overview
---

Le journal d'audit (`/audit`) est le protocole de contrôle à valeur
probante des modifications et des actions effectuées dans le système.
Les entrées sont **append-only** (ajout uniquement) et chaînées entre
elles par une **chaîne de hachage SHA-256** (GoBD) ; elles ne sont
jamais écrites brutes et ne peuvent pas être modifiées ou supprimées
après coup.

**Filtres** : la liste peut être restreinte par

- **Action** (par ex. créé, modifié, supprimé, archivé, restauré ainsi
  que les événements d'import),
- **Type** de l'objet concerné (notamment entrée de journal,
  commentaire, client, fournisseur, exécution d'import, séquence de
  numérotation),
- **Utilisateur** et
- **Période** (via le filtre de date global).

Pour chaque entrée, vous voyez l'horodatage, l'utilisateur à l'origine
de l'action, l'action, l'objet, les modifications concrètes et
l'adresse IP.

**Vérifier l'intégrité** : la chaîne de hachage est vérifiée par la
commande console `php artisan audit:verify`. Celle-ci valide le
chaînage et se termine avec le code de sortie 1 en cas de rupture –
idéal pour cron/CI. Maintenez la commande durablement au vert ; une
rupture indique une manipulation ou une erreur de données. Avec
`--chain`, il est possible de vérifier une seule chaîne de manière
ciblée (`audit_logs` ou `organization_audit_logs`).

Remarque : le journal d'audit est un outil en lecture seule. Il
affiche des opérations, mais ne modifie lui-même aucune donnée.
