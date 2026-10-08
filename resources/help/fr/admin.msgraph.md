---
title: "Connecter Microsoft 365"
topic: admin.msgraph
version: 1
keywords:
    - Microsoft 365
    - Office 365
    - agenda Outlook
    - envoyer des e-mails via Microsoft
    - contacts Outlook
    - Microsoft To Do
    - OneNote
    - réunion Teams
    - consentement administrateur
    - Entra ID
    - message d'absence
    - Exchange Online
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.sharepoint
    - admin.google-calendar
    - events.manage
    - admin.integration-inbox
    - admin.notification-rules
    - knowledge.collections
    - cloud-intake.overview
    - backup-targets.overview
---

La page **Microsoft 365** regroupe les connexions à Microsoft 365 via Microsoft
Graph : calendrier, envoi d'e-mails, contacts vers Outlook, Microsoft To Do et
l'import depuis OneNote, ainsi que le consentement à l'échelle du tenant.
Chaque fonction possède sa propre connexion, avec sa propre authentification et
uniquement les autorisations dont elle a besoin – vous ne connectez que ce que
vous utilisez réellement. Chaque connexion vaut pour toute l'organisation et
agit avec le compte Microsoft qui donne son accord lors de la connexion.

## Prérequis

- Le plugin **Microsoft 365** est activé sous **Plugins**. L'entrée
  **Microsoft 365** apparaît ensuite dans le menu système (icône d'engrenage
  **Système**), dans le groupe **Plugins**.
- Il existe un enregistrement d'application dans Microsoft Entra ID. Soit
  l'exploitant a enregistré une application pour toute l'installation, soit
  votre organisation utilise la sienne : sous **Plugins**, ouvrez la boîte de
  dialogue **Configurer** de **Microsoft 365** et saisissez **ID client
  (enregistrement d’application propre)**, **Secret client** et **Tenant (ID
  d’annuaire)**. Le tenant est le GUID de votre annuaire ou l'une des valeurs
  common, organizations ou consumers ; vide, la valeur de l'application de
  l'installation s'applique.
- En l'absence d'application, la page affiche un avertissement et les boutons
  de connexion manquent.
- Il vous faut un compte Microsoft autorisé à accepter les autorisations. La
  page est réservée aux administrateurs de votre organisation.

## Calendrier

En haut de la page, vous connectez le calendrier avec **Connecter à Microsoft
365**. Ensuite, **Publier maintenant** et **Déconnecter** y sont disponibles ;
à côté du titre, un badge indique **Connecté**, **Injoignable** ou
**Inactif**.

- **Sens :** les événements de WorkDiary sont transférés dans le calendrier du
  compte connecté – de 30 jours en arrière à 180 jours en avant, avec titre,
  description, horaire et lieu (salles réservées). Les modifications sont
  reportées, les événements annulés y sont supprimés, et les exécutions
  répétées ne créent pas de doublons. WorkDiary reste maître des données.
- **Moment :** une synchronisation a lieu chaque jour, par défaut à 4 h 45 ;
  vous modifiez la fréquence sous **Tâches planifiées**. **Publier
  maintenant** la lance immédiatement en arrière-plan. En outre, les
  notifications assorties d'une échéance partent aussitôt comme entrée de
  calendrier si une règle de notification utilise le canal **Calendrier**.
- **Calendrier cible :** avec une connexion active, choisissez sous
  **Calendrier**, dans la section du même nom, un calendrier du compte ; sans
  choix, le **Calendrier par défaut** s'applique. Cliquez ensuite sur
  **Enregistrer**.
- **Créer les nouveaux événements comme réunions Teams (lien de
  participation) :** les événements nouvellement transférés reçoivent un lien
  de participation Teams. L'option ne modifie pas les événements déjà
  transférés.
- **Bidirectionnel : importer les changements externes comme propositions :**
  le calendrier cible est relu toutes les heures, et Microsoft signale en plus
  les modifications immédiatement. Les nouveaux rendez-vous externes deviennent
  des propositions, les modifications d'événements transférés deviennent des
  conflits, et les rendez-vous supprimés apparaissent comme « Rendez-vous
  supprimé dans Microsoft 365 » – le tout dans la **Boîte de rapprochement**,
  jamais comme rendez-vous créé à l'aveugle. Les séries apparaissent comme
  rendez-vous individuels et peuvent y être acceptées ou rejetées en groupe. Si
  vous changez de calendrier cible, l'import repart de zéro.

La connexion au calendrier sert aussi :

- à **Vérifier la disponibilité (Microsoft 365)** dans la boîte de dialogue
  d'un événement : affiche libre ou occupé pour les participants choisis, sans
  détails des rendez-vous.
- à l'import des pointages et des temps de projet, qui propose le calendrier
  connecté comme source.
- à l'encadré **Équipe (statut Teams)** de la page **Pointeuse**. Il n'apparaît
  que si l'installation a activé l'accès en lecture au statut Teams et que la
  connexion au calendrier a été rétablie ensuite.

## Envoi d’e-mails via Microsoft 365

Avec **Connecter l’envoi d’e-mails**, un compte autorise WorkDiary à envoyer
des e-mails en son nom – par exemple factures, relances et notifications, sans
accès SMTP. Ensuite, vous voyez le **Compte connecté** et réglez :

- **Adresse d’expéditeur (optionnelle)** : vide, le compte envoie en son
  propre nom. Une autre adresse, par exemple une boîte partagée, nécessite
  dans Exchange le droit « Envoyer comme » (Send As) et une autorisation
  supplémentaire que l'exploitant active pour l'application.
- **Enregistrer une copie dans le dossier Éléments envoyés**.
- **Enregistrer**.

**Envoyer un e-mail de test** envoie immédiatement un message par cette
connexion, au **Destinataire (facultatif)** ou, sans indication, au compte
connecté. Le test utilise la même adresse d'expéditeur que l'envoi réel, si
bien que des droits d'envoi manquants apparaissent tout de suite. C'est
l'exploitant de l'installation qui décide si WorkDiary envoie réellement ses
e-mails par cette connexion ; si ce n'est pas configuré, la carte affiche un
avertissement. **Déconnecter l’envoi d’e-mails** retire l'accès.

## Envoyer les contacts vers Outlook

Après **Connecter l’envoi de contacts**, le bouton **Vers Outlook** apparaît
sur la page de détail d'un client. Il transfère le client comme contact dans
Outlook du compte connecté : nom, interlocuteur, entreprise, e-mail,
téléphone, numéro de mobile, site web et adresse. Un nouveau transfert met le
contact à jour au lieu de le dupliquer ; s'il a été supprimé dans Outlook,
WorkDiary le recrée. Le transfert n'a lieu que sur pression du bouton et
uniquement dans ce sens ; il faut pour cela le droit de modifier le client.

Les contacts Outlook de ce compte servent en outre d'annuaire lors du
rapprochement de numéros de téléphone inconnus, par exemple dans l'import
FRITZ!Box.

## Synchroniser Microsoft To Do

1. Cliquez sur **Connecter la synchronisation To Do**.
2. Créez une liaison : choisissez la **Liste To Do**, comme **Cible** un
   **Projet** ou le **Kanban global**, sélectionnez le **Projet** si la cible
   est un projet, fixez la **Direction** (**Les deux directions**,
   **Uniquement To Do → WorkDiary** ou **Uniquement WorkDiary → To Do**) et
   cliquez sur **Lier**.
3. Le tableau affiche toutes les liaisons. **Retirer** en supprime une ; les
   tâches déjà synchronisées sont conservées.

Chaque liste To Do ne peut être liée qu'une fois ; une nouvelle liaison de la
même liste remplace l'ancienne. Sont synchronisés le titre, la description, le
statut (ouvert, en cours, terminé), la priorité et la date d'échéance. La
synchronisation a lieu toutes les heures ; les modifications faites dans
WorkDiary partent en plus immédiatement, et Microsoft signale immédiatement
les modifications des listes qui importent. Si les deux côtés ont modifié la
même tâche, un conflit est créé dans la **Boîte de rapprochement** – la
dernière modification ne l'emporte pas simplement. WorkDiary ne transmet jamais
les suppressions ; les tâches supprimées dans To Do sont seulement marquées.
Cette synchronisation ne connaît ni sous-tâches, ni responsables, ni sections.

## Importer depuis OneNote

1. Sous **Plugins**, activez l'option **Autoriser l’import OneNote** dans la
   boîte de dialogue **Configurer** de **Microsoft 365**. Jusque-là, la carte
   affiche **Désactivé**, et la connexion ne demande aucun accès aux blocs-notes.
2. Cliquez sur **Connecter OneNote**. L'accès est en lecture seule.
3. **Aller à « Connaissances »** mène à l'import : là, **Importer OneNote**
   reprend un bloc-notes une fois ou à la demande sous forme de notes ou
   d'articles de connaissances. Le bloc-notes devient une collection, ses
   sections deviennent des sous-collections. Il n'y a ni réécriture ni
   synchronisation continue.

## Application Entra et consentement à l'échelle du tenant

Si une stratégie de votre tenant Microsoft empêche les utilisateurs de donner
eux-mêmes leur accord, un administrateur Entra accorde les autorisations une
fois pour toute l'organisation : **Accorder pour l'organisation (admin
consent)**. La connexion exige un rôle d'administrateur Entra dans le tenant
cible. Le consentement couvre le calendrier, l'envoi d'e-mails, les contacts,
les tâches et l'entrée de documents, et, si l'import OneNote est activé, aussi
la lecture des blocs-notes. Ensuite, les utilisateurs se connectent sans
demande de consentement personnelle.

**URI de redirection pour une inscription d'application propre** liste les
adresses qu'une application propre doit enregistrer comme URI de redirection
de type « Web » : pour le calendrier, l'envoi d'e-mails, les contacts, les
tâches, l'entrée de documents, l'admin consent et – uniquement pour
l'application de l'installation – la cible de sauvegarde. L'adresse pour
OneNote manque dans cette liste : avec une application propre, saisissez en
plus votre adresse WorkDiary suivie du chemin
/admin/msgraph/onenote/oauth/callback. Si le **Stockage SharePoint** utilise
la même application, le chemin /admin/sharepoint/oauth/callback en fait aussi
partie.

## Autres fonctions du plugin

- **Définir la réponse automatique Outlook pour les congés approuvés** (dans
  les paramètres du plugin, désactivé par défaut) : dès qu'un congé est
  définitivement approuvé, WorkDiary définit la réponse automatique dans la
  boîte aux lettres de la personne. L'application a besoin pour cela de
  l'autorisation d'application MailboxSettings.ReadWrite avec admin consent.
  Les erreurs ne bloquent pas l'approbation.
- L'**Entrée de documents cloud** et les **Cibles de sauvegarde cloud**
  utilisent leurs propres connexions Microsoft, que vous configurez sur ces
  pages.

## Déconnecter et reconnecter

Chaque carte possède sa propre déconnexion. Elle supprime les clés d'accès de
cette connexion ; les rendez-vous transférés et les contacts Outlook restent
chez Microsoft. Vous pouvez vous reconnecter à tout moment ; WorkDiary remet
alors aussi le compteur d'erreurs à zéro. Si une connexion a été mise à l'arrêt
après des erreurs répétées consécutives, le bouton de connexion réapparaît.

## Problèmes fréquents

- **Pas de bouton de connexion :** l'enregistrement d'application manque (voir
  prérequis).
- **« Le flux OAuth a expiré ou est invalide. Veuillez recommencer. »** La
  connexion a pris trop de temps ou a été terminée dans une autre session. La
  personne qui se connecte doit terminer l'opération elle-même.
- **« La connexion a été refusée ou annulée. »** Le consentement a été refusé.
  Si le compte ne peut pas donner son accord lui-même, utilisez l'admin
  consent.
- **Badge Injoignable :** Microsoft Graph est injoignable ou refuse l'accès.
  Vérifiez le compte et reconnectez-vous.
- **« Échec de l’envoi de test : … »** Avec une adresse d'expéditeur
  différente, il manque souvent le droit « Envoyer comme ».
- **« La liste To Do sélectionnée n’est plus disponible. »** La liste a été
  supprimée dans To Do ou n'appartient pas au compte connecté.
- **Avertissement sur les connexions secondaires :** si le contrôle de santé
  sous **Plugins** signale que des connexions secondaires Microsoft 365
  nécessitent une attention, une connexion pour l'entrée de documents, la
  sauvegarde ou l'envoi d'e-mails est perturbée. Reconnectez-vous à cet
  endroit.
