---
title: "Import de tâches Gitea/Forgejo"
topic: admin.gitea
version: 1
keywords:
    - Gitea
    - Forgejo
    - importer des tickets
    - dépôt
    - auto-hébergé
    - jeton d’accès
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

La page **Gitea/Forgejo** importe les tickets de dépôts sélectionnés de votre
instance Gitea ou Forgejo auto-hébergée comme tâches dans vos projets WorkDiary.
Vous saisissez ainsi des temps sur le travail qui y est planifié. L’import ne
fait que lire : WorkDiary n’écrit rien dans l’instance. Vous trouvez la page dans
le menu système (la roue dentée **Système**) sous **Plugins** → **Gitea/Forgejo**,
dès que le plugin est actif.

## Configurer l’accès

1. Dans Gitea ou Forgejo, créez sous **Paramètres** → **Applications** un jeton
   d’accès avec les portées **read:user**, **read:repository** et
   **read:issue**. Un jeton limité à certains dépôts ne voit que ceux-ci, y
   compris dans la boîte de dialogue.
2. Sur la page, ouvrez **Identifiants** dans le menu ⋯ et saisissez l’**URL de
   base** (p. ex. https://git.example.com, sans /api/v1) et le **Jeton
   d’accès**. Si l’instance se trouve dans votre propre réseau, cochez aussi
   **Autoriser les adresses privées** – effectif seulement si l’exploitant
   l’autorise.
3. Cliquez sur **Vérifier la connexion**. WorkDiary se connecte à l’instance et
   affiche instance, adresse et compte.

Tant que des associations existent, aucune autre instance ne peut être
saisie ; les tâches importées restent clairement liées à leur instance.

## Associer des dépôts et vérifier l’aperçu

1. **Associer un projet** ouvre une boîte de dialogue avec les dépôts
   accessibles au jeton et les projets WorkDiary. Chaque dépôt a exactement une
   cible.
2. L’**Aperçu** montre les tickets ouverts et ce que la première
   synchronisation en ferait. Il n’enregistre rien.
3. Liez des tâches existantes du projet cible via **Lier la tâche homonyme** ou
   **Lier une tâche existante** ; les champs divergents vont dans la boîte de
   réception pour vérification.
4. **Confirmer l’import** libère l’association. Ce n’est qu’alors que la
   synchronisation crée les tickets ouverts comme tâches ; les tickets fermés
   jamais.

## Ce qui est importé

- **Titre**, **description** en texte et **échéance** comme date, telle que
  l’instance l’affiche.
- **Statut** : fermé → **Terminé**, ouvert → **Ouvert**. Si une tâche est **En
  cours** dans WorkDiary, elle le reste.
- Les pull requests ne sont pas importées. Si un dépôt est renommé, l’import
  continue.
- Dans la boîte de dialogue de la tâche, **Ouvrir dans Gitea/Forgejo** mène au
  ticket.

## Quand la synchronisation a lieu

Toutes les 15 minutes, WorkDiary récupère les tickets modifiés depuis la
dernière exécution, une fois par jour tous les tickets. Modifiez la cadence sous
**Jobs planifiés** ; **Synchroniser maintenant** dans le menu ⋯ lance
immédiatement. Une exécution interrompue est **incomplète** ; la suivante reprend
au dernier état complet.

## Conflits et cas à vérifier

Des modifications différentes des deux côtés donnent un conflit dans la boîte de
réception ; rien n’est réécrit dans l’instance. Si un ticket lié n’est plus
visible, votre tâche reste inchangée et la boîte de réception signale un cas à
vérifier. Si la **Souveraineté des données** confie les tâches à un autre
système, WorkDiary n’importe rien. **Cas à vérifier** dans le menu ⋯ ouvre la
boîte de réception filtrée sur Gitea/Forgejo.

## Mettre en pause, supprimer, déconnecter

**Mettre en pause** et **Reprendre** arrêtent et relancent la synchronisation
d’une association ; **Supprimer l’association** conserve les tâches et temps
importés. **Déconnecter** arrête la synchronisation et met toutes les
associations en pause. Révoquez le jeton dans Gitea/Forgejo ou supprimez-le sous
**Identifiants** ; une nouvelle vérification de la même instance reprend les
associations.

## Erreurs fréquentes

- La vérification signale une adresse interne : l’URL de base pointe vers votre
  réseau, mais **Autoriser les adresses privées** est désactivé ou non autorisé
  par l’exploitant.
- La vérification échoue avec 401 : le jeton est erroné, expiré ou sans accès
  en lecture.
- Un dépôt manque dans la boîte de dialogue : le jeton ne le voit pas, par
  exemple parce qu’il est limité à certains dépôts.
- La vérification échoue avec 403 : il manque au jeton l’une des portées
  **read:user**, **read:repository** ou **read:issue**.
