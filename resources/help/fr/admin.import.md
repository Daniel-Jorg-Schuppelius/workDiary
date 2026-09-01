---
title: "Import CSV"
topic: admin.import
version: 2
audience:
    - admin
schema: process
related:
    - admin.handbook
    - admin.tenants
    - contacts.manage
---

## Objectif et contexte

L'assistant d'import amène les données de base dans WorkDiary par
CSV — avec analyse **avant** écriture et rapport d'erreurs complet.
C'est la voie la plus rapide pour reprendre un existant (clients,
utilisateurs, projets, équipes, fournisseurs, matériaux) de façon
structurée, sans laisser la qualité des données au hasard.

## Prérequis

- Droits d'administration.
- Un fichier CSV par entité ; le mappage des colonnes se fait dans
  l'assistant.
- Pour les données dépendantes : le bon **ordre** (d'abord
  clients/équipes, puis projets, etc.).

## Déroulement recommandé

1. **Choisir l'entité** (clients, utilisateurs, projets, équipes,
   fournisseurs, matériaux…).
2. **Téléverser le CSV** — l'**analyse préalable** vérifie structure
   et contenu sans rien écrire.
3. **Contrôler l'aperçu :** lignes reconnues, avertissements,
   erreurs.
4. **Confirmer** — l'import tourne en tâche de fond.
5. **Télécharger le CSV d'erreurs :** toutes les lignes rejetées avec
   motif ; corriger et réimporter.

![Assistant d’import avec choix d’entité, modèle et analyse préalable](media/administration/import-assistent.png)
*L’assistant d’import : choisir l’entité, télécharger le modèle, déposer le fichier — l’analyse n’écrit rien.*

## Exemple pratique

Lors d'une migration, une entreprise importe d'abord un fichier test
de dix clients, vérifie aperçu et mappage, puis charge les 800 lignes
du stock complet. Douze lignes atterrissent motivées dans le rapport
d'erreurs, sont corrigées et reprises au second passage.

## Erreurs fréquentes

- **Charger le stock complet sans fichier test** — les erreurs de
  mappage se multiplient inutilement.
- **Ignorer l'ordre :** des projets avant leurs clients échouent sur
  des références manquantes.
- **Ignorer le rapport d'erreurs :** les lignes fautives n'arrêtent
  pas l'import — mais elles manquent au stock tant qu'elles ne sont
  pas réimportées.

## Effets et prochaines étapes

Rien n'est écrit avant la confirmation — analyse et aperçu sont sans
risque. L'historique montre tous les passages avec leur statut,
filtrable par entité et état. Ensuite : contrôler les données par
sondage et fusionner les doublons.
