---
title: "Webhooks"
topic: admin.webhooks
version: 1
audience:
    - admin
related:
    - admin.notification-rules
    - admin.handbook
    - glossary.core
---

Les webhooks envoient des notifications d'événements sortantes à des
systèmes externes : dès qu'un événement abonné survient, WorkDiary
livre une charge utile JSON signée par `POST` HTTPS à votre URL. Créez
un webhook avec libellé et URL cible, abonnez-vous aux **événements**
souhaités, copiez la **clé de signature** affichée une seule fois en
clair, puis vérifiez la mise en place avec **« Envoyer un événement de
test »**. Chaque livraison porte des en-têtes de signature
(HMAC-SHA256 sur `timestamp.body`) à comparer en temps constant, avec
protection anti-rejeu via l'horodatage. Les livraisons échouées sont
réessayées avec backoff ; après plusieurs échecs consécutifs, le point
de terminaison est désactivé automatiquement.
