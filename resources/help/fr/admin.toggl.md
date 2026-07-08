---
title: "Import Toggl"
topic: admin.toggl
version: 1
audience:
    - admin
related:
    - admin.plugins
    - admin.import
    - admin.openproject
---

L'import Toggl reprend les saisies de temps de Toggl Track dans
WorkDiary ; il est **unidirectionnel** (lecture seule), rien n'est
réécrit vers Toggl. Deux voies existent : l'**import API** (jeton API
et période) et l'**import de fichier** (rapport détaillé CSV ou
archive d'export du workspace). Les clients/projets Toggl sans
correspondance automatique s'accumulent dans la boîte de réception, où
vous les affectez à un client/projet existant, en créez de nouveaux ou
les rejetez ; les mappings enregistrés permettent l'affectation
automatique des imports futurs. Attention : des imports répétés de la
même période peuvent créer des doublons, et le rejet d'entrées est
définitif.
