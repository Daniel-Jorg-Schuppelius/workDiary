---
title: "Rôles & droits"
topic: admin.roles
version: 2
audience:
    - admin
schema: process
related:
    - admin.handbook
    - admin.security
    - org.members
    - roles.admin
---

## Objectif et contexte

La gestion des droits détermine qui peut voir et faire quoi dans
WorkDiary. Elle se divise en quatre volets : **droits** (catalogue en
lecture seule de droits granulaires au schéma `ressource.action`,
p. ex. `month.approve`), **rôles** (paquets de droits, ajustables par
organisation), **groupes** (pur regroupement d'affichage, sans effet
fonctionnel) et **membres** (attribution des rôles).

## Prérequis

- Droits d'administration de l'organisation.
- Un compte de test sans droits admin pour vérifier réellement les
  découpages.
- Une vision claire des profils métiers (terrain, chef d'équipe,
  comptabilité…).

## Déroulement recommandé

1. **Créer ou copier un rôle** — partir d'un rôle existant évite des
   essais ratés.
2. **Tailler les droits :** plutôt un rôle étroit supplémentaire
   qu'un droit fourre-tout (principe du moindre privilège).
3. **Attribuer aux membres.**
4. **Vérifier avec le compte de test** avant de généraliser le rôle.

![Gestion des rôles avec rôles système et nombre de droits](media/administration/rollen.png)
*La gestion des rôles : les rôles système de l’organisation avec leur nombre de droits.*

## Exemple pratique

Pour une nouvelle employée de bureau, le rôle « back-office » est
copié de « chef d'équipe », amputé des droits de validation puis
attribué. Le test avec le compte de contrôle montre : validations
mensuelles invisibles, création de commandes opérationnelle —
exactement comme prévu.

## Erreurs fréquentes

- **Attribuer un rôle admin global :** un rôle sans rattachement à
  une organisation agit **sur toute la plateforme**, tous locataires
  confondus. Il appartient exclusivement à l'exploitant et ne doit
  jamais être attribué via des droits délégables ou l'interface de
  l'organisation — risque d'escalade.
- **Attendre un passe-droit admin :** les modules sensibles
  (protection des données, alerte professionnelle) exigent une
  attribution explicite — même pour les admins. C'est voulu.
- **Laisser proliférer les rôles fourre-tout :** commodes, mais
  presque impossibles à réduire ensuite.

## Effets et prochaines étapes

Les changements de rôle prennent effet immédiatement pour tous les
membres concernés — menus, contenus d'aide et accès aux modules
compris. Ensuite : tenir les attributions sous « Membres » et lire
les consignes de sécurité du manuel d'administration.
