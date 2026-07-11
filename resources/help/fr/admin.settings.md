---
title: "Paramètres système"
topic: admin.settings
version: 1
audience:
    - admin
related:
    - admin.handbook
---

Cette page gère en un seul endroit tous les paramètres enregistrés de la
plateforme – des tailles de page aux seuils d'exploitation et
d'intégration, en passant par les limites de téléversement.

**Registre central :** Chaque paramètre est enregistré avec son type, ses
portées autorisées et ses règles de validation. L'écriture passe
exclusivement par ce chemin validé – les valeurs invalides (p. ex. hors
des limites min/max) sont rejetées avec un message d'erreur clair avant
de pouvoir produire un effet.

**Deux portées :** Selon l'entrée, les paramètres s'appliquent à **tout
le système**, **par organisation**, ou les deux. Le sélecteur de portée
permet de changer de vue ; la recherche filtre par clés, la liste est
triée par groupes.

**Logique de priorité :** Un ordre fixe s'applique à chaque valeur – le
**paramètre d'organisation** prime sur le **paramètre système**, qui
prime lui-même sur la **valeur par défaut** intégrée de l'installation.
La vue d'ensemble affiche pour chaque entrée la valeur effective avec son
origine, de sorte que vous voyez immédiatement si une valeur est la
valeur par défaut ou si elle a été surchargée.

**Réinitialisation et historique :** Chaque surcharge peut être remise
individuellement à la valeur par défaut. Pour les paramètres système,
vous pouvez en outre consulter l'historique des modifications : qui a
défini quelle valeur et quand – traçable via le journal d'audit.

**Valeurs sensibles :** Les entrées marquées comme sensibles (p. ex. des
adresses de webhook contenant des secrets) sont affichées masquées dans
l'interface. Elles peuvent être redéfinies, mais pas lues.

**Effet sur les jobs :** Certains paramètres influencent des jobs
d'arrière-plan planifiés (comme les durées de conservation ou les heures
d'exécution). Ces liens sont indiqués sur l'entrée concernée ; la
modification prend effet à la prochaine exécution.

**Recommandation :** Surchargez le moins possible. Chaque surcharge
d'organisation rend le comportement plus difficile à prévoir – ne la
définissez que si l'organisation doit réellement dévier, et documentez
la raison. Après une modification, vérifiez la valeur effective
affichée au lieu de vous fier à votre saisie.
