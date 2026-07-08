---
title: "Journal d'audit"
topic: audit.log
version: 1
audience:
    - admin
related:
    - admin.security
    - admin.handbook
    - privacy.overview
---

Le journal d'audit (`/audit`) est le protocole de contrôle inviolable des
modifications et actions du système : les entrées sont **append-only**,
chaînées par une **chaîne de hachage SHA-256** (GoBD) et ne peuvent être ni
modifiées ni supprimées. La liste se filtre par **action**, **type**
d'objet, **utilisateur** et **période** ; chaque entrée montre l'horodatage,
l'utilisateur déclencheur, l'objet, les modifications concrètes et
l'adresse IP. L'intégrité se vérifie avec `php artisan audit:verify` (code
de sortie 1 en cas de rupture — idéal pour cron/CI) ; le journal est un
outil en lecture seule.
