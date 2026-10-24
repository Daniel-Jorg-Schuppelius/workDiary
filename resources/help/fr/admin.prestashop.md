---
title: "Import des commandes PrestaShop"
topic: admin.prestashop
version: 1
keywords:
    - PrestaShop
    - boutique
    - importer des commandes
    - boutique en ligne
    - webservice
    - rattacher les acheteurs
    - cas à clarifier
audience:
    - admin
related:
    - admin.plugins
    - admin.integration-inbox
    - admin.billbee
    - privacy.overview
    - admin.scheduler
---

La page **PrestaShop** lit les commandes de votre boutique PrestaShop et les
affiche dans WorkDiary : numéro, date, acheteur, statut et montant. Les acheteurs
sont rattachés à des clients existants, si bien que leurs commandes apparaissent
aussi sur la fiche client. WorkDiary n’écrit rien dans la boutique. Vous trouvez
la page dans le menu système (la roue dentée **Système**) sous **Plugins** →
**PrestaShop**, dès que le plugin est actif.

## Configurer l’accès

1. Dans PrestaShop, activez le webservice sous **Paramètres avancés** →
   **Webservice** et créez une clé. Elle a besoin du droit de lecture (GET) sur
   orders, customers, currencies et order_states, pour les remboursements aussi
   sur order_slip et pour le fuseau horaire sur configurations.
2. Sur la page, ouvrez **Identifiants** dans le menu ⋯ et saisissez l’**adresse
   de la boutique** (sans /api) et la **clé du webservice**. PrestaShop fournit
   les heures sans fuseau horaire : si la boutique ne fonctionne pas dans le
   fuseau de votre organisation, saisissez le **Fuseau horaire de la boutique**
   (p. ex. Europe/Vienna). Si la boutique tourne dans votre propre réseau,
   activez aussi **Autoriser les adresses privées** – cela n’a d’effet que si
   l’exploitant l’autorise.
3. Cliquez sur **Vérifier la connexion**. WorkDiary lit une commande pour test et
   affiche l’adresse.

Tant que des commandes sont reprises, aucune autre boutique ne peut être saisie.

## Aperçu et confirmation de l’import

**Vérifier l’aperçu** montre les commandes de la période de la première
synchronisation (30 jours par défaut, réglable sous **Identifiants**) et indique
si leur acheteur est déjà rattaché à un client. L’aperçu n’enregistre rien.
**Confirmer l’import** libère la synchronisation.

Les nouvelles commandes annulées ou remboursées ne sont reprises que si
**Reprendre les commandes annulées et remboursées** est activé. Les commandes
déjà reprises sont toujours mises à jour.

## Ce qui est repris

- Référence, date, **statut** et montant avec devise. Le statut suit les
  propriétés fixes de l’état de commande PrestaShop, pas son nom : annulée et
  erreur de paiement → annulée, remboursement → remboursée, expédiée (y compris livrée) → terminée, payée → en cours, sinon ouverte.
- Le bon de livraison seul (statut standard « Préparation en cours ») ne compte
  pas comme expédié. Avec plusieurs boutiques, WorkDiary lit les commandes de
  toutes les boutiques et relit une à une les devises des autres boutiques.
- Les remboursements proviennent des avoirs (order_slip) et figurent sous le
  montant. Si la clé ne peut pas lire order_slip, le montant remboursé reste
  vide.
- Si la clé peut lire configurations, **Vérifier la connexion** reprend le
  fuseau horaire de la boutique ; un **Fuseau horaire de la boutique** saisi est
  prioritaire.
- Une fois par jour, la synchronisation vérifie si les commandes reprises
  existent encore dans la boutique. Les commandes supprimées sont alors marquées
  **Supprimée dans la boutique** et restent dans WorkDiary ; si l’une réapparaît,
  la marque disparaît.
- Acheteur avec nom, e-mail et société, les lignes en texte.
- Il n’y a pas de lien vers l’administration de la boutique : son dossier est
  secret pour chaque boutique et ses liens ne valent qu’avec une connexion.

## Rattacher les clients et cas à clarifier

Si l’acheteur est déjà rattaché à un client ou clairement identifiable,
WorkDiary lie la commande à ce client. Sinon, un **cas à clarifier** est créé dans
la boîte de réception d’intégration : rattachez-y l’acheteur à un client, et la
synchronisation suivante lie aussi ses autres commandes. L’import ne crée jamais
de nouveaux clients. Les commandes d’invités sont reconnues par l’adresse e-mail.

## Quand la synchronisation a lieu

Toutes les 15 minutes, WorkDiary récupère les commandes nouvelles ou modifiées
depuis le dernier passage, et une fois par jour toute la période. Modifiez la
cadence sous **Jobs planifiés** ; **Synchroniser maintenant** lance tout de suite.
Un passage interrompu est marqué **incomplet** ; le suivant reprend au dernier
état complet.

## Déconnexion et conservation

**Déconnecter** arrête la synchronisation ; les commandes et rattachements clients
restent. La règle de conservation **Données acheteurs des commandes de places de
marché** supprime le nom, l’e-mail et la société des acheteurs après le délai de
conservation commercial ; les montants et le lien client restent.

## Erreurs fréquentes

- La vérification échoue avec 401 : la clé est erronée ou le webservice est
  désactivé.
- La vérification échoue avec 403 ou un passage reste **incomplet** : la clé n’a
  pas le droit de lecture sur l’une des quatre ressources.
- Les heures sont décalées de plusieurs heures : le **fuseau horaire de la
  boutique** manque ou est erroné.
- La vérification échoue avec 401 alors que la clé est correcte : le serveur web
  ne transmet pas l’en-tête Authorization (Apache : CGIPassAuth), ou la
  réécriture d’URL pour /api est désactivée.
