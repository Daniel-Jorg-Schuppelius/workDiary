---
title: "Connexion Zammad"
topic: admin.zammad
version: 1
keywords:
    - Zammad
    - helpdesk
    - importer des tickets
    - système de tickets
    - tickets en tâches
    - associer une file
    - ID de groupe
    - webhook
    - clôturer un ticket
    - retour de statut
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - helpdesk.overview
    - admin.data-ownership
    - admin.scheduler
    - projects.manage
---

La page **Zammad** importe dans WorkDiary, sous forme de tâches, les tickets du
système de tickets Zammad, afin que vous puissiez y saisir des temps, tenir
des justificatifs et facturer. Zammad reste le système de référence ; une
réimportation ne crée jamais de doublons. En option, WorkDiary signale au
ticket les tâches terminées. Vous trouvez la page dans le menu système (la
roue dentée **Système** dans l’en-tête) sous **Plugins** → **Zammad**, dès que
le plugin est actif.

## Prérequis

- Le plugin est activé pour votre organisation : **Système** → **Plugins** →
  **Plugins**, puis **Activer** sur l’entrée Zammad. Vous configurez la
  connexion elle-même sur la page **Zammad**, pas dans la boîte de dialogue du
  plugin.
- La page est réservée aux administrateurs.
- Il vous faut l’adresse de votre instance Zammad et un jeton API (dans Zammad
  sous Profil → Accès par jeton). Le jeton doit pouvoir lire les tickets et,
  si vous utilisez le retour de statut, aussi les modifier.
- L’instance doit être joignable publiquement. WorkDiary refuse les adresses
  d’un réseau interne.
- Il existe exactement une connexion Zammad par organisation.

## Configurer la connexion

Dans la section **Connexion**, vous renseignez :

- **Libellé** : un nom de votre choix.
- **URL de l'instance** : l’adresse à laquelle vous ouvrez Zammad dans le
  navigateur. Elle doit commencer par http:// ou https://.
- **Jeton API** : obligatoire au premier enregistrement. Il est stocké
  chiffré ; par la suite, un champ vide conserve le jeton enregistré.
- **Secret du webhook (facultatif)** : secret partagé pour les appels webhook
  de Zammad qui déclenchent immédiatement l’import. Un champ vide conserve le secret
  enregistré lors de l’enregistrement. La page n’affiche
  pas l’adresse du webhook ; sans webhook, l’interrogation régulière récupère
  les tickets.
- **Projet par défaut** : cible des tickets dont le groupe n’est associé à
  aucun projet. **— sans projet (global) —** les crée comme tâches globales
  sans projet.
- **Retour de statut (état cible)** : facultatif, voir plus bas.
- **Actif** : active ou désactive la connexion.

**Enregistrer** applique vos saisies. Lorsque la connexion est active, la
page affiche son état (par exemple **État OK**) et **Tester la connexion**.

## File → projet

Sous **File → projet**, vous associez des groupes Zammad à un projet
WorkDiary : à gauche l’**ID de groupe** issu de Zammad, à droite le projet. Il
y a toujours trois lignes vides ; pour d’autres groupes, enregistrez puis
saisissez-les. WorkDiary écarte à l’enregistrement les lignes sans ID de
groupe ou sans projet. La sélection de projets affiche au plus 500 projets.

Un ticket arrive dans le projet de son groupe, sinon dans le **Projet par
défaut**, sinon comme tâche globale.

## Import et planification

- Toutes les 15 minutes, WorkDiary interroge Zammad. La fréquence se modifie
  sous **Tâches planifiées**.
- **Importer maintenant** lance un import en arrière-plan.
- Avec un secret de webhook, un webhook de Zammad déclenche en plus l’import
  immédiatement. S’il fait défaut, l’interrogation régulière rattrape.
- WorkDiary récupère les tickets que le jeton API peut voir – pas seulement
  les groupes associés.
- Chaque ticket devient une tâche une seule fois. Son titre se compose du
  numéro et du titre du ticket, et elle est facturable. Les tickets clôturés
  ou fusionnés arrivent comme tâches terminées.
- WorkDiary ne reprend pas les modifications ultérieures du ticket (titre,
  statut, groupe) ; la tâche reste telle qu’elle a été créée.
- Si, d’après la **Maîtrise des données**, un autre système pilote les tâches,
  WorkDiary ne crée pas de tâche mais un cas dans la Boîte de rapprochement.

## Reconnaître les clients

Si un ticket contient l’e-mail d’un client ou une organisation, WorkDiary
cherche le client correspondant. En cas de correspondance univoque, il
déplace la tâche dans un projet de ce client, de préférence son projet par
défaut. Sinon, une suggestion est créée dans la Boîte de rapprochement, où
vous confirmez ou choisissez le client.

## Retour de statut

Saisissez un état Zammad sous **Retour de statut (état cible)**, par exemple
closed. Lorsqu’une personne passe une tâche liée à **Terminé** dans WorkDiary,
WorkDiary met le ticket dans cet état et ajoute une note interne « Résolu dans
WorkDiary. ». Le transfert s’exécute en arrière-plan et est répété en cas
d’erreur. Un champ vide désactive le retour. WorkDiary n’écrit aucune autre
donnée dans Zammad.

## Limites

- Une exécution ne récupère que la première page de la liste des tickets, au
  plus 100 tickets.
- **Déconnecter** ne fait que désactiver la connexion. Les tâches et les liens
  sont conservés, et rien ne change dans Zammad. Pour la réactiver, cochez
  **Actif** et enregistrez.
- Les tâches ne sont jamais supprimées, même si le ticket disparaît dans
  Zammad.

## Erreurs fréquentes

- « L'URL de l'instance doit commencer par http:// ou https://. » : saisissez
  l’adresse complète.
- « Une nouvelle connexion nécessite un jeton API. » : le jeton manque au
  premier enregistrement.
- « Aucune connexion Zammad active. » avec **Importer maintenant** : la
  connexion est désactivée ou incomplète.
- **État défaillant** avec « API Zammad inaccessible ou jeton invalide. » :
  vérifiez l’adresse et le jeton. Une erreur de l’API Zammad avec
  RuntimeException indique souvent une adresse d’un réseau interne.
- Des tickets arrivent dans le mauvais projet : vérifiez les ID de groupe sous
  **File → projet** et les suggestions de clients dans la boîte.
