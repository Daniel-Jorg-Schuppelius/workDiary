---
title: "Import de tâches Asana"
topic: admin.asana
version: 1
keywords:
    - Asana
    - importer des tâches
    - espace de travail
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

La page **Asana** importe les tâches de projets Asana sélectionnés dans vos
projets WorkDiary. Vous saisissez ainsi des temps sur le travail planifié dans
Asana et le facturez comme d’habitude. L’import ne fait que lire : WorkDiary
n’écrit rien dans Asana. Vous trouvez la page dans le menu système (la roue
dentée **Système**) sous **Plugins** → **Asana**, dès que le plugin est actif.

## Prérequis

- Le plugin est activé pour votre organisation : **Système** → **Plugins** →
  **Plugins**, puis **Activer** sur l’entrée Asana.
- Le module Kanban est sous licence ; la page est ouverte aux administrateurs.
- L’exploitant de votre installation a configuré l’application Asana de
  l’éditeur. Si elle manque, la page l’indique ; un jeton d’accès personnel
  n’est ni nécessaire ni prévu.
- Chaque organisation dispose d’exactement une connexion Asana et d’un espace
  de travail.

## Se connecter et choisir l’espace de travail

1. Cliquez sur **Se connecter à Asana** et identifiez-vous chez Asana.
   WorkDiary ne demande qu’un accès en lecture aux tâches, projets, espaces de
   travail et à votre propre compte.
2. De retour sur la page, choisissez l’espace de travail sous **Choisir Espace
   de travail** et cliquez sur **Appliquer**.
3. Tant que des associations de projets existent, l’espace de travail ne peut
   pas être changé.

## Associer des projets et vérifier l’aperçu

1. **Associer un projet** ouvre une boîte de dialogue : choisissez le **Projet
   Asana** et le **Projet WorkDiary** auquel les tâches appartiennent.
2. L’**Aperçu** montre les tâches ouvertes et ce que la première
   synchronisation en ferait. Il n’enregistre rien.
3. Liez des tâches existantes du projet cible via **Lier la tâche homonyme**
   ou la section **Lier une tâche existante**. Les champs divergents vont dans
   la boîte de réception pour vérification.
4. **Confirmer l’import** libère l’association. Ce n’est qu’alors que la
   synchronisation crée les tâches ouvertes. Les tâches terminées ne sont
   jamais créées.

## Ce qui est importé

- **Titre**, **notes** comme description et **échéance** comme date. Si une
  tâche Asana a une heure d’échéance, la date compte dans le fuseau horaire de
  votre organisation.
- **Statut** : terminée dans Asana → **Terminé**, sinon **Ouvert**. Si la
  tâche est **En cours** dans WorkDiary, elle le reste – Asana ne connaît pas
  d’état d’avancement.
- Une tâche présente dans plusieurs projets associés à la même cible n’est
  créée qu’une fois. Si les projets pointent vers des cibles différentes,
  WorkDiary ne la crée pas et signale un cas à vérifier.
- Une tâche que vous avez terminée dans WorkDiary n’est pas rouverte par
  Asana.
- Dans la boîte de dialogue de la tâche, **Ouvrir dans Asana** mène à la
  tâche.

## Quand la synchronisation a lieu

- Toutes les 15 minutes, WorkDiary lit les tâches ouvertes et celles terminées
  depuis la dernière exécution, une fois par jour toutes les tâches. Modifiez
  la cadence sous **Jobs planifiés** ; **Synchroniser maintenant** dans le
  menu ⋯ lance immédiatement.
- Si une exécution est interrompue, elle est incomplète, et l’exécution
  suivante reprend au dernier état complet.

## Conflits et cas à vérifier

- Des modifications différentes des deux côtés donnent un conflit dans la
  boîte de réception ; rien n’est réécrit dans Asana.
- Si une tâche liée n’est plus visible dans Asana, votre tâche reste inchangée
  et la boîte de réception signale un cas à vérifier.
- Si la **Souveraineté des données** confie les tâches à un autre système,
  WorkDiary n’importe rien.
- **Cas à vérifier** dans le menu ⋯ ouvre la boîte de réception filtrée sur
  Asana.

## Mettre en pause, supprimer, déconnecter

- **Mettre en pause** et **Reprendre** arrêtent et relancent la
  synchronisation d’une association ; **Supprimer l’association** conserve les
  tâches et temps importés.
- **Déconnecter** révoque l’autorisation chez Asana, supprime la connexion et
  met toutes les associations en pause. Reconnectées au même espace de
  travail, elles reprennent.

## Erreurs fréquentes

- « Asana n’est pas configuré pour cette installation » : l’enregistrement de
  l’application manque ; adressez-vous à l’exploitant.
- La connexion signale une authentification refusée : l’autorisation a été
  retirée dans Asana. Reconnectez-vous.
- Rien n’est créé : l’association n’est pas encore confirmée, elle est en
  pause, ou les tâches sont déjà terminées.
