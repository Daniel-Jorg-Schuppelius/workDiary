---
title: "Installation"
topic: install.wizard
version: 1
audience: [admin]
related:
    - admin.tenants
    - auth.login
---

L'assistant d'installation vous guide pas à pas dans la première mise en
service de WorkDiary ; chaque étape enregistre immédiatement ses valeurs,
si bien qu'une interruption peut être reprise sans risque. Les étapes :
**Prérequis** (vérification serveur/PHP), **Application** (nom, URL,
langue, fuseau horaire), **Base de données** (test de connexion,
migrations, rôles et permissions), **Administrateur** (première
organisation et compte admin), **Mail**, **Intégrations** (clé API
Lexoffice, clés VAPID) et **Finalisation**. Cette dernière pose le fichier
de verrouillage, vide les caches et mène à la connexion — une fois
l'installation terminée, l'assistant est verrouillé définitivement.
