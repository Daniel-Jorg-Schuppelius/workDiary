---
title: "Intégration OpenProject"
topic: admin.openproject
version: 1
audience:
    - admin
related:
    - admin.plugins
    - admin.toggl
    - admin.import
---

L'intégration OpenProject couple WorkDiary de façon **bidirectionnelle** :
la synchronisation de structure importe projets, work packages et
utilisateurs (condition préalable à l'import des temps), puis l'import
reprend les saisies de temps d'OpenProject. Les projets sans
correspondance s'accumulent dans la boîte de réception, où vous les
affectez à un projet existant, en créez un nouveau ou les rejetez ;
les imports futurs s'appuient sur les mappings enregistrés. La
réécriture (push) renvoie les temps saisis vers OpenProject et exige
une **activité par défaut** configurée dans le plugin. Vérifiez les
mappings avant le premier push, car il modifie des données dans le
système OpenProject connecté.
