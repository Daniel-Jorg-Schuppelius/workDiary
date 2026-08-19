---
title: "Changement de logiciel comptable"
topic: admin.accounting-migration
version: 1
audience:
    - admin
related:
    - admin.plugins
    - customers.billing
---

Le changement de logiciel comptable fait passer une organisation d'un
système à l'autre de façon contrôlée (premier chemin pris en charge :
Lexoffice → orgaMAX). WorkDiary ne copie pas aveuglément d'un système à
l'autre : il rattache les deux systèmes externes aux mêmes objets métier
locaux.

Étapes : planifier (domaines de données + date de bascule, un seul
changement par organisation à la fois) → analyse en simulation (n'écrit dans
aucun système externe) → décider individuellement les enregistrements
ambigus → double exploitation (les anciennes pièces sont soldées dans le
système source) → bascule (à partir de la date, les nouvelles pièces
naissent exclusivement dans le système cible ; l'envoi vers la source est
techniquement bloqué et la bascule reste bloquée tant que des conflits
subsistent) → clôture avec protocole CSV.

Principes : les pièces finalisées ne sont **jamais** reconstruites dans le
système cible — elles restent consultables comme historique avec numéro,
statut et origine. Chaque étape est consignée dans une chaîne d'événements
infalsifiable.
