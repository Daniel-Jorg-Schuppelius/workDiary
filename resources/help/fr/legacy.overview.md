---
title: "Ancien système (legacy)"
topic: legacy.overview
version: 4
keywords:
    - ancienne application
    - anciennes données
    - migration des données
    - reprise des données
    - service de garde
    - astreinte
    - connexion centre d'appels
    - archives anciennes
    - utilisateurs de l'ancien système
    - mode hérité
    - bureau central
    - portail support
    - clients du portail
    - commandes clients de l’ancien système
    - avis par e-mail
related:
    - auth.login
    - admin.tenants
---

L'espace legacy sert de passerelle vers l'ancien système : il continue de
fournir ses données et fonctions jusqu'à leur reprise complète dans
WorkDiary. L'accès est réservé aux utilisateurs disposant d'un identifiant
de l'ancien système et aux administrateurs. Il comprend le **journal** (vue
hebdomadaire, création/modification/suppression d'entrées), le **service
d'urgence et d'astreinte**, les **archives** (lecture seule, avec
déclenchement des cycles d'archivage), la **gestion des utilisateurs** de
l'ancien système, l'accès **centre d'appels** avec son propre login et le
**portail support** avec les commandes et accès des clients.
Les fonctions de lecture sont toujours disponibles ; les actions en
écriture et le changement de mot de passe ne sont actifs que si l'accès en
écriture au legacy est activé. Les administrateurs disposent en plus d'un
tableau de bord de migration pour reprendre les données.

## Bureau central et Employé

En **Mode hérité** – activable sous **Paramètres** dans l'en-tête si vous avez
accès aux deux espaces – la navigation principale affiche **Vue
hebdomadaire**, **Liste de travail** et **Bureau central**.

**Bureau central** donne la vue d'ensemble de l'ancien système :

- Vignettes **Problèmes**, **Ouvert**, **Confirmé** et **Terminé (7 j)** ainsi
  que **En retard**, **Échu aujourd’hui** et **7 prochains jours** ; un clic
  ouvre la liste de travail avec le filtre correspondant.
- Le **Planning hebdomadaire** avec **Service d’astreinte** et **Astreinte**, à
  partir de la veille ; on navigue avec **Semaine précédente**, **Semaine
  suivante** et **Semaine actuelle**.
- **Week-end et jours fériés**, **Nouvelles entrées (14 jours)**,
  **Principaux responsables (ouverts)**, **Prochains jours fériés (30 jours)**
  et **Notifications ouvertes**.

Chacun voit les plannings de service. Les données du journal de toutes les
personnes sont visibles pour les administrateurs de l'ancien système et pour le
rôle **Comptabilité** ; les autres ne voient que les leurs. La connexion du
centre d'appels mène à la même page.

**Employés** se trouve dans le menu d'administration (icône **Administration**
dans l'en-tête) sous **Personnel** et liste les utilisateurs de l'ancien
système avec **Nom** et **E-mail** :

- **Nouvel employé** crée une personne avec **Nom**, **E-mail** et **Mot de
  passe** ; en modification, le mot de passe reste inchangé si le champ reste
  vide.
- Les trois premiers comptes de l'ancien système (administrateurs)
  n'apparaissent pas et ne peuvent pas être modifiés ici.
- **Supprimer** n'est possible que tant qu'aucune entrée de journal, de service
  d'astreinte ou d'astreinte n'existe pour la personne.
- Créer, modifier et supprimer exigent l'accès en écriture à l'ancien système.

**Autorisation :** la page **Employés** est ouverte aux administrateurs de
l'ancien système et à l'exploitation de la plateforme ; le rôle
d'administrateur de l'organisation ne suffit pas à lui seul.

## Portail support

**Portail support** dans la navigation principale du mode legacy affiche les
commandes que les clients ont passées dans le portail client de l'ancien
système :

- Filtres par **Année** (l'année la plus récente avec des commandes est
  présélectionnée), **Client** et texte de recherche ; les tuiles **Nouveau**,
  **Consulté**, **Rendez-vous fixé**, **Rendez-vous dépassé**, **Terminé** et
  **Archivé** filtrent la liste.
- **Modifier** gère client, téléphone, site, description, **Rendez-vous**,
  **Technicien**, **Message pour le client**, **Note interne**, **Durée** et
  **Statut**. Un nouveau rendez-vous passe le statut à **Rendez-vous fixé**,
  sauf si terminé ou archivé est choisi. Le client voit le message, pas la note.
- **Créer une commande** saisit une commande pour un client, par exemple après
  un appel.
- **Avis par e-mail** active la notification des nouvelles commandes clients et
  définit le destinataire.
- **Archives** liste les commandes archivées ; **Archiver** y déplace les
  commandes au statut **Archivé** datées d'avant un jour de référence. Les
  clients ne les voient plus ensuite.

**Clients du portail** dans le menu de gestion gère les accès des clients avec
**Entreprise**, **Nom d'utilisateur** et **Mot de passe** (4 à 10 caractères).
Les clients ayant des commandes en cours ne peuvent pas être supprimés.

Les clients se connectent sur la page de connexion propre au portail client,
ne voient que leurs propres commandes avec rendez-vous et réponses, passent de
nouvelles commandes et changent leur mot de passe. Si l'accès en écriture à
l'ancien système est désactivé, le portail est en lecture seule.

**Autorisation :** le portail support et les clients du portail sont ouverts aux
administrateurs de l'ancien système et à l'exploitation de la plateforme.
