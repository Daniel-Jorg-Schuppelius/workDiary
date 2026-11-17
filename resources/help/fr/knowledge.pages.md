---
title: "Pages de connaissances"
topic: knowledge.pages
version: 1
keywords:
    - base de connaissances
    - wiki
    - manuel
    - instruction de travail
    - lecture obligatoire
    - confirmation de lecture
    - intégration
    - glossaire
    - lacune de recherche
    - manuel d'urgence
    - guide illustré
    - caviarder
    - OneNote
    - version
audience: []
modules:
    - module.knowledge
related:
    - knowledge.collections
    - knowledge.articles
    - work-queue.overview
---

Les **pages de connaissances** consignent la façon dont votre organisation
travaille : instructions de travail, guides, décisions et manuel d'urgence.
Vous les trouvez sous **Activité quotidienne** → **Connaissances & docs** →
**Connaissances**, onglet **Pages de connaissances**.

## Organisées comme un bloc-notes

- Les **domaines** sont les blocs-notes (par exemple « Manuel » ou
  « Informatique »). Un domaine peut être **restreint** : seuls les rôles,
  équipes et personnes sélectionnés peuvent alors le lire.
- Les **rubriques** structurent un domaine, les **pages** s'y trouvent, les
  **sous-pages** en dessous (jusqu'à trois niveaux).
- Vous rédigez le texte en Markdown – avec tableaux, images des pièces jointes
  (adresse d'image `anhang:fichier.png`), **encadrés** (`:::note`, `:::tip`,
  `:::warning`) et **sections dépliables** (`:::details Titre`), chacun
  jusqu'à une ligne `:::`. Le HTML saisi s'affiche comme texte.

## Modification, versions et validation

1. **Modifier** ouvre le texte ; chaque modification enregistrée est une
   nouvelle **version**. Si quelqu'un a enregistré la page entre-temps,
   l'enregistrement signale un conflit – rien n'est écrasé en silence.
2. La **personne responsable** **valide** la version. D'ici là, les lecteurs
   continuent de voir la dernière version validée.
3. Sous **Versions**, vous comparez deux versions ligne par ligne et
   rétablissez une ancienne comme nouvelle version.

## Rester à jour

Sous **Responsabilité et classement**, vous définissez la personne
responsable, un **intervalle de contrôle** et une **date de validité**.
**Toujours à jour** confirme la page et fixe la prochaine date de contrôle.
Les contrôles dus, la fin de validité et les validations en attente figurent
dans la **file de travail** ; après la date de validité, la page est
considérée comme **obsolète**.

## Lecture obligatoire

Sous **Lecture obligatoire**, vous attribuez une page à tous les membres, à un
rôle, à une équipe ou à une personne – si vous le souhaitez dans le cadre de
la **liste d'intégration**. Les personnes concernées confirment avec **Lu et
compris** ; une nouvelle version validée exige une nouvelle confirmation. La
vue d'ensemble montre qui a confirmé quelle version. Votre propre lecture se
trouve sous **Ma lecture** et dans la file de travail.

## Trouver

La recherche porte sur le titre et le texte – et sur le **glossaire** : une
abréviation ou un synonyme trouve aussi le terme. Les termes sans résultat
parviennent à l'administration comme **lacunes de recherche**, comptés sans
votre nom. Les pages apparaissent aussi dans la recherche globale. La mention
**modifiée** signale les pages modifiées depuis votre dernière visite.

## Lier

Avec **Lier**, vous rattachez une page à un client, projet, équipement, ordre,
ticket ou procédure. Pour le client, le projet et l'équipement, elle apparaît
ensuite dans le cockpit ; pour l'ordre, le ticket et la procédure, dans la
carte **Pages de connaissances**. Comme les autres contenus, les pages peuvent
être rangées dans des **collections**.

## Guides illustrés

Un **guide illustré** se compose d'étapes avec capture d'écran et texte. Dans
une étape, vous placez des marquages **flèche**, **numéro**, **cadre** et
**texte**. **Caviarder** inscrit les zones choisies dans l'image ; l'original
non caviardé est supprimé et ne peut plus être récupéré, pas même dans les
anciennes versions. **Lire** affiche le guide étape par étape.

## Manuel d'urgence et PDF

Les pages marquées pour le **manuel d'urgence** le constituent : **Manuel
d'urgence (PDF)** produit toutes les pages d'urgence validées avec version,
validation et responsable – afin qu'il soit disponible même en cas de panne
de l'application. Chaque page peut être exportée **en PDF** séparément.

## Corbeille et import

Les pages supprimées restent 30 jours dans la **corbeille** et peuvent être
restaurées ; ensuite, elles sont supprimées définitivement. Seules les
personnes autorisées peuvent supprimer définitivement. **Importer du
Markdown** reprend des fichiers comme brouillons ; les dossiers Obsidian et
les blocs-notes OneNote se reprennent via l'import de l'accès
**Connaissances** avec la cible **Page de connaissances**.
