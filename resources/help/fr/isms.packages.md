---
title: "Paquets d'audit et liens auditeur"
topic: isms.packages
version: 1
audience: []
related:
    - isms.audits
    - isms.conformity
    - isms.overview
    - glossary.core
---

Les **paquets d'audit** figent l'état des données SMSI à une date de
référence sous forme d'instantané, base fiable pour les auditeurs
externes. Créez le paquet (titre, date de référence, périmètre, filtre par
norme en option), **finalisez-le** — cela génère l'instantané JSON avec
hachage SHA-256 — puis vérifiez l'intégrité à tout moment et créez un
**lien auditeur** limité dans le temps (1–90 jours, révocable). Les
paquets finalisés sont **immuables** (modification et suppression
bloquées) et l'état des données correspond au moment de la finalisation ;
le lien complet ne s'affiche **qu'une seule fois** à la création. Le
téléchargement par l'auditeur passe par un lien protégé, sans compte
WorkDiary.
