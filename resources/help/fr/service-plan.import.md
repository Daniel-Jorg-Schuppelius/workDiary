---
title: "Importer des listes et comparer la liste des clients"
topic: service-plan.import
version: 1
keywords:
    - importer une liste
    - Excel
    - CSV
    - liste annuelle
    - liste par périodes
    - comparaison des clients
    - DATEV
    - liste des mandants
audience: []
related:
    - service-plan.overview
    - customers.deadlines
---

## Importer une liste

Importez des listes Excel ou CSV existantes via **Importer une liste** dans
le plan des prestations. Un **aperçu** vérifie d’abord chaque ligne, puis vous
importez.

- **Liste annuelle :** numéro de client, prestation, état, remarque ; en
  option documents reçus, obligatoire et responsable.
- **Liste par périodes :** numéro de client, prestation et une colonne par
  période (janv. … déc., T1 … T4 ou S1, S2) avec l’état.
- S’il n’y a pas de colonne « prestation », choisissez la prestation pour
  toute la liste.
- Les colonnes portant le nom d’une caractéristique des échéances clients
  (p. ex. périodicité TVA) reprennent les caractéristiques comme nouvelle
  version.

L’affectation **ne devine jamais** : les clients uniquement par le numéro de
client exact, les prestations seulement si un seul modèle ou type d’entrée
correspond, les responsables uniquement par le nom ou l’e-mail d’un membre.
Une ligne avec un numéro de client inconnu ne crée rien et figure dans
l’aperçu.

L’import reprend le périmètre manquant (l’existant reste), les
caractéristiques, la remarque et « documents reçus » de la période en cours
(seulement là où rien n’est encore saisi) et « sans objet ». Sur demande,
l’état de la période en cours devient une commande là où il n’y en a pas
encore.

## Comparer la liste des clients

**Comparer la liste des clients** compare votre base clients avec une liste
d’un autre logiciel, par exemple l’export des mandants DATEV (numéro de
mandant, nom). L’aperçu présente des propositions :

- **Nouveau :** numéro inconnu — créer le client.
- **Renommé :** numéro connu, nom différent — renommer le client.
- **Absent de la liste :** pour information uniquement.

Rien n’est écrasé ni créé avant que vous cochiez une proposition.
