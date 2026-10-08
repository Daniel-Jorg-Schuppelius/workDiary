---
title: "Plugins"
topic: admin.plugins
version: 2
keywords:
    - extensions
    - modules complémentaires
    - intégrations
    - add-ons
    - activer un plugin
    - désactiver un plugin
    - test de connexion
    - contrôle de santé
    - erreurs de plugin
    - désactivation automatique
    - journal des erreurs
audience:
    - admin
related:
    - admin.handbook
    - admin.toggl
    - admin.openproject
    - admin.lexoffice
    - admin.remote-support
---

Vous gérez ici les plugins et intégrations installés. Les plugins
complètent WorkDiary par des connexions externes (par ex. Toggl,
OpenProject, Lexoffice, télémaintenance).

Important : les plugins sont pilotés **par organisation**.
L'activation, les paramètres, l'état de santé et les erreurs
s'appliquent chaque fois à votre organisation – un plugin peut avoir un
état tout à fait différent dans une autre organisation.

Vue d'ensemble (liste) :

- **Statut** : actif, inactif ou désactivé automatiquement.
- **Santé (health)** : ok / limité / défaillant, avec l'heure du
  dernier contrôle.
- **Actions par plugin** : configurer, activer/désactiver, exécuter
  immédiatement le contrôle de santé, en cas de désactivation
  automatique réinitialiser et réactiver.

Configurer (modifier) :

- Paramètres propres à chaque plugin (par ex. jeton API, points de
  terminaison). Mots de passe/jetons : un champ vide laisse la valeur
  existante inchangée.
- **Tester la connexion** déclenche un contrôle de santé sans
  enregistrer.

Contrôle de santé et désactivation automatique :

- Le contrôle de santé vérifie l'accessibilité et le fonctionnement et
  met à jour le résultat pour chaque organisation. Il s'exécute
  manuellement ou de façon planifiée (cron).
- Si des erreurs se répètent, le plugin est **désactivé
  automatiquement** une fois le seuil atteint – uniquement pour
  l'organisation concernée. Le fonctionnement reste ainsi intact pour
  les autres.
- Après avoir éliminé la cause, vous réinitialisez le compteur
  d'erreurs et réactivez le plugin.

Journal des erreurs (erreurs de plugin) :

- Liste de toutes les erreurs enregistrées avec l'heure, le plugin, la
  phase (démarrage/exécution/contrôle de santé), la classe d'exception
  et le message.
- Filtres par plugin, phase et statut (ouvert/confirmé).
- Dans la vue détaillée : message complet, contexte et trace de pile
  (stacktrace).
- Les erreurs peuvent être marquées comme **confirmé** (avec la
  personne en charge et l'horodatage) ; elles sont conservées pour la
  traçabilité.

Autorisation : ces sections sont réservées aux administrateurs et
nécessitent un contexte d'organisation.

Risques : un plugin désactivé interrompt sa synchronisation – les
imports/exports et les contrôles de santé sont suspendus jusqu'à sa
réactivation. Vérifiez l'état de santé après chaque modification de
la configuration.
