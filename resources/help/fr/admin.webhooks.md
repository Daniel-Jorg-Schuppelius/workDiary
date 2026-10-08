---
title: "Webhooks"
topic: admin.webhooks
version: 2
keywords:
    - URL de rappel
    - callback
    - notifications sortantes
    - abonnement aux événements
    - signature HMAC
    - clé de signature
    - automatisation
    - intégration sortante
    - journal de livraison
    - charge utile
audience:
    - admin
    - geschaeftsfuehrung
related:
    - admin.notification-rules
    - admin.handbook
    - glossary.core
---

Les webhooks envoient des notifications d'événements sortantes à des
systèmes externes (par ex. un ERP, une plateforme d'automatisation ou
un outil maison). Dès qu'un événement auquel vous êtes abonné se
produit, WorkDiary transmet une charge utile JSON signée par `POST`
HTTPS à votre URL.

Déroulement type :

1. **Créer un webhook** : saisir le libellé et l'URL cible (HTTPS).
2. S'abonner aux **événements** (cases à cocher). Seuls les événements
   sélectionnés déclenchent un envoi.
3. La **Clé de signature** n'est affichée qu'une seule fois en clair —
   copiez-la maintenant. Ensuite, elle n'est plus stockée que sous forme
   chiffrée et peut être renouvelée si nécessaire.
4. Avec **Envoyer un événement de test**, vérifier l'accessibilité et
   le contrôle de la signature.

## Charge utile

La charge utile est volontairement minimale et pauvre en données
personnelles :

```json
{
  "event": "openIssue.assigned",
  "occurred_at": "2026-06-14T12:00:00+00:00",
  "organization": { "id": 1 },
  "data": {
    "subject_type": "OpenIssue",
    "subject_id": 42,
    "title": "..."
  }
}
```

Si nécessaire, complétez d'autres champs via l'API REST.

## Vérifier la signature

Chaque livraison comporte les en-têtes suivants :

- `X-WorkDiary-Signature: sha256=<hmac>`
- `X-WorkDiary-Timestamp: <heure-unix>`
- `X-WorkDiary-Event: <clé-événement>`

Le HMAC est calculé sur `<timestamp>.<body>` avec la clé de signature :

```text
expected = HMAC_SHA256(timestamp + "." + raw_body, signing_key)
```

Comparez `expected` à la valeur de signature en temps constant et
rejetez les requêtes dont l'horodatage est trop ancien (protection
contre le rejeu).

## Fiabilité

Les livraisons ayant échoué sont relancées avec un délai croissant
(backoff). Après plusieurs échecs consécutifs, le point de terminaison
est **désactivé automatiquement** ; enregistrez-le comme actif pour le
réactiver. Le journal des livraisons de chaque point de terminaison
indique le statut, le code HTTP et l'heure des dernières tentatives.
