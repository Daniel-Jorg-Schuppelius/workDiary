---
title: "Dictionnaire"
topic: admin.text-corrections
version: 1
audience:
    - admin
---

Le **dictionnaire** corrige automatiquement les fautes d'orthographe
récurrentes — de façon déterministe et sans IA. Chaque entrée est une paire
« faux → correct ».

- **Effet** : lors de la construction des textes de position générés
  (transferts de facturation, brouillons de facture, aperçu de facture).
  Les saisies de temps enregistrées restent inchangées.
- **Correspondance** : mots ou expressions entiers uniquement, sans tenir
  compte de la casse ; l'orthographe de la correction est conservée
  (MAJUSCULES restent MAJUSCULES, les débuts de phrase prennent une
  majuscule).
- **Apprentissage** : lorsqu'un texte de position est corrigé manuellement,
  l'application détecte les remplacements de mots 1:1 et propose de les
  « mémoriser » — l'ajout n'a lieu qu'après confirmation, jamais en
  silence. Ces entrées apparaissent comme « Appris ».
- **Désactiver plutôt que supprimer** : une entrée désactivée n'a aucun
  effet mais reste traçable.

La gestion requiert le droit de configuration financière, car les entrées
modifient le contenu des factures.
