---
title: "Gestion des urgences & des crises"
topic: crisis.overview
version: 1
audience: []
related:
    - documents.manage
---

Le dossier de crise maintient un état de conduite coordonné au-dessus des
processus métier liés (tickets de service, incidents de sécurité ou de
protection des données, événements de sécurité au travail, exécutions de
playbooks) — les modules métier restent maîtres.

**Cellule de crise & alerte :** Rôles avec personne, suppléance et
joignabilité. L'alerte outrepasse volontairement les temps de repos
(alarme de crise) ; chaque membre acquitte son alerte, les alertes non
acquittées sont escaladées manuellement vers la suppléance. La nomination
dans la cellule constitue en même temps l'accès d'urgence audité au
dossier.

**Tableau de situation & décisions :** Les rapports de situation sont
versionnés et en append-only ; le journal des décisions consigne le
moment, la décision et la justification.

**Délais de notification :** Les modèles de délais par catégorie (RGPD
72 h, NIS2 24 h/72 h/1 mois, KRITIS sans délai) sont des données de
catalogue — l'organisation peut les surcharger ; les échéances concrètes
courent à partir de l'activation.

**Communication :** Le brouillon, la validation (jamais par le rédacteur)
et la diffusion documentée restent traçables séparément.

**Reprise & retour d'expérience :** processus critiques avec RTO/RPO,
solutions de contournement et processus de substitution ; après la levée
de l'alerte suit le retour d'expérience (obligatoire avant la clôture).
Les exercices sont menés séparément et ne faussent aucun dossier réel.
