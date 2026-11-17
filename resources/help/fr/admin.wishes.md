---
title: "Souhaits des organisations"
topic: admin.wishes
version: 1
keywords:
    - vue plateforme souhaits
    - regrouper des souhaits
    - canal éditeur
    - souhait à l’éditeur
    - réponse de l’exploitation
audience:
    - admin
related:
    - wishes.overview
    - admin.settings
---

La vue plateforme affiche tous les souhaits que les organisations ont transmis
à l’exploitation – réservée aux opérateurs de la plateforme. On y voit le texte
épuré, le type, la page, la version de l’application, le nombre de votes et
l’organisation. Qui a rédigé ou approuvé le souhait ainsi que le texte
d’origine restent dans l’organisation.

## Regrouper, statut et réponse

- **Regrouper :** rattachez des souhaits similaires à un souhait principal du
  même type. La liste n’affiche que les souhaits principaux ; les votes du
  regroupement s’additionnent.
- **Le statut et la réponse de l’exploitation** se définissent sur le souhait
  principal. Ils valent pour tout le regroupement et apparaissent dans le
  souhait de chaque organisation comme « Réponse de l’exploitation de la
  plateforme » ; auteurs et votants reçoivent une notification.

## Canal éditeur (installation auto-hébergée)

Si l’éditeur exploite l’installation, cette vue est déjà l’éditeur. Dans une
installation auto-hébergée, vous pouvez envoyer certains souhaits à l’éditeur
par courriel – uniquement si vous activez le canal dans les **paramètres
système** avec `wishes.vendor_channel` (désactivé par défaut ; sans lui, aucun
souhait ne quitte l’installation). L’adresse de l’éditeur figure dans
`wishes.vendor_address` ; si elle est vide, l’adresse de contact enregistrée
s’applique.

1. Adaptez si nécessaire le **texte pour l’éditeur** et enregistrez.
2. Vérifiez l’**aperçu** : destinataire, adresse de réponse, objet et
   exactement le texte envoyé – référence, type, texte, page, version de
   l’application, nombre de votes et vous comme expéditeur. L’organisation et
   les noms ne sont jamais inclus.
3. **Envoyer à l’éditeur** envoie exactement ce courriel. Si quelque chose a
   changé entre-temps (par exemple le nombre de votes), l’application demande
   un nouvel aperçu.

La réponse de l’éditeur vous parvient par courriel. Saisissez-la sous
**Réponse de l’éditeur** ; l’organisation la voit sur le souhait.
