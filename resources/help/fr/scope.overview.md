---
title: "Périmètre fonctionnel"
topic: scope.overview
version: 1
keywords:
    - activer des modules
    - désactiver des modules
    - masquer des fonctions
    - alléger le menu
    - sélection des modules
    - préréglage
    - page d'accueil par rôle
    - page de démarrage
    - désactiver des fonctionnalités
audience:
    - admin
related:
    - admin.handbook
    - navigation.customize
---

La page **Périmètre fonctionnel** définit quels modules votre organisation
utilise visiblement. C'est un raccourci pour la configuration des modules :
seul le statut du module change — **aucune donnée n'est supprimée**, et tout
revient à la réactivation.

## Préréglages

Un préréglage (p. ex. « Démarrage allégé » ou « Service & artisanat »)
bascule la liste des modules en une seule étape. Vous pouvez ensuite ajuster
les modules individuellement.

## Recommandation du profil métier

Si votre organisation a installé un profil métier, la page affiche sa
recommandation de modules. Elle n'est jamais appliquée automatiquement —
vous la confirmez délibérément.

## Page d'accueil par rôle

Sous les modules, vous définissez où un rôle arrive après la connexion – par exemple
la pointeuse pour le personnel itinérant ou le flux de pièces pour la
comptabilité. Le choix personnel dans le profil prime toujours. Si une personne
a plusieurs rôles, le premier dans l'ordre affiché pour lequel une page est
définie s'applique. Une page que la personne ne peut pas ouvrir est ignorée ;
sans réglage, la page par défaut reste.

## Limites

- Les modules non licenciés ne peuvent pas être activés ici ; cela relève de
  la gestion des licences.
- Masquer ne change aucun droit. Les pages verrouillées répondent par un
  avis (HTTP 423) au lieu de perdre des données.

## Mode de périmètre et démarrage compact

Depuis la fonctionnalité 165, la page affiche aussi le **mode de
périmètre** : « Volontairement limité » garde désactivés les modules ajoutés
plus tard à la licence jusqu'à ce que vous les configuriez sous *Étendre le
périmètre fonctionnel* ; « Périmètre licencié complet » active immédiatement
toute extension de licence. La carte **Démarrage compact** mène à la
configuration avec quelques objectifs (clients et commandes, temps,
planification d'équipe, devis et factures) et, après application, à
l'annulation qui rétablit l'état antérieur. Les modifications manuelles
effectuées depuis sont signalées et écrasées uniquement sur demande.
