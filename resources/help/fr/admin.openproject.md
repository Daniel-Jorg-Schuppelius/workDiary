---
title: "Intégration OpenProject"
topic: admin.openproject
version: 3
keywords:
    - gestion de projet
    - lots de travaux
    - work packages
    - saisies de temps
    - importer les temps
    - renvoyer les temps
    - synchronisation des temps
    - synchronisation des projets
    - correspondances
audience:
    - admin
related:
    - admin.plugins
    - admin.toggl
    - admin.import
    - admin.integration-inbox
---

L'intégration OpenProject relie WorkDiary à OpenProject de manière
**bidirectionnelle** : les temps sont importés **et** les temps saisis
peuvent être reportés vers OpenProject. Vous enregistrez les accès et
les options dans les paramètres du plugin (notamment **URL de
l'instance**, **Jeton API** et **Fenêtre de synchronisation (jours)**).

Synchroniser (page **Synchroniser OpenProject**) :

- **Synchroniser structure + temps** avec **Synchroniser maintenant** :
  rapproche les projets et les work packages, puis importe les entrées
  de temps de la fenêtre configurée.
- **Synchroniser la structure uniquement** avec **Comparer la
  structure** : associe les projets, work packages et utilisateurs
  d'OpenProject aux projets, tâches et utilisateurs de WorkDiary. Si
  **Créer les projets/tâches manquants** est activé, la comparaison
  crée automatiquement les éléments manquants.

Entrées de temps non affectées :

- Ce qui ne peut pas être affecté automatiquement aboutit dans la
  **Boîte de rapprochement** centrale ; **Vers la boîte de
  rapprochement** y mène et affiche le nombre d'entrées ouvertes.
- Vous y affectez un groupe à un client et à un projet (ou en nommez un
  nouveau) et le comptabilisez, ou vous l'ignorez. Les importations
  futures s'affectent automatiquement à partir des associations
  enregistrées.

Report (**Reporter les temps**) :

- Reporte vers OpenProject les temps non exportés des projets associés
  à un projet OpenProject ; les tâches sont comptabilisées comme work
  package lorsqu'elles sont associées. **Période (facultatif)** limite
  l'exécution (vide = toutes les entrées ouvertes), **Reporter
  maintenant** la lance et **Dernier report** en montre le
  résultat. Les entrées déjà reportées sont ignorées.
- Condition préalable : l'**ID d'activité OpenProject (report)** doit
  être renseigné dans les paramètres du plugin – sinon aucun report
  n'est possible.

Associations (**Gérer les affectations**) :

- La page **OpenProject – associations** liste les liens enregistrés
  pour les projets, work packages et utilisateurs. **Réaffecter**
  modifie la cible, **Supprimer** efface une association.

Risques : le report modifie des données dans le système OpenProject
connecté. Avant la première exécution, vérifiez les associations et
l'ID d'activité afin d'éviter des imputations erronées.
