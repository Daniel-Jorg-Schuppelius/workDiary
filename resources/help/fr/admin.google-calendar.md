---
title: "Connecter Google Agenda"
topic: admin.google-calendar
version: 2
keywords:
    - Google Agenda
    - Google Calendar
    - rendez-vous vers Google
    - synchroniser l'agenda
    - Google Workspace
    - synchronisation d'agenda
    - agenda bidirectionnel
    - exporter des rendez-vous
    - publier l'agenda
    - Google Cloud Console
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.msgraph
    - events.manage
    - admin.integration-inbox
    - admin.notification-rules
    - admin.import
    - admin.scheduler
---

La page **Google Agenda** transfère les événements de WorkDiary dans un agenda
d'un compte Google. WorkDiary reste maître des données : les modifications sont
reportées, les événements annulés et supprimés disparaissent de l'agenda Google, et les
exécutions répétées ne créent pas de doublons. Si vous le souhaitez, WorkDiary
relit en outre l'agenda et vous soumet les modifications externes sous forme de
propositions à vérifier.

## Prérequis

- Le plugin **Google Agenda** est activé sous **Plugins**. L'entrée **Google
  Agenda** apparaît ensuite dans le menu système (icône d'engrenage
  **Système**), dans le groupe **Plugins**.
- Il existe un client OAuth dans la Google Cloud Console. Soit l'exploitant en
  a enregistré un pour toute l'installation, soit votre organisation utilise le
  sien : sous **Plugins**, ouvrez la boîte de dialogue **Configurer** de
  **Google Agenda** et saisissez **ID client (application Google Cloud
  propre)** et **Secret client**. Un client propre doit connaître votre adresse
  WorkDiary suivie du chemin /admin/google-calendar/oauth/callback comme URI de
  redirection autorisé.
- Google considère l'accès à l'agenda comme sensible. L'application a donc
  besoin d'une validation par Google, ou bien vous réglez l'écran de
  consentement sur le type « Interne » dans Google Workspace.
- En l'absence de client OAuth, la page affiche un avertissement au lieu du
  bouton de connexion.
- Il vous faut un compte Google disposant d'un droit d'écriture sur l'agenda
  cible. La connexion peut modifier des rendez-vous et lire la liste des
  agendas.
- La page est réservée aux administrateurs de votre organisation. Chaque
  organisation dispose d'une connexion.

## Se connecter

1. Cliquez sur **Connecter à Google**. La connexion Google s'ouvre ;
   connectez-vous et autorisez l'accès.
2. Google vous renvoie vers la page. Le message « Compte Google connecté. »
   confirme la connexion ; à côté du titre figure le badge **Connecté**.

La personne qui a lancé l'opération doit la terminer elle-même, dans la même
session.

## Choisir l'agenda cible

Dans la section **Agenda cible**, choisissez sous **Agenda** l'un des agendas
du compte connecté. Sans choix, l'**Agenda principal** s'applique. C'est
aussi là que vous activez au besoin **Bidirectionnel : importer les
modifications externes comme propositions**. Cliquez ensuite sur
**Enregistrer**. Si vous changez d'agenda, l'import repart de zéro.

## Ce qui est transféré et quand

- **Contenu :** les événements de 30 jours en arrière à 180 jours en avant,
  avec titre, description, horaire et lieu (salles réservées). Les événements
  annulés sont supprimés de l'agenda Google.
- **Moment :** une synchronisation a lieu chaque jour, par défaut à 4 h 55 ;
  vous modifiez la fréquence sous **Tâches planifiées**. **Publier
  maintenant** la lance immédiatement en arrière-plan.
- **Notifications :** les notifications assorties d'une échéance partent
  aussitôt comme entrée d'agenda si une règle de notification utilise le canal
  **Calendrier**.
- **Sans mode bidirectionnel**, WorkDiary ne lit aucun rendez-vous de l'agenda
  Google.

## Import bidirectionnel

Lorsque le mode bidirectionnel est activé, WorkDiary relit l'agenda cible
toutes les heures. Il en résulte uniquement des entrées dans la **Boîte de
rapprochement**, jamais des rendez-vous créés d'office :

- Un nouveau rendez-vous qui ne vient pas de WorkDiary devient une
  proposition.
- Un événement transféré puis modifié dans Google devient un conflit – sinon la
  synchronisation suivante écraserait la modification en silence.
- Un événement transféré puis supprimé ou annulé dans Google apparaît comme
  « Rendez-vous supprimé dans Google Agenda ».
- Les séries apparaissent comme rendez-vous individuels sur la période
  d'import et peuvent être acceptées ou rejetées en groupe.

La **Boîte de rapprochement** est accessible aux personnes autorisées à gérer
la facturation. Indépendamment du mode bidirectionnel, l'import des pointages
et des temps de projet propose l'agenda connecté comme source.

## Déconnecter et reconnecter

**Déconnecter** retire l'accès. Les rendez-vous déjà transférés restent dans
l'agenda Google. Avec **Connecter à Google**, vous rétablissez la connexion à
tout moment ; l'agenda choisi reste enregistré, et le compteur d'erreurs
repart à zéro. Tant que la connexion est perturbée, une tâche d'exploitation
la signale.

## Problèmes fréquents

- **Pas de bouton de connexion :** aucun client OAuth n'est enregistré (voir
  prérequis).
- **« Le flux OAuth a expiré ou est invalide. Veuillez recommencer. »** La
  connexion a pris trop de temps ou a été terminée dans une autre session.
  Relancez la connexion.
- **« La connexion a été refusée ou annulée. »** Le consentement a été refusé,
  ou Google n'autorise pas l'application pour ce compte – par exemple parce
  qu'elle n'est pas encore validée. Vérifiez l'écran de consentement dans la
  Google Cloud Console.
- **Badge Injoignable :** l'API Google Calendar est injoignable ou refuse
  l'accès, par exemple après une révocation dans le compte Google.
  **Déconnecter** puis se reconnecter.
- **« L'agenda sélectionné est introuvable. »** L'agenda a été supprimé, ou le
  compte a perdu l'accès. Choisissez-en un autre.
- **Mise à l'arrêt :** après des erreurs répétées consécutives, WorkDiary met
  la connexion à l'arrêt ; **Connecter à Google** réapparaît alors. Vérifiez la
  cause et reconnectez-vous.
