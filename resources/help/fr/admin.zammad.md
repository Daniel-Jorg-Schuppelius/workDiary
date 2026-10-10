---
title: "Connexion Zammad"
topic: admin.zammad
version: 3
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
    - imputation du temps dans le ticket
    - groupes associés uniquement
    - tickets de service
    - cible des tickets
    - adresses privées
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
ticket les tâches terminées et y impute les temps saisis. Vous trouvez la page dans le menu système (la
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
  si vous utilisez le retour de statut ou l’imputation du temps, aussi les
  modifier.
- L’instance doit être joignable publiquement. Si Zammad se trouve sur votre
  propre réseau, activez **Autoriser les adresses privées/internes** (voir
  plus bas).
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
  enregistré lors de l’enregistrement. Une fois la connexion enregistrée,
  l’**Adresse du webhook** s’affiche sous le champ : saisissez-la dans Zammad
  sous Webhook comme point de terminaison, avec le secret comme jeton de
  signature HMAC SHA1, et déclenchez le webhook par un déclencheur. Sans
  webhook, l’interrogation régulière récupère les tickets.
- **Projet par défaut** : cible des tickets dont le groupe n’est associé à
  aucun projet. **— sans projet (global) —** les crée comme tâches globales
  sans projet.
- **Retour de statut (état cible)** : facultatif, voir plus bas.
- **Imputation du temps dans le ticket** : facultatif, voir plus bas.
- **Autoriser les adresses privées/internes** : à activer uniquement si Zammad
  se trouve sur votre propre réseau (par exemple 192.168.x.x). Sans ce
  commutateur, WorkDiary refuse les adresses internes dès l’enregistrement.
  L’activation est journalisée. Si l’exploitant de votre installation a
  bloqué cette autorisation, le commutateur reste sans effet.
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

**Groupes associés uniquement** (désactivé par défaut) limite l’import :
lorsqu’il est activé, WorkDiary ne crée des tâches que pour les tickets des
groupes associés ici ; le **Projet par défaut** ne s’applique alors plus.
Lorsqu’il est désactivé, tous les tickets visibles par le jeton API arrivent.
Commutateur activé et aucune association : WorkDiary n’importe rien.

## Import et planification

- Toutes les 15 minutes, WorkDiary interroge Zammad. La fréquence se modifie
  sous **Tâches planifiées**.
- **Importer maintenant** lance un import en arrière-plan.
- Avec un secret de webhook, un webhook de Zammad déclenche en plus l’import
  immédiatement. S’il fait défaut, l’interrogation régulière rattrape.
- WorkDiary récupère tous les tickets que le jeton API peut voir ; avec
  **Groupes associés uniquement**, les tâches ne naissent que des groupes
  associés. Chaque exécution lit la liste complète des tickets, page par page.
- Chaque ticket ouvert devient une tâche une seule fois. Son titre se compose
  du numéro et du titre du ticket, et elle est facturable. Les tickets
  clôturés ou fusionnés que WorkDiary ne connaît pas encore ne sont pas
  rattrapés.
- Si un ticket déjà lié est clôturé ou fusionné dans Zammad, WorkDiary passe
  la tâche à **Terminé** – sans retour vers le ticket. Si vous rouvrez ensuite
  la tâche, elle reste ouverte. Un ticket rouvert dans Zammad ne modifie pas
  la tâche.
- WorkDiary ne reprend pas les modifications du titre ou du groupe du
  ticket.
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
d’erreur. Un champ vide désactive le retour. En dehors de l’état, de la note
et – avec l’imputation du temps – des temps, WorkDiary n’écrit rien dans
Zammad.

## Imputation du temps dans le ticket

Sous **Imputation du temps dans le ticket**, choisissez l’unité dans laquelle
votre Zammad saisit les temps : **Minutes** ou **Heures**, selon l’unité de
suivi du temps de Zammad. Lorsqu’une personne saisit dans WorkDiary un temps
sur une tâche liée, WorkDiary l’impute au ticket comme suivi du temps ; en
heures arrondi à deux décimales. Chaque temps est imputé au plus une fois. Le
transfert s’exécute en arrière-plan et est répété en cas d’erreur. WorkDiary
ne transfère pas les temps modifiés ou supprimés par la suite.
**Désactivé** coupe l’imputation.

## Cible des tickets

Dans la section **Cible des tickets**, **Actuellement** indique comment
arrivent les nouveaux tickets : comme **Tâches** (par défaut) ou comme
**Tickets de service** d’une file d’attente. Pour changer, choisissez la
cible sous **Nouveaux tickets comme**, pour les tickets de service aussi la
**File d'attente**, puis cliquez sur **Changer de cible** ; WorkDiary demande
d’abord confirmation.

- Les tickets de service nécessitent le module Helpdesk. Vous créez la file
  sous **Service desk** → **Files d'attente**. Zammad gère ensuite les
  tickets de cette file.
- Les tickets déjà importés restent où ils sont.
- S’il existe des conflits ouverts de maîtrise des données dans la Boîte de
  rapprochement, WorkDiary refuse le changement jusqu’à leur résolution.
- Chaque changement est journalisé.
- Les tickets de service ne reçoivent ni suggestion de client, ni retour de
  statut, ni imputation du temps ; cela ne vaut que pour les tâches.

## Limites

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
- « L'URL de l'instance pointe vers une adresse privée/interne. » : si Zammad
  se trouve sur votre propre réseau, activez **Autoriser les adresses
  privées/internes**. Si l’exploitant a bloqué cette autorisation, l’instance
  doit avoir une adresse joignable publiquement.
- **État défaillant** avec « API Zammad inaccessible ou jeton invalide. » :
  vérifiez l’adresse et le jeton. Une erreur de l’API Zammad avec
  RuntimeException indique souvent une adresse d’un réseau interne sans
  autorisation.
- « Veuillez choisir une file d'attente. » avec **Changer de cible** : la
  file manque pour les tickets de service.
- Des tickets arrivent dans le mauvais projet : vérifiez les ID de groupe sous
  **File → projet** et les suggestions de clients dans la boîte.
