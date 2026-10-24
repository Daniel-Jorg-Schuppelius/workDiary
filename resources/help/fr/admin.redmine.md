---
title: "Import de tâches et de temps Redmine"
topic: admin.redmine
version: 1
keywords:
    - Redmine
    - importer des tickets
    - importer des saisies de temps
    - clé d’API
    - auto-hébergé
    - association des utilisateurs
    - import de tâches
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

La page **Redmine** importe les tickets de projets Redmine sélectionnés comme
tâches dans des projets WorkDiary et, si souhaité, les saisies de temps qui y sont
enregistrées comme temps réservés. L’import ne fait que lire : WorkDiary n’écrit
rien dans Redmine. Vous trouvez la page dans le menu système (la roue dentée
**Système**) sous **Plugins** → **Redmine**, dès que le plugin est actif.

## Configurer l’accès

1. Dans Redmine, activez le service web REST sous **Administration** →
   **Configuration** → **API** et copiez la **Clé d’accès API** sous **Mon
   compte**.
2. Sur la page, ouvrez **Identifiants** dans le menu ⋯ et saisissez **URL de
   base** et **Clé d’API**. Pour une instance de votre propre réseau, cochez
   aussi **Autoriser les adresses privées**. Vous y décidez aussi si les temps
   importés sont facturables (**Importer les temps comme facturables**) et
   combien de jours en arrière chaque exécution lit (**Fenêtre de temps
   (jours)**).
3. Cliquez sur **Vérifier la connexion**. Tant que des associations existent,
   aucune autre instance ne peut être saisie.

Le compte de la clé a besoin, dans chaque projet associé, d’un rôle autorisé à
voir les tickets et – pour l’import des temps – le temps passé. Réglez la
visibilité des tickets et des saisies de temps de ce rôle sur **tous** ; sinon,
WorkDiary ne reprend que ce que le compte a lui-même créé ou ce qui lui est
attribué.

## Associer des projets et vérifier l’aperçu

1. **Associer un projet** choisit un projet Redmine et le projet WorkDiary. Les
   sous-projets n’en font pas partie, ni pour les tickets ni pour les temps ;
   associez-les séparément si besoin.
2. L’**Aperçu** montre les tickets ouverts et ce que ferait la première
   synchronisation ; il n’enregistre rien. Liez des tâches existantes via **Lier
   la tâche homonyme** ou **Lier une tâche existante**.
3. **Confirmer l’import** libère l’association. Ce n’est qu’alors que des
   tâches sont créées à partir des tickets ouverts ; jamais des tickets fermés.

## Ce qui est importé

- **Titre**, **description** en texte et **échéance**.
- **Statut** : un ticket au statut fermé (marqué comme fermé dans Redmine)
  devient **Terminé**, sinon **Ouvert** ; les noms des statuts n’interviennent
  pas. Un **En cours** local reste.
- Dans la boîte de dialogue de la tâche, **Ouvrir dans Redmine** mène au ticket.

## Saisies de temps

1. Dans la section **Saisies de temps**, activez **Importer les temps** pour
   chaque association.
2. Redmine ne connaît que le jour et les heures. WorkDiary réserve donc une
   durée sans heure dans le projet cible ; si la saisie appartient à un ticket
   lié, elle appartient à la tâche de ce ticket.
3. Les temps ne sont réservés que pour des utilisateurs Redmine associés. Sous
   **Association des utilisateurs** apparaissent les utilisateurs Redmine ayant
   des saisies en attente ; choisissez l’utilisateur correspondant et cliquez sur
   **Associer**. La prochaine synchronisation réserve les saisies en attente.
4. Chaque exécution lit entièrement la fenêtre de temps. Les heures modifiées
   sont mises à jour, les saisies supprimées dans Redmine disparaissent aussi
   ici – sauf pour les temps déjà facturés : la boîte de réception signale alors
   un conflit et le temps reste inchangé.

## Quand la synchronisation a lieu

Toutes les 15 minutes, WorkDiary récupère les tickets modifiés et les saisies de
temps de la fenêtre, une fois par jour tous les tickets. Modifiez la cadence sous
**Jobs planifiés** ; **Synchroniser maintenant** dans le menu ⋯ lance
immédiatement.

## Conflits, cas à vérifier et déconnexion

Des modifications différentes des deux côtés donnent un conflit dans la boîte de
réception ; rien n’est réécrit dans Redmine. Les tickets plus visibles, les
données gérées ailleurs et les statuts inconnus sont signalés comme cas à
vérifier. **Déconnecter** arrête la synchronisation et met les associations en
pause ; les tâches et temps importés restent. Révoquez la clé dans Redmine ou
supprimez-la sous **Identifiants**.

## Erreurs fréquentes

- La vérification échoue avec 401 ou 403 : la clé d’API est erronée ou le
  service web REST est désactivé dans Redmine.
- Les temps n’apparaissent pas : **Importer les temps** est désactivé,
  l’utilisateur Redmine n’est pas encore associé ou la saisie est hors de la
  fenêtre de temps.
- La synchronisation s’arrête avec 401 : Redmine traite une clé invalide comme
  un visiteur non connecté et n’affiche alors que les données publiques.
  WorkDiary vérifie donc le compte avant chaque exécution et ne lit rien avec
  une clé invalide.
