---
title: "Connecter un carnet d'adresses CardDAV"
topic: admin.carddav
version: 1
keywords:
    - CardDAV
    - connecter un carnet d'adresses
    - contacts Nextcloud
    - Radicale
    - Baïkal
    - importer des contacts
    - rapprocher des contacts
    - mot de passe d'application
    - vCard
    - synchronisation des contacts
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - contacts.manage
    - admin.scheduler
---

La page **CardDAV** relie WorkDiary à un carnet d'adresses situé sur votre
propre serveur CardDAV, par exemple Nextcloud, Radicale ou Baïkal. WorkDiary
lit uniquement les contacts et vous les propose pour un rapprochement avec vos
clients. Il n'écrit jamais rien dans le carnet d'adresses, ne fusionne aucun
enregistrement de lui-même et ne crée aucun client. Vous n'avez besoin ni d'un
compte Microsoft ni d'un compte Google.

## Prérequis

- Le plugin **CardDAV** est activé pour votre organisation sous **Plugins**.
  L'entrée **CardDAV** apparaît ensuite dans le menu système (icône d'engrenage
  **Système**), dans le groupe **Plugins**.
- Vous connaissez l'adresse du serveur CardDAV, un nom d'utilisateur et un mot
  de passe. Si la connexion en deux étapes est active sur le serveur (courant
  avec Nextcloud), il vous faut un mot de passe d'application, que vous créez
  dans votre compte sur le serveur.
- La page est réservée aux administrateurs de votre organisation. Chaque
  organisation dispose d'une seule connexion CardDAV.

## Configurer la connexion

1. Remplissez les champs de la section **Connexion** :
   - **Désignation** : un nom pour la connexion, par exemple « Nextcloud
     bureau ».
   - **URL de base DAV** : pour Nextcloud, l'adresse jusqu'à /remote.php/dav
     inclus ; pour Radicale et Baïkal, la racine du serveur. L'adresse doit
     commencer par http:// ou https://.
   - **Nom d'utilisateur** et **Mot de passe d'application**. Le mot de passe
     est enregistré chiffré et n'est plus jamais affiché. Si vous laissez le
     champ vide pour une connexion existante, le mot de passe enregistré reste
     valable.
   - **Autoriser les adresses privées/internes** : à activer uniquement si le
     serveur se trouve sur votre propre réseau (par exemple 192.168.x.x). Sans
     cette option, WorkDiary refuse les adresses internes. L'activation est
     journalisée.
   - **Actif** : active ou désactive la connexion.
2. Cliquez sur **Enregistrer**.
3. Cliquez en haut sur **Rechercher les carnets d'adresses**. WorkDiary
   interroge le serveur et affiche les carnets trouvés dans la section
   **Carnet d'adresses**.
4. Sélectionnez un carnet d'adresses et cliquez sur **Utiliser ce carnet
   d'adresses**. Il devient la source de synchronisation ; la page l'affiche
   comme « Source de synchronisation actuelle ».

Seuls les carnets d'adresses issus de la dernière recherche et situés sur le
même serveur que l'URL de base peuvent être choisis. Il est impossible de
saisir une adresse quelconque comme source.

## Ce qui est synchronisé et quand

- **Sens :** uniquement du serveur CardDAV vers WorkDiary.
- **Contenu :** nom, entreprise, adresse e-mail, numéros de téléphone, de
  mobile et de fax, note ainsi que l'adresse postale avec le pays. S'il existe
  plusieurs adresses e-mail ou numéros, WorkDiary privilégie ceux marqués comme
  professionnels.
- **Moment :** la synchronisation s'exécute automatiquement toutes les heures.
  Vous modifiez la fréquence sous **Tâches planifiées**. **Synchroniser
  maintenant** la lance immédiatement ; elle s'exécute alors en arrière-plan, et
  la page affiche ensuite « Dernière synchronisation … ».
- **Modifications uniquement :** WorkDiary ignore les contacts inchangés. Seules
  les fiches nouvelles ou modifiées sont traitées.

## Rapprochement avec les clients

- Si un contact correspond sans ambiguïté à un seul client, WorkDiary relie les
  deux. Si un contact relié change par la suite et que ses informations
  diffèrent de celles du client, un conflit de champ est créé dans la **Boîte
  de rapprochement** – les données client ne sont jamais écrasées en silence.
- Tous les autres contacts (aucun client correspondant ou plusieurs candidats)
  arrivent comme propositions dans la **Boîte de rapprochement**. Vous y
  affectez le contact à un client, le créez comme nouvel enregistrement ou le
  rejetez.
- Si un contact est supprimé dans le carnet d'adresses, WorkDiary rejette sa
  proposition encore ouverte. Les affectations déjà effectuées sont conservées.
- La **Boîte de rapprochement** est accessible aux personnes autorisées à
  gérer la facturation.

## Modifier, déconnecter, recommencer

- Si vous modifiez l'**URL de base DAV**, WorkDiary abandonne le carnet
  d'adresses choisi et l'état de synchronisation précédent. Recherchez et
  choisissez ensuite de nouveau le carnet d'adresses.
- Si vous choisissez un autre carnet d'adresses, la synchronisation repart de
  zéro.
- **Déconnecter** rend la connexion inactive. Les propositions déjà créées sont
  conservées. Pour reprendre, réactivez **Actif** et cliquez sur
  **Enregistrer**.

## Problèmes fréquents

- **Adresse interne refusée :** le message « L'URL de base pointe vers une
  adresse privée/interne » apparaît lorsque le serveur se trouve sur votre
  propre réseau. Activez **Autoriser les adresses privées/internes**.
- **La recherche échoue :** « Échec de la recherche des carnets d'adresses »
  signifie que le serveur est injoignable ou que les identifiants sont
  incorrects. Vérifiez l'URL de base, le nom d'utilisateur et le mot de passe
  d'application.
- **Aucun carnet d'adresses :** « Aucun carnet d'adresses trouvé sur le
  serveur » – le compte n'a pas de carnet d'adresses, ou l'URL de base pointe
  vers le mauvais niveau.
- **Carnet d'adresses étranger :** « L’adresse n’appartient pas au serveur
  CardDAV configuré » – le carnet choisi se trouve sur un autre serveur que
  l'URL de base.
- **Pas de synchronisation :** si **Synchroniser maintenant** manque ou si
  WorkDiary signale « Synchronisation impossible », la connexion est inactive,
  aucun carnet d'adresses n'est choisi, ou elle a été mise à l'arrêt après des
  erreurs répétées consécutives. La page affiche la dernière erreur en haut.
- **Vérifier l'état :** à côté du titre de la page figure le dernier état
  vérifié de la connexion. **Tester la connexion** le vérifie immédiatement.
