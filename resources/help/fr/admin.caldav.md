---
title: "Calendrier CalDAV"
topic: admin.caldav
version: 1
keywords:
    - CalDAV
    - calendrier Nextcloud
    - calendrier ownCloud
    - publier des rendez-vous
    - s'abonner au calendrier
    - planning dans le calendrier
    - congés dans le calendrier
    - synchronisation bidirectionnelle
    - mot de passe d'application
    - chemin du calendrier
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - events.manage
    - planning.shifts
    - absences.manage
    - admin.import
    - admin.notification-rules
---

La page **CalDAV** publie les rendez-vous de WorkDiary dans un calendrier
CalDAV externe, par exemple dans Nextcloud ou ownCloud – sans compte
Microsoft ni Google. Si vous le souhaitez, les plannings et congés s’y
ajoutent, et les modifications faites dans le calendrier peuvent revenir sous
forme de propositions. WorkDiary reste le système de référence : les
rendez-vous annulés y disparaissent, et les exécutions répétées ne créent
jamais de doublons. Vous trouvez la page dans le menu système (la roue dentée
**Système** dans l’en-tête) sous **Plugins** → **CalDAV**, dès que le plugin
est actif.

## Prérequis

- Le plugin est activé pour votre organisation : **Système** → **Plugins** →
  **Plugins**, puis **Activer** sur l’entrée CalDAV. Vous configurez la
  connexion elle-même sur la page **CalDAV**, pas dans la boîte de dialogue du
  plugin.
- La page est réservée aux administrateurs.
- Il vous faut un calendrier sur le serveur CalDAV, un compte avec droit
  d’écriture sur celui-ci et un mot de passe d’application (Nextcloud :
  Paramètres → Sécurité → Mot de passe d’application).
- Le serveur doit être joignable publiquement. WorkDiary refuse les adresses
  d’un réseau interne.
- Il existe exactement une connexion CalDAV par organisation.

## Configurer la connexion

Dans la section **Connexion**, vous renseignez :

- **Libellé** : un nom de votre choix ; il apparaît aussi lors du choix d’une
  source d’import.
- **URL de base DAV** : l’adresse DAV du serveur sans chemin de calendrier,
  pour Nextcloud …/remote.php/dav. Elle doit commencer par http:// ou
  https://.
- **Nom d'utilisateur** et **Mot de passe d'application** : le mot de passe
  est obligatoire au premier enregistrement et stocké chiffré ; par la suite,
  un champ vide conserve le mot de passe enregistré.
- **Chemin du calendrier (collection)** : le chemin du calendrier relatif à
  l’URL de base, par exemple calendars/team/planning. Si vous collez une
  adresse complète copiée depuis Nextcloud, WorkDiary la raccourcit lui-même,
  pourvu qu’elle commence par l’URL de base.
- **Actif** : active ou désactive la connexion.
- **Bidirectionnel : importer les modifications externes comme
  propositions** : voir plus bas.
- **Contenu publié** : **Événements** et/ou **Plannings & congés**. Sans
  sélection, seuls les événements sont publiés.

**Enregistrer** applique vos saisies. Lorsque la connexion est active, la
page affiche son état (par exemple **État OK**) et **Tester la connexion**.

## Ce qui est publié

- **Événements :** les événements de votre organisation qui commencent entre
  30 jours dans le passé et 180 jours dans le futur. WorkDiary retire du
  calendrier les événements annulés.
- **Plannings & congés :** les services publiés ou confirmés avec horaires à
  partir de deux mois dans le passé, ainsi que les congés approuvés terminés
  depuis un an au plus. Les services en brouillon, sans horaires ou annulés
  et les congés qui ne sont plus approuvés sont retirés ou jamais créés.
- Les règles de notification avec le canal **Calendrier** déposent aussi des
  notifications de type rendez-vous dans les connexions avec **Événements**.
- Les entrées modifiées sont mises à jour ; les entrées inchangées ne sont
  pas renvoyées.

## Quand la publication a lieu

- Une fois par jour (par défaut à 04:35), WorkDiary synchronise le
  calendrier. La fréquence se modifie sous **Tâches planifiées**.
- **Publier maintenant** lance immédiatement la synchronisation en
  arrière-plan – par exemple après la configuration ou après de nombreuses
  modifications.
- Les rendez-vous nouveaux ou modifiés n’apparaissent donc dans le calendrier
  qu’après l’exécution suivante. Seules les notifications passant par le
  canal **Calendrier** partent immédiatement.

## Bidirectionnel : modifications depuis le calendrier

Le réimport reste désactivé tant que vous n’activez pas **Bidirectionnel :
importer les modifications externes comme propositions**. WorkDiary lit
alors le calendrier toutes les heures, dans une fenêtre allant de 30 jours en
arrière à 180 jours en avant :

- Les nouvelles entrées du calendrier deviennent des propositions dans la
  Boîte de rapprochement. Rien n’est créé sans demande.
- Les rendez-vous récurrents apparaissent comme un groupe avec leurs
  occurrences dans la fenêtre ; WorkDiary tient compte des occurrences
  déplacées et annulées. Le groupe peut être créé comme rendez-vous ou écarté
  en une fois.
- Les modifications externes de rendez-vous publiés apparaissent comme un
  conflit, les entrées supprimées dans le calendrier comme un cas
  « Rendez-vous supprimé dans l’agenda CalDAV ». WorkDiary ne supprime rien
  lui-même à cette occasion.

La Boîte de rapprochement est accessible aux administrateurs et à la
comptabilité.

## Le calendrier comme source d’import

Dans l’import CSV (**Transfert de données** → **Import**), vous pouvez choisir
le calendrier CalDAV au lieu d’un fichier comme source pour les pointages et
les temps de projet. Les connexions actives sont proposées avec leur
**Libellé** ; WorkDiary lit alors les entrées de la période choisie.

## Déconnecter

**Déconnecter** désactive la connexion. Les entrées déjà publiées restent
dans le calendrier. Pour la réactiver, cochez **Actif** et enregistrez.

WorkDiary ne retire pas non plus du calendrier les événements supprimés ni
les entrées qui sortent de la fenêtre de temps. Si un rendez-vous doit y
disparaître, annulez-le au lieu de le supprimer.

## Erreurs fréquentes

- « L'URL de base doit commencer par http:// ou https://. » : saisissez
  l’adresse complète.
- « L'URL du calendrier ne se trouve pas sous l'URL de base. » : le lien collé
  ne correspond pas à l’URL de base DAV. Indiquez le chemin relatif à l’URL de
  base.
- « Une nouvelle connexion nécessite un mot de passe d'application. » : le mot
  de passe manque au premier enregistrement.
- **État défaillant** avec « Serveur CalDAV injoignable ou identifiants
  invalides. » : vérifiez l’adresse, le chemin du calendrier, le nom
  d’utilisateur et le mot de passe d’application. Une erreur CalDAV avec
  RuntimeException indique souvent une adresse d’un réseau interne.
- « Aucune connexion CalDAV active. » avec **Publier maintenant** : la
  connexion est désactivée ou incomplète.
- Les plannings manquent dans le calendrier : **Plannings & congés** n’est pas
  coché sous **Contenu publié**, ou les services ne sont pas encore publiés.
