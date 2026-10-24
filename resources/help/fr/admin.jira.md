---
title: "Import de tâches Jira"
topic: admin.jira
version: 1
keywords:
    - Jira
    - Jira Cloud
    - importer des tickets
    - Atlassian
    - site
    - association de projets
    - aperçu
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

La page **Jira** importe les tickets de projets Jira Cloud sélectionnés comme
tâches dans vos projets WorkDiary. Vous saisissez ainsi des temps sur le
travail planifié dans Jira et le facturez comme d’habitude. L’import ne fait
que lire : WorkDiary n’écrit rien dans Jira. Vous trouvez la page dans le menu
système (la roue dentée **Système**) sous **Plugins** → **Jira**, dès que le
plugin est actif.

## Prérequis

- Le plugin est activé pour votre organisation : **Système** → **Plugins** →
  **Plugins**, puis **Activer** sur l’entrée Jira.
- Le module Kanban est sous licence ; la page est ouverte aux administrateurs.
- L’exploitant de votre installation a configuré l’application Jira de
  l’éditeur. Si elle manque, la page l’indique ; une application propre ou un
  jeton d’API n’est ni nécessaire ni prévu.
- Chaque organisation dispose d’exactement une connexion Jira et d’un site.

## Se connecter et choisir le site

1. Cliquez sur **Se connecter à Jira** et identifiez-vous chez Atlassian.
   WorkDiary ne demande qu’un accès en lecture aux projets et aux tickets.
2. De retour sur la page, choisissez le site sous **Choisir Site** et cliquez
   sur **Appliquer**. Seuls les sites lisibles avec cette connexion sont
   proposés.
3. Tant que des associations de projets existent, le site ne peut pas être
   changé – les tâches importées restent clairement liées à leur site.

## Associer des projets et vérifier l’aperçu

1. **Associer un projet** ouvre une boîte de dialogue : choisissez le **Projet
   Jira** et le **Projet WorkDiary** auquel les tâches appartiennent. Chaque
   projet Jira a exactement une cible.
2. L’**Aperçu** s’ouvre ensuite. Il montre les tickets ouverts et ce que la
   première synchronisation en ferait : **Sera créée**, **Déjà liée**, **Autre
   cible**, **Cas à vérifier** ou **Gérée ailleurs**. L’aperçu n’enregistre
   rien.
3. Si le projet cible contient déjà une tâche correspondante, liez-la au lieu
   d’en créer une nouvelle : à titre identique via **Lier la tâche homonyme**,
   sinon dans la section **Lier une tâche existante**. Les champs divergents
   vont dans la boîte de réception pour vérification ; les champs vides sont
   remplis depuis Jira.
4. **Confirmer l’import** libère l’association. Ce n’est qu’alors que la
   synchronisation crée les tickets ouverts comme tâches. Les tickets déjà
   terminés ne sont jamais créés.

## Ce qui est importé

- **Titre** depuis le résumé, **description** en texte brut (images, tableaux
  et autres contenus non représentables en texte donnent un cas à vérifier ;
  une description existante reste alors inchangée), **échéance** comme date.
- **Statut** via la catégorie de statut de Jira : à faire → **Ouvert**, en
  cours → **En cours**, terminé → **Terminé**. Les noms des statuts de votre
  Jira n’interviennent pas.
- Client, temps, taux, budgets et responsables restent gérés par WorkDiary.
- Une tâche que vous avez terminée dans WorkDiary n’est pas rouverte par Jira.
  Seule une clôture venue de Jira est rouverte avec Jira.
- Dans la boîte de dialogue de la tâche, **Ouvrir dans Jira** mène au ticket.

## Quand la synchronisation a lieu

- Toutes les 15 minutes, WorkDiary récupère les tickets modifiés depuis la
  dernière exécution, une fois par jour tous les tickets des projets associés.
  Modifiez la cadence sous **Jobs planifiés**.
- **Synchroniser maintenant** dans le menu ⋯ lance immédiatement la même
  synchronisation.
- Si une exécution est interrompue (par exemple par une panne de Jira), elle
  est incomplète : l’association affiche **incomplète**, et l’exécution
  suivante reprend au dernier état complet.

## Conflits et cas à vérifier

- Si un champ a été modifié différemment dans WorkDiary et dans Jira, un
  conflit apparaît dans la boîte de réception, où vous décidez de la version
  valable. Rien n’est réécrit dans Jira.
- Des cas à vérifier apparaissent notamment quand un ticket n’est plus visible
  dans Jira (votre tâche reste inchangée, rien n’est supprimé), quand sa tâche
  se trouve dans un autre projet WorkDiary (rien n’est déplacé) ou quand un
  statut inconnu arrive.
- Si la **Souveraineté des données** confie les tâches à un autre système,
  WorkDiary n’importe rien et crée un cas par ticket.
- **Cas à vérifier** dans le menu ⋯ ouvre la boîte de réception filtrée sur
  Jira.

## Mettre en pause, supprimer, déconnecter

- **Mettre en pause** et **Reprendre** sur la ligne d’une association arrêtent
  et relancent sa synchronisation. **Supprimer l’association** dissout
  l’association ; les tâches, liens et temps importés restent.
- **Déconnecter** supprime la connexion et met toutes les associations en
  pause. Si vous vous reconnectez plus tard au même site, elles reprennent.

## Erreurs fréquentes

- « Jira n’est pas configuré pour cette installation » : l’enregistrement de
  l’application manque ; adressez-vous à l’exploitant.
- Aucun site à choisir : le compte connecté n’a d’accès en lecture à Jira sur
  aucun site.
- La connexion signale une authentification refusée : l’autorisation a été
  révoquée chez Atlassian ou a expiré. Reconnectez-vous.
- Rien n’est créé : l’association n’est pas encore confirmée, elle est en
  pause, ou les tickets sont déjà terminés.
