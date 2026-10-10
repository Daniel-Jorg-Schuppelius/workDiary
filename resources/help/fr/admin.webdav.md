---
title: "Stockage WebDAV"
topic: admin.webdav
version: 3
keywords:
    - WebDAV
    - Nextcloud
    - ownCloud
    - copier les documents
    - stockage de fichiers
    - archiver les factures
    - archiver les comptes rendus
    - mot de passe d'application
    - règles de dossiers
    - conflit de copie
    - adresses privées
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - documents.manage
    - invoices.manage
    - protocols.sign
    - backup-targets.overview
---

La page **WebDAV** (titre de page **Stockage WebDAV**) copie sous forme de
fichiers les documents validés et, si vous le souhaitez, les factures émises
et les comptes rendus signés dans un stockage WebDAV externe tel que
Nextcloud ou ownCloud. Pour chaque fichier, WorkDiary conserve une preuve de
transfert (empreinte, heure, cible). WorkDiary reste le système de
référence : il n’y a pas de canal retour, et les modifications des fichiers
copiés dans le stockage apparaissent comme un conflit au lieu d’être reprises
en silence. Vous trouvez la page dans le menu système (la roue dentée
**Système** dans l’en-tête) sous **Plugins** → **WebDAV**, dès que le plugin
est actif.

## Prérequis

- Le plugin est activé pour votre organisation : **Système** → **Plugins** →
  **Plugins**, puis **Activer** sur l’entrée WebDAV. Vous configurez le
  stockage lui-même sur la page **WebDAV**, pas dans la boîte de dialogue du
  plugin.
- La page est réservée aux administrateurs.
- Il vous faut un compte dans le stockage avec droit d’écriture sur le
  dossier cible et un mot de passe d’application (Nextcloud : Paramètres →
  Sécurité → Mot de passe d’application).
- Le stockage doit être joignable publiquement. Si le serveur se trouve sur
  votre propre réseau, activez **Autoriser les adresses privées/internes**
  (voir plus bas).
- Il existe exactement un stockage WebDAV par organisation.

Cette page ne sert pas de cible de sauvegarde. Une cible de sauvegarde WebDAV
se configure sous **Cibles de sauvegarde cloud**.

## Configurer le stockage

Dans la section **Stockage**, vous renseignez :

- **Libellé** : un nom de votre choix.
- **URL de la collection** : le dossier WebDAV complet dans lequel WorkDiary
  écrit, pour Nextcloud par exemple …/remote.php/dav/files/UTILISATEUR/WorkDiary.
  L’adresse doit commencer par http:// ou https:// ; créez le dossier au
  préalable dans le stockage.
- **Nom d'utilisateur** et **Mot de passe d'application** : le mot de passe
  est obligatoire au premier enregistrement et stocké chiffré ; par la suite,
  un champ vide conserve le mot de passe enregistré.
- **Dossier par défaut** : sous-dossier des documents sans règle de dossier
  propre (prérempli avec Dokumente).
- **Autoriser les adresses privées/internes** : à activer uniquement si le
  serveur WebDAV se trouve sur votre propre réseau (par exemple
  192.168.x.x). Sans ce commutateur, WorkDiary refuse les adresses internes
  dès l’enregistrement. L’activation est journalisée. Si l’exploitant de
  votre installation a bloqué cette autorisation, le commutateur reste sans
  effet.
- **Actif** : active ou désactive le stockage.
- **Contenu répliqué** : **Documents (GED)**, **Factures (PDF)**, **Comptes
  rendus (PDF)**.
- **Type de document → dossier** : un sous-dossier par type de document, voir
  plus bas.

**Enregistrer** applique vos saisies. Lorsque le stockage est actif, la page
affiche son état (par exemple **État OK**) et **Tester la connexion**.

## Ce qui est copié et quand

- **Documents :** un document est copié dès qu’il a le statut **Actif** avec
  un fichier, puis à chaque nouvelle version. Les modifications des
  informations sans nouvelle version ne déclenchent pas de téléversement.
  Cela ne vaut que si **Documents (GED)** est coché ; si aucune source n’est
  cochée, les documents sont considérés comme choisis.
- **Factures (PDF) :** avec cette case cochée, chaque facture est déposée une
  fois en PDF lors de son passage à **Émise**.
- **Comptes rendus (PDF) :** avec cette case cochée, chaque compte rendu est
  déposé en PDF lors de sa signature (statut **Signé**).
- Le transfert s’exécute en arrière-plan via une file d’attente et est répété
  en cas d’erreur de connexion. WorkDiary ne téléverse pas à nouveau un
  contenu inchangé.
- **Copier maintenant** remet en file tout ce qui provient des sources
  cochées : documents validés, factures émises et comptes rendus signés –
  utile après la configuration, y compris pour les pièces antérieures.
  WorkDiary ne téléverse pas à nouveau un contenu déjà copié.
- Il n’existe pas d’exécution planifiée ; la copie suit les modifications dans
  WorkDiary.

## Dossiers et noms de fichiers

- Les documents sont déposés dans le dossier de leur type de document défini
  sous **Type de document → dossier**, sinon dans le **Dossier par défaut** –
  tous deux relatifs à l’URL de la collection. Le fichier s’appelle document-
  suivi du numéro du document et de l’extension d’origine, par exemple
  document-42.pdf.
- La sélection désigne les types de document par leur libellé, par exemple
  Contrat ou Facture. Il y a toujours trois lignes vides ; WorkDiary écarte
  les lignes sans type ou sans sous-dossier.
- Les factures sont déposées sous invoices/année/numéro-de-facture.pdf, les
  comptes rendus sous protocols/année/protocol-numéro.pdf – directement sous
  l’URL de la collection, pas dans le dossier par défaut.
- WorkDiary crée lui-même les sous-dossiers manquants.

## Conflits

Avant de téléverser une nouvelle version, WorkDiary vérifie si le fichier du
stockage a été modifié depuis la dernière copie. Si c’est le cas, il n’écrase
rien et crée un conflit dans la Boîte de rapprochement : « Modification
externe détectée — copie suspendue ». Vous y choisissez :

- **Écraser le distant** : le fichier du stockage reçoit l’état de WorkDiary ;
  la modification externe est perdue.
- **Importer comme nouvelle version** : l’état du stockage devient la nouvelle
  version du document dans WorkDiary.
- **Détacher la copie** : ce document n’est définitivement plus copié ; le
  stockage reste actif pour tous les autres.

Pour les PDF de factures et de comptes rendus, seul **Écraser le distant**
est proposé : les factures émises et les comptes rendus signés sont
immuables, WorkDiary dépose de nouveau son PDF. Pour conserver le fichier
modifié, choisissez **Rejeter**.

La Boîte de rapprochement est accessible aux administrateurs et à la
comptabilité.

## Déconnecter

**Déconnecter** désactive le stockage. Les fichiers déjà copiés restent dans
le stockage. Pour le réactiver, cochez **Actif** et enregistrez.

## Erreurs fréquentes

- « L'URL de la collection doit commencer par http:// ou https://. » :
  saisissez l’adresse complète.
- « Un nouveau stockage nécessite un mot de passe d'application. » : le mot de
  passe manque au premier enregistrement.
- **État défaillant** avec « Stockage WebDAV inaccessible ou identifiants
  invalides. » : vérifiez l’URL de la collection, le nom d’utilisateur, le mot
  de passe d’application et l’existence du dossier. Une erreur WebDAV avec
  RuntimeException indique souvent une adresse d’un réseau interne sans
  autorisation.
- « L'URL de la collection pointe vers une adresse privée/interne. » : si le
  serveur se trouve sur votre propre réseau, activez **Autoriser les adresses
  privées/internes**. Si l’exploitant a bloqué cette autorisation, le
  stockage doit avoir une adresse joignable publiquement.
- « Aucun stockage WebDAV actif. » avec **Copier maintenant** : le stockage est
  désactivé ou incomplet.
- Des factures ou comptes rendus manquent dans le stockage : la case
  correspondante sous **Contenu répliqué** n’était pas cochée lors de
  l’émission ou de la signature.
- Un document n’est plus mis à jour : un conflit est ouvert dans la boîte, ou
  sa copie a été détachée.
