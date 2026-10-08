---
title: "Connexion Todoist"
topic: admin.todoist
version: 1
keywords:
    - Todoist
    - synchroniser les tâches
    - synchronisation des tâches
    - connecter Todoist
    - association de projets
    - préverification
    - sections
    - associer les responsables
    - kanban
    - conflit de tâche
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - work.overview
    - projects.manage
    - admin.scheduler
---

La page **Todoist** synchronise les tâches entre WorkDiary et Todoist. Seuls
les projets Todoist que vous associez explicitement à un projet WorkDiary ou
au kanban global sont synchronisés ; les conflits arrivent dans la boîte de
réception d’intégration, et rien n’est écrasé ni supprimé en silence. Vous
trouvez la page dans le menu système (la roue dentée **Système** dans
l’en-tête) sous **Plugins** → **Todoist**, dès que le plugin est actif.

## Prérequis

- Le plugin est activé pour votre organisation : **Système** → **Plugins** →
  **Plugins**, puis **Activer** sur l’entrée Todoist.
- La page est réservée aux administrateurs.
- Une application Todoist enregistrée est nécessaire. Soit l’exploitant de
  votre installation en a déposé une, soit vous saisissez la vôtre : sur la
  page **Plugins** via **Configurer** sur l’entrée Todoist, dans les champs
  **ID client (application Todoist propre)** et **Secret client**. Vides,
  c’est l’application de l’installation qui s’applique. Votre propre
  application doit enregistrer dans Todoist, comme URI de redirection,
  l’adresse de votre installation WorkDiary avec le chemin
  `/admin/todoist/oauth/callback`.
- Il existe exactement une connexion Todoist, donc un compte Todoist, par
  organisation.

## Se connecter à Todoist

1. Ouvrez la page **Todoist**. La section **Connexion** indique au préalable
   quelles données sont transmises : titres, descriptions, statuts, échéances
   et responsables des tâches associées. WorkDiary ne demande aucun droit de
   suppression.
2. Cliquez sur **Se connecter à Todoist** et connectez-vous à Todoist. Après
   l’autorisation, vous revenez sur la page.
3. La page affiche ensuite **Statut**, **Compte**, **Connecté depuis** et
   **Dernière synchronisation**. **Renouveler la connexion** vous reconnecte,
   **Déconnecter** met fin à la connexion ; les associations et les liens sont
   conservés.

## Associer des projets

Avec une connexion active, le tableau **Associations de projets** apparaît.
Sous le tableau, vous créez une nouvelle association :

1. **Projet Todoist** : choix parmi votre compte Todoist.
2. **Cible** : **Projet WorkDiary** (choisissez alors le projet ; la liste
   affiche au plus 500 projets) ou **Kanban global** pour les tâches sans
   projet.
3. **Direction** : **Todoist → WorkDiary**, **WorkDiary → Todoist** ou
   **Bidirectionnel**.
4. **Associer**. Chaque nouvelle association commence comme **Brouillon** et
   ne synchronise encore rien.

Dans le tableau, vous ouvrez la **Préverification** de chaque association, la
basculez avec **Activer** ou **Mettre en pause** et la supprimez avec l’icône
de corbeille (les références sont conservées). La colonne **Dernière
exécution** indique l’heure et les compteurs : créées, mises à jour,
inchangées et conflits.

## Préverification : responsables et sections

Avant l’activation, la **Préverification** montre ce que la synchronisation
va trouver :

- **Indicateurs** : tâches actives, sous-tâches, tâches récurrentes, échéances
  avec heure, responsables non associables et tâches déjà liées.
- **Association des responsables** : pour chaque collaborateur Todoist, vous
  choisissez un utilisateur WorkDiary et cliquez sur **Enregistrer**. Une
  adresse e-mail identique n’apparaît que comme **Suggestion** ; l’affectation
  ne vaut qu’après votre choix. Sans association, une tâche reste sans
  responsable.
- **Sections → statut** : pour chaque section Todoist, vous choisissez
  **Ouvert** ou **En cours**. Les sections non associées laissent le statut
  inchangé.

Ensuite seulement, vous mettez l’association en service avec **Activer**.

## Ce qui est synchronisé

- **Todoist → WorkDiary** : chaque tâche Todoist active devient une tâche
  WorkDiary dans le projet cible ou dans le kanban global. Sont synchronisés
  le titre, la description, la priorité (Todoist p1 à p4 correspondent à
  Urgente, Haute, Moyenne, Basse), l’échéance, la durée comme budget de
  temps, le responsable et le statut. Terminée dans Todoist signifie
  **Terminé** ici. Les sous-tâches restent sous leur tâche parente.
- **WorkDiary → Todoist** : les nouvelles tâches créées après l’activation
  dans le projet associé ou dans le kanban global sont créées dans Todoist par
  WorkDiary. Les modifications des tâches liées sont aussi transmises ; un
  changement de statut déplace la tâche dans la section associée, ou la
  termine ou la rouvre. Les tâches qui existaient déjà avant l’activation ne
  sont pas transférées.
- **Bidirectionnel** combine les deux directions.
- Pour les tâches liées, la boîte de dialogue de la tâche affiche le lien
  **Ouvrir dans Todoist**.

## Quand la synchronisation a lieu

- Toutes les heures, WorkDiary récupère auprès de Todoist les modifications
  depuis la dernière exécution. La fréquence se modifie sous **Tâches
  planifiées**.
- **Synchroniser maintenant** lance une synchronisation complète en
  arrière-plan. Seule celle-ci remarque aussi les tâches disparues du projet
  Todoist sans avoir été supprimées, par exemple parce qu’elles ont été
  déplacées.
- Les modifications issues de WorkDiary partent aussitôt via une file
  d’attente et sont répétées en cas d’erreur.
- Si vous utilisez votre propre application Todoist, vous pouvez y saisir en
  plus un webhook vers l’adresse de votre installation avec le chemin
  `/api/webhooks/todoist`. Il déclenche une synchronisation ciblée lors de
  modifications ; l’exécution horaire reste la source fiable.

## Conflits et suppressions

- WorkDiary compare chaque champ avec son état lors de la dernière
  synchronisation. Si un champ a été modifié différemment des deux côtés, un
  conflit est créé dans la boîte ; vous y décidez quel état s’applique.
  Jusque-là, WorkDiary ne transmet pas ce champ.
- WorkDiary ne propage les suppressions dans aucune direction. Si une tâche
  disparaît dans Todoist ou si une tâche liée est supprimée ici, un cas est
  créé dans la boîte.
- Une sous-tâche dont la tâche parente manque ici arrive aussi dans la boîte.
- Si une tâche a été terminée dans WorkDiary, sa réouverture dans Todoist ne
  la rétablit pas.

**Boîte de réception d'intégration** sur la page ouvre la Boîte de
rapprochement filtrée sur Todoist.

## Erreurs fréquentes

- « Todoist n'est pas configuré » : aucune application Todoist n’est
  enregistrée – ni par l’exploitant ni dans les paramètres du plugin.
- « État OAuth non valide ou expiré » : la connexion a duré trop longtemps ou
  s’est faite dans une autre session. Reconnectez-vous.
- « Échec de l'échange de jeton » : l’ID client, le secret client ou l’URI de
  redirection de votre propre application sont incorrects.
- La liste des projets Todoist est vide : la connexion n’atteint pas Todoist.
  Vérifiez l’état sur la page **Plugins** et renouvelez la connexion.
- Les tâches arrivent sans responsable : le collaborateur Todoist n’est pas
  encore associé à un utilisateur dans la préverification.
- Rien n’est synchronisé : l’association est encore en **Brouillon** ou **En
  pause**.
