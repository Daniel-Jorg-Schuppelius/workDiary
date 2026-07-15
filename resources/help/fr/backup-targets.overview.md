---
title: "Cibles de sauvegarde cloud"
topic: backup-targets.overview
version: 1
audience: []
related:
    - admin.integrations
---

WorkDiary sauvegarde toute l'installation de manière chiffrée vers Dropbox, OneDrive ou Google Drive (copie hors site de la stratégie 3-2-1). Le texte en clair ne quitte jamais l'installation — seules des parties chiffrées avec un manifeste de commit signé sont téléversées.

**Connexions :** Seul l'exploitant de la plateforme gère les cibles de sauvegarde ; chaque fournisseur reçoit son propre compte OAuth (séparé de la réception de documents, scopes d'écriture dédiés). Si une autorisation requise manque, la cible est visiblement bloquée.

**Clés :** BACKUP_MASTER_KEY (ENV, à conserver hors ligne !) est le seul chemin de déchiffrement régulier ; une paire de clés de récupération optionnelle déchiffre en cas d'urgence. Sans clé de récupération, la page avertit en permanence — la perte de la clé maîtresse rend toutes les sauvegardes inutilisables.

**Exploitation :** L'exécution nocturne crée un instantané (dump BD + fichiers), le chiffre, téléverse les parties de façon reprenable et applique la rétention (7 quotidiennes / 4 hebdomadaires / 12 mensuelles ; la conservation légale protège des générations individuelles). Une vérification hebdomadaire par échantillonnage contrôle signature et empreintes ; le test de restauration restaure dans un répertoire isolé et journalise RPO/RTO — jusqu'au premier test vert, une génération vaut « sauvegardée, restauration non confirmée ».
