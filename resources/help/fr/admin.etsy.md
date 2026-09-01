---
title: "Connecter Etsy"
topic: admin.etsy
version: 1
audience:
    - admin
modules:
    - module.lager
related:
    - admin.integrations
    - admin.plugins
---

WorkDiary connecte directement la **boutique Etsy** de l'organisation
(Open API v3) : les commandes apparaissent en **miroir de commandes**, les
déclarations d'expédition avec suivi repartent vers Etsy, et les frais et
versements du ledger Etsy sont disponibles pour l'analyse.

**Seller app propre :** Chaque organisation enregistre sa propre seller app
sur etsy.com/developers (validation en quelques minutes) et renseigne le
**keystring** et le **shared secret** sur la carte du plugin. La redirect
URI de l'app doit être exactement l'URL de callback affichée dans le
panneau (HTTPS, sans écart). Ensuite « Connecter à Etsy » — la boutique est
détectée automatiquement ; une boutique ne peut être liée qu'à **une**
organisation.

**Inbox-first :** Les acheteurs ne sont jamais créés aveuglément comme
clients. Les correspondances uniques ou les acheteurs récurrents déjà
affectés sont liés ; tout le reste apparaît comme proposition dans la boîte
d'intégration. Les commandes invité sans compte Etsy restent dans le miroir
sans proposition.

**Webhooks (optionnel) :** Dans le portail webhook d'Etsy, enregistrez
l'URL affichée dans le panneau avec les quatre événements order.* et
renseignez le secret `whsec_…` sur la carte du plugin — les nouvelles
commandes apparaissent alors immédiatement. Sans webhook, tout passe par la
synchronisation régulière (qui reste toujours la source fiable).

**Déclarer l'expédition :** L'action du miroir transmet numéro de suivi et
transporteur à Etsy (Etsy notifie l'acheteur). Les transporteurs inconnus
sont transmis comme « other ». Chaque commande n'est déclarée qu'une fois
au maximum.

**Attention au délai :** Le refresh token d'Etsy expire 90 jours après la
dernière utilisation ; le contrôle de santé prévient à temps, ensuite seule
une reconnexion aide. Etsy ne fournit pas d'environnement de test — les
tests se font sur la boutique réelle selon l'API testing policy d'Etsy
(les frais sont facturés réellement).

*The term "Etsy" is a trademark of Etsy, Inc. This application uses the
Etsy API but is not endorsed or certified by Etsy, Inc.*
