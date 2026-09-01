---
title: "Supports d’accès"
topic: access.media
version: 1
audience: []
modules:
    - module.fuhrpark
related:
    - assets.fleet
---

**Transpondeurs, cartes et codes** comme parc géré — l’extension de la remise
physique des clés. Chaque support a à tout moment **exactement un statut**
(En stock / Remis / Perdu / Bloqué / Réformé) et une localisation attestée.

## Principes

- **Le numéro du support n’est stocké que haché** — les quatre derniers
  caractères restent visibles. Le texte en clair n’est connu qu’à la
  création.
- **Le détenteur est un utilisateur OU une personne externe** (nom +
  société) — une société de nettoyage n’a pas de compte collaborateur.
- **workDiary ne pilote aucune installation d’accès.** L’état administratif
  ici et l’état de l’installation là-bas sont tenus ensemble par la tâche de
  blocage.

## Perte et blocage

Une déclaration de perte pose le statut **Perdu** et crée obligatoirement une
**tâche de blocage** (« Bloquer le support …1234 dans l’installation X »,
échéance deux jours). Seul celui qui a effectué le blocage dans
l’installation le confirme — le support devient alors **Bloqué** et la tâche
faite. Perdu et bloqué sont volontairement des états séparés : c’est
précisément cet écart qui doit être visible, car le support y est un risque.

## Remise et retour

Chaque remise (remise/retour) atterrit dans l’**historique** du support —
avec détenteur, moment, retour attendu et état. Un support remis ne peut pas
être réformé — le reprendre d’abord.
