---
title: "Plugins"
topic: admin.plugins
version: 1
audience:
    - admin
related:
    - admin.handbook
    - admin.toggl
    - admin.openproject
    - admin.lexoffice
    - admin.remote-support
---

Cette page gère les plugins et intégrations installés (Toggl,
OpenProject, Lexoffice, télémaintenance, etc.), pilotés **par
organisation** : activation, réglages, statut de santé et erreurs ne
valent que pour votre organisation. La liste montre le statut et la
santé de chaque plugin avec des actions : configurer, activer/
désactiver, lancer un contrôle de santé, réinitialiser et réactiver
après une désactivation automatique. En cas d'erreurs répétées, le
plugin est **désactivé automatiquement** pour l'organisation
concernée ; le journal des erreurs conserve chaque incident et permet
de les marquer comme **confirmés**. Un plugin désactivé suspend sa
synchronisation — vérifiez le statut de santé après chaque changement
de configuration.
