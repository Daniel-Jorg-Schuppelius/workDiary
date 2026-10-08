---
title: "Stockage SharePoint"
topic: admin.sharepoint
version: 1
keywords:
    - SharePoint
    - SharePoint Online
    - bibliothèque de documents
    - répliquer des documents
    - archiver dans SharePoint
    - Microsoft 365
    - choisir un site
    - réplication
    - preuve de transmission
    - classer les factures
    - conflit de réplication
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.msgraph
    - documents.manage
    - admin.integration-inbox
    - cloud-intake.overview
---

La page **Stockage SharePoint** réplique les documents validés de WorkDiary
dans une bibliothèque de documents SharePoint Online via Microsoft Graph – et,
si vous le souhaitez, les PDF des factures émises et des protocoles signés.
WorkDiary reste maître des données : rien ne revient de SharePoint, et les
modifications apportées aux fichiers répliqués dans SharePoint apparaissent
comme conflit, sans jamais être reprises en silence. Pour chaque transfert,
WorkDiary consigne une preuve de transmission (somme de contrôle, date, cible).

Récupérer des fichiers de SharePoint vers WorkDiary en lecture seule relève
d'une autre fonction : l'**Entrée de documents cloud**.

## Prérequis

- Le plugin **SharePoint** est activé sous **Plugins**. L'entrée **Stockage
  SharePoint** apparaît ensuite dans le menu système (icône d'engrenage
  **Système**), dans le groupe **Plugins**.
- Il existe un enregistrement d'application dans Microsoft Entra ID avec un ID
  client et un secret client. Soit l'exploitant a enregistré une application
  pour toute l'installation, soit votre organisation utilise sa propre
  application issue des paramètres du plugin **Microsoft 365** (**ID client
  (enregistrement d’application propre)**, **Secret client**, **Tenant (ID
  d’annuaire)**). Une application propre doit connaître votre adresse
  WorkDiary suivie du chemin /admin/sharepoint/oauth/callback comme URI de
  redirection de type « Web ». Si l'application manque, la page affiche un
  avertissement au lieu du bouton de connexion.
- Il vous faut un compte Microsoft 365 disposant d'un droit d'écriture sur la
  bibliothèque cible. La connexion agit avec les droits de ce compte. Si
  l'exploitant a limité l'accès aux sites autorisés un par un
  (Sites.Selected), un administrateur du tenant doit en plus autoriser le site
  souhaité.
- La page est réservée aux administrateurs de votre organisation. Chaque
  organisation dispose d'une connexion SharePoint.

## Se connecter

1. Cliquez sur **Connecter avec Microsoft 365**. La connexion Microsoft
   s'ouvre ; connectez-vous et acceptez les autorisations.
2. Microsoft vous renvoie vers la page. Le message « Connecté avec Microsoft
   365. Choisissez maintenant le site + la bibliothèque. » confirme la
   connexion.

La personne qui a lancé l'opération doit la terminer elle-même, dans la même
session. Sinon, le message « Le flux OAuth a expiré ou est invalide »
apparaît ; relancez alors la connexion.

## Choisir la cible : site et bibliothèque de documents

1. Dans la section **Cible : site + bibliothèque de documents**, saisissez le
   nom ou un mot-clé du site dans le champ **Rechercher un site** et cliquez
   sur **Rechercher**.
2. Cliquez sur le site dans la liste des résultats. Il est marqué comme
   **Sélectionné**, et WorkDiary charge ses bibliothèques de documents.
3. Sous **Bibliothèque de documents**, choisissez la bibliothèque et cliquez
   sur **Enregistrer**. **Cible actuelle** affiche ensuite le site et la
   bibliothèque.

Lors de l'enregistrement, WorkDiary vérifie le site et la bibliothèque auprès
de Microsoft ; une bibliothèque qui n'appartient pas au site choisi est
refusée.

## Règles de dossiers et contenus répliqués

Dans la section **Règles de dossiers + sources**, vous définissez ce qui est
répliqué et où :

- **Dossier par défaut** (prérempli avec « Dokumente ») : sous-dossier de la
  bibliothèque pour tous les documents sans règle propre.
- **Actif** : active ou désactive la réplication.
- **Contenus reflétés** : **Documents (GED)**, **Factures (PDF)** et
  **Protocoles (PDF)**. Sans sélection, seuls les documents sont répliqués.
- **Type de document → dossier** : choisissez par ligne un type de document et
  saisissez un sous-dossier relatif à la bibliothèque. Les lignes vides sont
  ignorées ; après chaque enregistrement, trois lignes vides supplémentaires
  sont disponibles. Les types apparaissent dans la liste sous leur nom court
  anglais, par exemple contract pour les contrats ou invoice pour les
  factures.

Cliquez ensuite sur **Enregistrer**.

Voici comment WorkDiary range les fichiers :

- **Documents** dans le dossier de leur type ou dans le dossier par défaut. Le
  nom de fichier se compose de « document- », d'un numéro interne et de
  l'extension ; une nouvelle version remplace ainsi le même fichier.
- **Factures** dans le dossier invoices, avec un sous-dossier par année ; le
  nom de fichier est le numéro de facture.
- **Protocoles** dans le dossier protocols, avec un sous-dossier par année.

Les factures et les protocoles ne suivent pas les règles de dossiers.

## Quand la réplication a lieu

- **Automatiquement lors d'événements :** lorsqu'un document reçoit le statut
  **Actif** (validé) ou une nouvelle version, WorkDiary transfère cette
  version. De simples modifications de métadonnées ne déclenchent pas de
  nouveau transfert. Lorsqu'une facture est émise ou qu'un protocole est
  signé, son PDF suit – à condition que le contenu correspondant soit
  sélectionné.
- **En arrière-plan avec répétition :** le transfert passe par une file
  d'attente. En cas d'échec, il est répété automatiquement ; aucun fichier
  n'est écrit deux fois.
- **Refléter maintenant :** met en file d'attente tous les documents actifs de
  l'organisation, par exemple après la première configuration. WorkDiary
  ignore les fichiers inchangés. Ce bouton ne couvre pas les factures et les
  protocoles ; ceux-ci sont transférés lors de leur émission ou de leur
  signature.

Il n'existe pas de planification fixe. WorkDiary lit dans SharePoint uniquement
pour vérifier si un fichier répliqué y a été modifié.

## Résoudre les conflits

Si un fichier répliqué a été modifié dans SharePoint, WorkDiary ne l'écrase
pas. Une entrée apparaît à la place dans la **Boîte de rapprochement** avec
l'indication « Modification externe détectée — réplication suspendue (pas
d'écrasement). » Pour les documents de la gestion documentaire, trois actions
sont disponibles :

- **Écraser le distant** : la version de WorkDiary remplace le fichier dans
  SharePoint ; la modification qui y a été faite est perdue.
- **Importer comme nouvelle version** : la version de SharePoint est reprise
  comme nouvelle version du document.
- **Détacher la réplication** : ce seul document n'est plus répliqué ; la
  connexion reste active.

La **Boîte de rapprochement** est accessible aux personnes autorisées à gérer
la facturation.

## Déconnecter et reconnecter

**Déconnecter** supprime les clés d'accès de la connexion. Les fichiers déjà
répliqués restent dans SharePoint. La cible et les règles de dossiers restent
enregistrées ; après un nouveau **Connecter avec Microsoft 365**, tout
reprend avec les mêmes paramètres.

## Problèmes fréquents

- **Pas de bouton de connexion :** la page signale un enregistrement
  d'application manquant. Enregistrez l'ID client et le secret client (voir
  prérequis) ou adressez-vous à l'exploitant.
- **Connexion interrompue :** « Microsoft n'a pas renvoyé de code
  d'autorisation » – la connexion a été annulée ou le consentement refusé. Si
  votre tenant exige le consentement d'un administrateur, un administrateur
  Entra doit accorder l'autorisation à l'application.
- **Aucun site trouvé :** vérifiez le terme de recherche. En cas d'accès
  limité, l'administrateur du tenant doit autoriser le site.
- **Site ou bibliothèque refusé :** « Le site choisi est inaccessible ou non
  autorisé. » ou « Aucune bibliothèque de documents trouvée dans ce site. » –
  le compte connecté n'a pas accès, ou le site n'a pas de bibliothèque.
- **Statut Inactif, Refléter maintenant absent :** la connexion est
  déconnectée, **Actif** est désactivé, aucune bibliothèque n'est choisie, ou
  la connexion a été mise à l'arrêt après des erreurs répétées consécutives.
  Une fois la cause corrigée, **Déconnecter** puis une nouvelle connexion
  remettent le compteur d'erreurs à zéro.
- **Vérifier l'état :** à côté du titre de la page figure le dernier état
  vérifié ; **Tester la connexion** le vérifie immédiatement.
