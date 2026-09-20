---
title: "Collections"
topic: knowledge.collections
version: 5
audience: []
related:
    - knowledge.articles
    - communication.notes
    - ideas.overview
    - documents.manage
    - learning.overview
---

Une **collection** organise des contenus au-delà des modules : notes, cartes
d'idées, articles de la base, documents, cours et parcours peuvent figurer
ensemble dans une collection. Le rattachement à un client, une intervention ou
un projet reste déterminant — la collection est l'ordre supplémentaire, choisi
librement, pour tout ce qui ne relève pas d'un seul dossier.

Déroulement type :

1. Créez une collection depuis l'entrée **Connaissances** via **Gérer les
   collections**, au besoin comme sous-collection
   d'une autre. Les collections s'imbriquent sur cinq niveaux au maximum et se
   déplacent par la suite.
2. Sur la page de détail d'un contenu, choisissez **Ajouter à une
   collection**. Un contenu peut figurer dans plusieurs collections ; aucune
   copie n'est créée.
3. **Archivez** les collections devenues inutiles au lieu de les supprimer —
   les rattachements sont conservés et peuvent être restaurés.

**Une collection ne donne aucun accès.** Chacun n'y voit que ce qu'il peut voir
par ailleurs : les notes confidentielles d'autres personnes, les cartes d'idées
non partagées, les documents confidentiels et les contenus de formation sans le
module de la plateforme restent masqués — leur nombre n'est pas affiché non
plus. Seule la personne qui l'a créée voit une collection **privée**.

## L'entrée « Connaissances »

La page **Connaissances** affiche notes, cartes d'idées, articles de
connaissances, documents, cours et parcours de formation dans une seule liste —
ou en vignettes. L'arbre des collections se trouve à gauche (une collection
inclut ses sous-collections) ; le titre et le type filtrent en haut, les badges
de mots-clés affinent encore. Les entrées existantes (notes, base de
connaissances, cartes d'idées, documents) sont désormais des onglets de cette
page, et les collections en sont le mode de gestion. Chaque onglet garde ses
colonnes et ses actions : les échéances et la publication restent aux
documents, la publication des articles reste à la base de connaissances.

Cochez plusieurs contenus et cliquez sur **Ajouter** pour les placer d'un coup
dans une collection — cela fonctionne aussi dans les résultats de la
**Recherche** pour les notes, articles de connaissances et cours.

## Convertir une note en article de connaissances

Dans la fenêtre de lecture d'une note, **Convertir en article de connaissances**
crée un brouillon dans la base de connaissances : l'objet devient le titre, le
texte la description du problème, et les mots-clés sont repris. L'article
affiche la note comme origine sous « Mentionné dans » ; un second clic ouvre
l'article existant au lieu d'en créer un nouveau. Les notes confidentielles ne
peuvent pas être converties.

## Import depuis Obsidian et OneNote

Les administrateurs importent des notes existantes **une fois ou à la demande**
— en lecture seule, sans réécriture ni synchronisation continue. L'entrée
**Connaissances** propose deux boutons :

- **Importer Obsidian** lit un coffre Obsidian via une connexion de dossier
  existante de l'entrée de documents cloud (Nextcloud, OneDrive, Dropbox, Google
  Drive). Indiquez le chemin du coffre relativement au dossier racine de la
  connexion. Les sous-dossiers deviennent des collections, les mots-clés de
  l'en-tête YAML et les `#mots-clés` du texte sont repris, les `[[wikiliens]]`
  deviennent des références. `.obsidian/` et `.trash/` sont exclus.
- **Importer OneNote** n'apparaît qu'une fois que l'organisation a activé
  **Autoriser l'import OneNote** dans les paramètres du plugin Microsoft 365 et
  utilisé **Connecter OneNote** dans le panneau Microsoft 365. La connexion
  demande l'autorisation supplémentaire en lecture seule Notes.Read. Le
  bloc-notes devient une collection, les groupes de sections et les sections des
  sous-collections, chaque page une note ou un article ; le contenu est importé
  sous forme de texte.

L'import se fait sous forme de notes ou de brouillons d'articles de
connaissances. Chaque contenu importé affiche son origine (« Importé depuis … »).
Une exécution suivante ignore ce qui existe déjà et n'importe que le nouveau —
au plus 300 nouveaux contenus par exécution.

## Références et rétroliens

Sur les pages de détail de ces contenus, la carte **Références** montre vers quoi
un contenu renvoie et où il est mentionné :

- **Ajouter une référence** relie le contenu à une note, une carte d'idées, un
  article de connaissances, un document, un cours ou un parcours de formation.
  Le champ de recherche vous permet de filtrer la liste.
- **Mentionné dans** liste, regroupé par type, tout ce qui pointe vers la page,
  y compris les liens de la base de connaissances et les cibles converties ou
  liées depuis des nœuds d'idées. Les clients, projets et interventions
  affichent cette liste dès qu'un contenu y renvoie.
- **Retirer la référence** ne supprime que les références ajoutées à la main ;
  les liens de la base de connaissances et des cartes d'idées se gèrent là-bas.

Comme pour les collections, une référence ne donne aucun accès : une source
n'apparaît que si vous pouvez l'ouvrir par ailleurs.

La création et le remplissage des collections ainsi que l'ajout de références
exigent le droit « Gérer les collections et les références » ; la consultation
des collections, « Voir les collections ».
