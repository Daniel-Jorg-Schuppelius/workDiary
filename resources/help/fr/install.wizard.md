---
title: "Installation"
topic: install.wizard
version: 3
keywords:
    - assistant d'installation
    - configuration initiale
    - première installation
    - setup
    - prérequis système
    - configurer la base de données
    - créer l'administrateur
    - paramètres SMTP
    - serveur de messagerie
    - notifications push
    - VAPID
audience: [admin]
related:
    - admin.tenants
    - auth.login
---

L’assistant d’installation vous guide pas à pas dans la première mise en
service de WorkDiary. Chaque étape enregistre immédiatement ses valeurs,
si bien qu’une interruption peut être reprise sans risque à tout moment.
**Suivant** mène à l’étape suivante, **Retour** à la précédente. Une fois
l’installation terminée, l’assistant est verrouillé et ne peut plus être
ouvert.

Les étapes en un coup d’œil :

- **Prérequis** : vérifie si le serveur remplit toutes les exigences pour
  le **Pilote de base de données** choisi. Après avoir corrigé les points
  signalés, vérifiez de nouveau avec **Mettre à jour**.
- **Application** : **Nom de l’application**, **URL de l’application**,
  **Environnement**, **Langue** et **Fuseau horaire**. S’il n’existe pas
  encore de clé d’application, elle est générée automatiquement ; une clé
  existante reste inchangée.
- **Base de données** : **Pilote** et données de connexion. **Connecter
  et migrer** teste la connexion, configure la base de données et crée les
  rôles et autorisations. N’activez l’option qui vide la base de données
  avant la migration que si la base doit être vide ou si une tentative
  précédente a été interrompue.
- **Administrateur** : création de la première organisation (**Nom de
  l’organisation**) et du compte administrateur avec **Créer un
  administrateur**.
- **E-mail** : canal d’envoi (**Mailer**) et expéditeur des e-mails. Avec
  « log », les e-mails sont seulement journalisés, sans envoi ; avec
  « smtp », vous saisissez le **SMTP-Host**, le port, les identifiants et
  le **Chiffrement**, ainsi que l’**Adresse de l’expéditeur** et le **Nom
  de l’expéditeur**.
- **Intégrations** : accès facultatifs comme la **Clé API Lexoffice** et
  la paire de clés pour **Web-Push (VAPID)**, que **Générer la clé** crée
  automatiquement. Tout peut être complété plus tard.
- **Clôture** : **Finaliser l’installation** verrouille l’assistant,
  supprime les paramètres mis en cache pour que les nouvelles valeurs
  s’appliquent immédiatement, et mène à la connexion. L’administrateur se
  reconnecte ensuite normalement.
