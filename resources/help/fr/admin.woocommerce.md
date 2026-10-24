---
title: "Import des commandes WooCommerce"
topic: admin.woocommerce
version: 1
keywords:
    - WooCommerce
    - WordPress
    - boutique
    - importer des commandes
    - boutique en ligne
    - clé client
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

La page **WooCommerce** lit les commandes de votre boutique WooCommerce et les
affiche dans WorkDiary : numéro, date, acheteur, statut et montant. Les acheteurs
sont rattachés à des clients existants, si bien que leurs commandes apparaissent
aussi sur la fiche client. WorkDiary n’écrit rien dans la boutique. Vous trouvez
la page dans le menu système (la roue dentée **Système**) sous **Plugins** →
**WooCommerce**, dès que le plugin est actif.

## Configurer l’accès

1. Dans WooCommerce, créez une clé avec le droit **Lecture** sous **Réglages** →
   **Avancé** → **API REST**.
2. Sur la page, ouvrez **Identifiants** dans le menu ⋯ et saisissez l’**adresse
   de la boutique** (en https, sans /wp-json), la **clé client** et le **secret
   client**. WooCommerce n’accepte les clés qu’en https. Si la boutique tourne dans
   votre propre réseau, activez aussi **Autoriser les adresses privées** – cela
   n’a d’effet que si l’exploitant l’autorise.
3. Cliquez sur **Vérifier la connexion**. WorkDiary lit une commande pour test et
   affiche le nom de la boutique.

Tant que des commandes sont reprises, aucune autre boutique ne peut être saisie.

## Aperçu et confirmation de l’import

**Vérifier l’aperçu** montre les commandes de la période de la première
synchronisation (30 jours par défaut, réglable sous **Identifiants**) et indique
si leur acheteur est déjà rattaché à un client. L’aperçu n’enregistre rien.
**Confirmer l’import** libère la synchronisation.

Les nouvelles commandes annulées, échouées ou remboursées ne sont reprises que si
**Reprendre les commandes annulées et remboursées** est activé. Les commandes
déjà reprises sont toujours mises à jour.

## Ce qui est repris

- Numéro de commande, date, **statut** issu du statut fixe de WooCommerce
  (ouverte, en cours, terminée, annulée, remboursée) et montant avec devise.
- Les remboursements figurent sous le montant (« dont remboursé ») ; le total
  de la commande reste celui de la boutique.
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

- La vérification signale `https_required` : l’adresse de la boutique ne commence
  pas par https.
- La vérification échoue avec 401 : clés erronées ou sans droit de lecture.
- La vérification signale une adresse interne : l’adresse pointe vers votre réseau,
  mais **Autoriser les adresses privées** est désactivé ou non autorisé par
  l’exploitant.
- La vérification échoue avec 401 alors que les clés sont correctes : derrière
  un proxy, WordPress ne reconnaît pas la connexion https, ou le serveur ne
  transmet pas l’en-tête Authorization.
- La vérification signale 404 : sous **Réglages** → **Permaliens**, « Simple »
  est choisi ; l’API REST a besoin de permaliens explicites.
