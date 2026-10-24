---
title: "Cibles de sauvegarde cloud"
topic: backup-targets.overview
version: 2
keywords:
    - sauvegarde externalisée
    - sauvegarde des données
    - Dropbox
    - OneDrive
    - Google Drive
    - sauvegarde chiffrée
    - règle 3-2-1
    - restauration
    - test de restauration
    - clé maître
    - rétention des sauvegardes
    - reprise après sinistre
    - Nextcloud
    - WebDAV
    - S3
    - Seafile
audience: []
related:
    - admin.integrations
---

WorkDiary sauvegarde toute l'installation de manière chiffrée vers Dropbox, OneDrive, Google Drive, Nextcloud, un serveur WebDAV, un stockage compatible S3 ou Seafile (copie hors site de la stratégie 3-2-1). Le texte en clair ne quitte jamais l'installation — seules des parties chiffrées avec un manifeste de commit signé sont téléversées.

**Connexions :** Seul l'exploitant de la plateforme gère les cibles de sauvegarde ; Dropbox, OneDrive et Google Drive reçoivent chacun leur propre compte OAuth (séparé de la réception de documents, scopes d'écriture dédiés), Nextcloud, WebDAV, S3 et Seafile leurs propres identifiants. Lors de la connexion, WorkDiary écrit, lit et supprime une fois un fichier d'essai ; ce n'est qu'ensuite que la cible est active. Si une autorisation requise manque, la cible est visiblement bloquée.

**Seafile :** URL du serveur (HTTPS uniquement), jeton de compte d'un utilisateur Seafile dédié et nom ou ID d'une bibliothèque existante non chiffrée. WorkDiary n'écrit que dans son propre dossier à l'intérieur et contrôle la taille de chaque fichier téléversé. Le jeton de compte est fourni par l’API Seafile : POST vers /api2/auth-token/ avec nom d’utilisateur et mot de passe, avec l’authentification à deux facteurs également avec l’en-tête X-Seafile-OTP et le code actuel.

**Clés :** BACKUP_MASTER_KEY (ENV, à conserver hors ligne !) est le seul chemin de déchiffrement régulier ; une paire de clés de récupération optionnelle déchiffre en cas d'urgence. Sans clé de récupération, la page avertit en permanence — la perte de la clé maîtresse rend toutes les sauvegardes inutilisables.

**Exploitation :** L'exécution nocturne crée un instantané (dump BD + fichiers), le chiffre, téléverse les parties de façon reprenable et applique la rétention (7 quotidiennes / 4 hebdomadaires / 12 mensuelles ; la conservation légale protège des générations individuelles). Une vérification hebdomadaire par échantillonnage contrôle signature et empreintes ; le test de restauration restaure dans un répertoire isolé et journalise RPO/RTO — jusqu'au premier test vert, une génération vaut « sauvegardée, restauration non confirmée ».
