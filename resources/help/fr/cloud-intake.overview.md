---
title: "Entrée de documents cloud"
topic: cloud-intake.overview
version: 5
keywords:
    - Paperless-ngx
    - Seafile
    - Dropbox
    - OneDrive
    - SharePoint
    - Google Drive
    - dossier surveillé
    - import de documents
    - dossier cloud
    - règle de dossier
    - import automatique
    - connecter un stockage cloud
audience: []
related:
    - documents.manage
    - admin.integrations
---

WorkDiary LIT les documents des dossiers surveillés dans Dropbox, OneDrive/SharePoint, Google Drive et Nextcloud ainsi que depuis Paperless-ngx et Seafile, et les route vers les factures entrantes ou la GED via des règles de dossiers.

**Connexions :** un compte par fournisseur est connecté et confirmé via OAuth ; ensuite le conteneur (drive/bibliothèque) et le dossier racine sont choisis. L’import ne démarre qu’avec au moins une règle valide.

**Paperless-ngx :** l’URL de base et le jeton API se trouvent dans les paramètres du plugin ; « Connecter Paperless-ngx » vérifie le compte. Comme conteneur, choisissez tous les documents ou une étiquette, un correspondant ou un type de document. Le chemin d’un document est type de document/correspondant/nom de fichier (« - » sans affectation) ; « / » suffit comme dossier racine. Le fichier original est repris, les données de facture électronique intégrées sont donc conservées ; l’origine sur la pièce renvoie à l’original dans Paperless. Les documents étiquetés après coup sont détectés par l’exécution suivante en comparant la liste des documents. Le jeton lit avec les droits de son utilisateur : il faut l’accès en lecture aux documents, étiquettes, correspondants, types de document et paramètres d’interface ; seul ce que cet utilisateur peut voir est repris.

**Seafile :** l’adresse et le jeton API se trouvent dans les paramètres du plugin ; « Connecter Seafile » vérifie le compte. Comme conteneur, choisissez une bibliothèque, puis le dossier racine qu’elle contient ; les bibliothèques chiffrées ne sont pas proposées. Si rien n’a changé sous le dossier racine depuis la dernière exécution, WorkDiary ne lit aucun dossier. WorkDiary reconnaît un fichier à son contenu : le renommer ou le déplacer ne crée pas de nouveau justificatif, un fichier au contenu modifié si. L’origine sur le justificatif renvoie au fichier dans Seafile. Le jeton de compte est fourni par l’API Seafile : POST vers /api2/auth-token/ avec nom d’utilisateur et mot de passe, avec l’authentification à deux facteurs également avec l’en-tête X-Seafile-OTP et le code actuel.

**Règles :** des motifs de chemin avec * et ** plus des variables comme {customer_number} associent les fichiers aux clients, projets, commandes, actifs ou contrats existants — jamais par création automatique. Les cas ambigus vont dans la boîte de réception d’intégration.

**Sécurité :** scopes en lecture seule, jetons chiffrés, fichiers sources intacts ; les webhooks ne sont que des signaux de réveil — l’exécution delta reprise fait foi.

**Test de règle et démarrage :** chaque règle a besoin d’un chemin de test, par exemple un vrai dossier client. À l’enregistrement, WorkDiary vérifie si le chemin correspond au modèle, quelles variables sont reconnues et quelle cible en résulte ; sans test réussi, rien n’est enregistré. Une référence cible fixe affecte tous les fichiers d’une règle à un client, projet, commande, actif ou contrat précis. Les règles actives d’une connexion ne doivent pas se chevaucher : des modèles identiques ou un chemin de test correspondant à deux règles bloquent l’activation. Une connexion ne commence son premier import qu’après que vous avez consulté l’aperçu et confirmé « Démarrer l’import » ; un nouveau dossier racine exige une nouvelle confirmation.

**Souveraineté des données :** si, selon la souveraineté des données, un autre système est maître des documents, la connexion ne reprend aucun fichier dans la GED ; chaque fichier devient un cas dans la boîte d’intégration, sans justificatif de transfert. **Reprendre une fois** télécharge à nouveau le fichier et le crée selon les règles. Les règles pour factures entrantes, commandes et dossiers d’appel d’offres ne sont pas concernées.

## Connexion expirée, bloquée ou désactivée

Si le fournisseur refuse la connexion (expirée ou révoquée), la connexion affiche **Reconnexion requise**. S'il manque des autorisations de lecture lors de la connexion, elle affiche **Bloquée** ; après des erreurs répétées jusqu'au seuil de désactivation, **Désactivée**. Dans les trois cas, l'import est suspendu et la carte propose **Se reconnecter**. Seule une nouvelle connexion réactive la connexion ; modifier le dossier ou les règles ne suffit pas.
