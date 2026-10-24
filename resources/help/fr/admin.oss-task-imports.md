---
title: "Imports de tâches depuis des outils open source"
topic: admin.oss-task-imports
version: 1
keywords:
    - Vikunja
    - Kanboard
    - Plane
    - Taiga
    - Leantime
    - FreeScout
    - GLPI
    - import de tâches
    - importer des tickets
    - auto-hébergé
    - jeton API
    - cas à vérifier
audience:
    - admin
related:
    - admin.plugins
    - admin.integration-inbox
    - admin.data-ownership
    - projects.manage
    - admin.scheduler
---

Sept plugins reprennent des éléments d’outils open source répandus comme tâches
dans des projets WorkDiary : **Vikunja**, **Kanboard**, **Plane**, **Taiga** et
**Leantime** pour la planification de projets, **FreeScout** et **GLPI** pour le
support et les tickets. Vous saisissez ainsi du temps sur le travail planifié ou
demandé là-bas. Chaque import ne fait que lire : WorkDiary n’écrit rien dans le
système source. Vous trouvez les pages dans le menu système (l’engrenage
**Système**) sous **Plugins**, dès que le plugin concerné est actif. Les sept
fonctionnent de la même manière ; ce texte indique les différences par système.

## Configurer l’accès

1. Créez dans le système source un accès en lecture (voir le tableau).
2. Sur la page du plugin, ouvrez **Identifiants** dans le menu ⋯ et saisissez
   l’**adresse** et l’accès. Si l’instance se trouve dans votre propre réseau,
   cochez aussi **Autoriser les adresses privées** – effectif seulement si
   l’exploitant l’autorise.
3. Cliquez sur **Vérifier la connexion**. WorkDiary se connecte et affiche
   l’instance, l’adresse et – si le système l’indique – le compte.

| Système | Adresse | Accès |
| --- | --- | --- |
| Vikunja | Adresse de l’instance | Jeton API de Paramètres → Jetons API |
| Kanboard | Adresse de l’instance | Jeton d’application de Paramètres → API (nom d’utilisateur vide) ou nom d’utilisateur et jeton d’accès personnel |
| Plane | https://api.plane.so ou votre propre instance | Identifiant de l’espace de travail et jeton d’accès personnel |
| Taiga | https://api.taiga.io ou votre propre instance | Nom d’utilisateur et mot de passe |
| Leantime | Adresse de l’instance | Clé API des paramètres de l’entreprise |
| FreeScout | Adresse de l’instance | Clé API du module « API & Webhooks » |
| GLPI | Adresse de l’instance | Jeton utilisateur (« clé d’accès distant »), jeton d’application si nécessaire |

Tant que des associations existent, aucune autre instance ne peut être saisie ;
les tâches importées restent ainsi clairement liées à leur instance. Pour Plane,
l’espace de travail compte comme instance.

## Prérequis par système

- **Vikunja** à partir de la version 1.0. Le jeton d’API a besoin des droits de
  lecture sur les projets et les tâches ; avec le droit « other → user », la
  vérification affiche aussi le nom du compte.
- **Kanboard** ne propose que les projets actifs ; les projets inactifs
  n’apparaissent pas.
- **Plane** à partir de la version 0.28. Seuls les projets dont le compte du
  jeton est membre sont proposés. Plane autorise 60 requêtes par minute ; la
  première synchronisation de grands projets prend donc quelques minutes.
- **Taiga** : le compte doit avoir le droit de voir les récits utilisateur du
  projet. Sans ce droit, Taiga renvoie une liste vide ; WorkDiary signale alors
  l’exécution comme incomplète au lieu de considérer des tâches comme
  supprimées.
- **Leantime** à partir de la version 3.8.
- **FreeScout** : seules les conversations publiées sont reprises, ni
  brouillons ni conversations supprimées.
- **GLPI** : l’API REST doit être activée et le compte doit avoir les droits de
  lecture sur les tickets de l’entité. WorkDiary ferme la session après chaque
  exécution.

## Associer des sources et vérifier l’aperçu

La source est un projet dans chaque système, une **boîte aux lettres** dans
FreeScout et une **entité** dans GLPI (sans sous-entités). Dans la boîte de
dialogue, boîtes aux lettres et entités apparaissent dans la liste des projets.

1. **Associer un projet** ouvre une boîte de dialogue avec les sources visibles
   par l’accès et les projets WorkDiary. Chaque source a exactement une cible.
2. L’**Aperçu** montre les éléments ouverts et ce que la première
   synchronisation en ferait. Il n’enregistre rien.
3. Liez des tâches existantes du projet cible via **Lier la tâche homonyme** ou
   **Lier une tâche existante** ; les champs divergents vont dans la boîte de
   réception pour vérification.
4. **Confirmer l’import** libère l’association. Ce n’est qu’alors que la
   synchronisation crée les éléments ouverts comme tâches ; ceux déjà terminés
   jamais.

## Ce qui est importé

Sont repris le **titre**, la **description** en texte et, si elle existe,
l’**échéance**. Le statut provient toujours d’un champ fixe du système, jamais de
noms de colonnes ou de statuts librement nommés :

| Système | Ouvert | En cours | Terminé | Échéance |
| --- | --- | --- | --- | --- |
| Vikunja | non terminé | – | terminé | date d’échéance |
| Kanboard | active | – | fermée | date d’échéance |
| Plane | groupe Backlog, Unstarted, Triage | groupe Started | groupe Completed, Cancelled | date cible |
| Taiga | ouvert | – | fermé | date d’échéance |
| Leantime | type de statut « Nouveau » | type de statut « En cours » | type de statut « Terminé » | date d’échéance |
| FreeScout | active | en attente | fermée, spam | – |
| GLPI | nouveau, approbation | attribué, planifié, en attente | résolu, clos | délai de résolution |

- Si une tâche est **En cours** dans WorkDiary, elle le reste tant que la source
  ne la ferme pas.
- Dans FreeScout, la description est le premier message de la conversation ;
  les notes internes sont exclues. Dans Taiga, WorkDiary ne lit la description
  que pour les récits utilisateur nouveaux ou modifiés.
- Si Taiga clôt un récit utilisateur parce que toutes ses tâches sont
  terminées, cela arrive avec la synchronisation suivante. Si une nouvelle tâche
  le rouvre, WorkDiary ne le voit qu’à la synchronisation quotidienne de tous
  les éléments.
- Les jalons de Leantime ne sont pas repris.
- Dans la boîte de dialogue de la tâche, **Ouvrir dans …** mène à l’original.

## Quand la synchronisation a lieu

Toutes les 15 minutes, WorkDiary récupère les éléments modifiés depuis la
dernière exécution, et une fois par jour tous. Vous modifiez la cadence sous
**Jobs planifiés** ; **Synchroniser maintenant** dans le menu ⋯ démarre
immédiatement. Une exécution interrompue est **incomplète** ; la suivante
reprend au dernier état complet.

## Conflits et cas à vérifier

Des modifications différentes des deux côtés donnent un conflit dans la boîte de
réception ; rien n’est réécrit dans le système source. Si un élément lié n’est
plus visible, votre tâche reste inchangée et la boîte de réception signale un
cas à vérifier. Si, selon la **Souveraineté des données**, un autre système
gère les tâches, WorkDiary ne reprend rien. **Cas à vérifier** dans le menu ⋯
ouvre la boîte de réception filtrée sur le plugin.

## Mettre en pause, supprimer, déconnecter

**Mettre en pause** et **Reprendre** arrêtent et relancent la synchronisation
d’une association ; **Supprimer l’association** conserve les tâches et temps
importés. **Déconnecter** met fin à la synchronisation et met en pause toutes
les associations. Vous retirez l’accès dans le système source ou le supprimez
sous **Identifiants** ; une nouvelle vérification avec la même instance reprend
les associations.

## Erreurs fréquentes

- La vérification signale une adresse interne : l’adresse pointe vers votre
  propre réseau, mais **Autoriser les adresses privées** est désactivé ou non
  permis par l’exploitant.
- La vérification échoue avec 401 : le jeton, la clé ou le mot de passe est
  erroné, expiré ou sans accès en lecture.
- La vérification signale « …VersionUnsupportedException » : l’instance est
  plus ancienne que la version minimale indiquée plus haut.
- Plane n’affiche pas un projet : le compte du jeton n’y est pas membre, même si
  le projet est public.
- Kanboard n’affiche qu’une partie des projets : avec un nom d’utilisateur,
  l’accès ne voit que les projets de cet utilisateur ; pour tous les projets,
  utilisez le jeton d’application sans nom d’utilisateur.
- Taiga refuse la connexion : les comptes qui se connectent via GitHub ou GitLab
  ont besoin de leur propre mot de passe.
- Leantime ou GLPI n’affiche aucune source : la clé API n’est associée à aucun
  projet, ou l’API REST n’est pas activée dans GLPI.
- FreeScout répond par 404 : le module « API & Webhooks » n’est pas actif.
