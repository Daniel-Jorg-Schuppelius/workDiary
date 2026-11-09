---
title: "Changement de logiciel comptable"
topic: admin.accounting-migration
version: 2
keywords:
    - migration comptable
    - migrer la comptabilité
    - changement de fournisseur
    - Lexoffice
    - orgaMAX
    - sevDesk
    - easybill
    - migration de données
    - simulation
    - essai à blanc
    - date de bascule
    - fonctionnement en parallèle
audience:
    - admin
related:
    - admin.plugins
    - customers.billing
---

Le changement de logiciel comptable fait passer une organisation d'un
système à l'autre de façon contrôlée. Source et cible se choisissent
librement parmi Lexoffice, orgaMAX, sevDesk et easybill, dans les deux sens.
WorkDiary ne copie pas aveuglément d'un système à
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

Pour sevDesk et easybill :

- Les **données de base** sont rapprochées via le lien de contact : les
  clients et fournisseurs déjà liés au logiciel (transfert, envoi de contact
  ou synchronisation des contacts) comptent comme attribués.
- Les **articles** ne peuvent pas être rapprochés avec sevDesk et easybill,
  car WorkDiary ne fait que refléter leur fichier articles sans le lier aux
  articles locaux. Désélectionnez alors ce domaine.
- L’**historique des pièces** provient du miroir des pièces. Lancez la
  récupération des pièces avant l’analyse et avant la clôture afin que les
  anciennes pièces ouvertes soient entièrement prises en compte.
- Les images des pièces restent dans le logiciel. Sauvegardez-les via son
  export avant la fin du contrat.

Principes : les pièces finalisées ne sont **jamais** reconstruites dans le
système cible — elles restent consultables comme historique avec numéro,
statut et origine. Chaque étape est consignée dans une chaîne d'événements
infalsifiable.
