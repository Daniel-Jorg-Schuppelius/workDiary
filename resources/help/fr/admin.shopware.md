---
title: "Import des commandes Shopware"
topic: admin.shopware
version: 1
keywords:
    - Shopware
    - Shopware 6
    - boutique
    - importer des commandes
    - boutique en ligne
    - intégration
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

La page **Shopware** lit les commandes de votre boutique Shopware 6 et les
affiche dans WorkDiary : numéro, date, acheteur, statut et montant. Les acheteurs
sont rattachés à des clients existants, si bien que leurs commandes apparaissent
aussi sur la fiche client. WorkDiary n’écrit rien dans la boutique. Vous trouvez
la page dans le menu système (la roue dentée **Système**) sous **Plugins** →
**Shopware**, dès que le plugin est actif.

## Configurer l’accès

1. Dans Shopware, créez une intégration sous **Paramètres** → **Système** →
   **Intégrations**. Elle a besoin du droit
   de lecture sur les commandes, les clients de commande, les clients, les
   lignes, les transactions de paiement avec leurs captures et remboursements, les devises et les états – ou de
   l’option **Administrateur**.
2. Sur la page, ouvrez **Identifiants** dans le menu ⋯ et saisissez l’**adresse
   de la boutique** (sans /api), l’**ID de clé d’accès** et la **clé d’accès
   secrète** de l’intégration. Si la boutique tourne dans votre propre réseau,
   activez aussi **Autoriser les adresses privées** – cela n’a d’effet que si
   l’exploitant l’autorise.
3. Cliquez sur **Vérifier la connexion**. WorkDiary se connecte, lit une commande
   pour test et affiche l’adresse et la version de Shopware.

Tant que des commandes sont reprises, aucune autre boutique ne peut être saisie.

## Aperçu et confirmation de l’import

**Vérifier l’aperçu** montre les commandes de la période de la première
synchronisation (30 jours par défaut, réglable sous **Identifiants**) et indique
si leur acheteur est déjà rattaché à un client. L’aperçu n’enregistre rien.
**Confirmer l’import** libère la synchronisation.

Les nouvelles commandes annulées ne sont reprises que si
**Reprendre les commandes annulées et remboursées** est activé. Les commandes
déjà reprises sont toujours mises à jour.

## Ce qui est repris

- Numéro de commande, date, **statut** issu de l’état technique de la commande
  dans Shopware (ouverte, en cours, terminée, annulée) et montant avec devise.
  Shopware gère les remboursements dans l’état du paiement : si le
  dernier paiement est remboursé, la commande compte comme **remboursée** ; un
  remboursement partiel ne change pas le statut.
- WorkDiary affiche le montant remboursé sous le montant, dans la mesure où
  Shopware enregistre des remboursements terminés. Un « remboursé » posé à la main
  sans remboursement compte comme remboursement total ; un « partiellement
  remboursé » sans remboursement reste sans montant.
- Une fois par jour, la synchronisation vérifie si les commandes reprises
  existent encore dans la boutique. Les commandes supprimées sont alors marquées
  **Supprimée dans la boutique** et restent dans WorkDiary ; si l’une réapparaît,
  la marque disparaît.
- Acheteur avec nom, e-mail et société, les lignes en texte.
- Le numéro de commande mène à la gestion des commandes de la boutique.

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

- La vérification échoue avec 401 : l’ID de clé d’accès ou la clé secrète est
  erronée.
- La vérification échoue avec 403 : l’intégration n’a pas le droit de lecture sur
  les commandes.
- La vérification signale une adresse interne : l’adresse pointe vers votre réseau,
  mais **Autoriser les adresses privées** est désactivé ou non autorisé par
  l’exploitant.
- La vérification ou une exécution échoue avec 403 alors que les commandes sont
  lisibles : il manque à l’intégration l’un des droits de lecture cités plus
  haut, par exemple sur les transactions de paiement.
