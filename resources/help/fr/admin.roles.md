---
title: "Rôles et droits"
topic: admin.roles
version: 1
audience:
    - admin
related:
    - admin.handbook
    - admin.security
    - roles.admin
---

La gestion des droits comprend quatre volets : **permissions**
(catalogue en lecture seule au schéma `ressource.action`), **rôles**
(ensembles de permissions adaptables par organisation), **groupes**
(regroupement d'affichage sans effet fonctionnel) et **membres**
(attribution des rôles). Démarche typique : créer ou copier un rôle,
ajuster les permissions, l'attribuer aux membres, puis vérifier avec
un compte de test. Principes de sécurité : un rôle admin global sans
lien d'organisation agit sur toute la plateforme et ne doit **jamais**
être attribué via des permissions délégables (risque d'escalade) ;
appliquez le principe du moindre privilège, et notez que les modules
sensibles (protection des données, lanceurs d'alerte) n'ont pas de
contournement admin — ces droits doivent être attribués explicitement.
