---
title: "Gérer les documents"
topic: documents.manage
version: 1
audience: []
modules:
    - module.documents
related:
    - forms.fill
    - knowledge.articles
    - glossary.core
---

Le module documentaire gère contrats, certificats, rapports de contrôle
et notices comme **fichiers versionnés** avec métadonnées, validité et
rattachement à un client, projet, commande ou équipement. Téléversez un
document (titre, type, validité et objet de référence optionnels) puis,
en cas de modification, une **nouvelle version** — le numéro s'incrémente
et les anciennes versions restent inchangées ; le téléchargement porte
sur la version actuelle ou une version antérieure, et l'archivage retire
le document de l'usage actif. Les statuts sont « Brouillon », « Actif »,
« Archivé » ; **« Expiré »** est calculé automatiquement à partir de la
date « valable jusqu'au », et des règles de notification peuvent
signaler les documents arrivant à échéance. Attention : **la
suppression retire le document avec toutes ses versions** (soft delete,
droit de suppression requis) ; les versions sont immuables, toute
correction passe par une nouvelle version.
