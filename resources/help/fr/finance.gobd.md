---
title: "Export GoBD (Datenträgerüberlassung)"
topic: finance.gobd
version: 1
audience:
    - admin
related:
    - invoices.manage
    - audit.log
---

Pour les contrôles fiscaux, WorkDiary génère la remise de support de
données selon le mode d'accès Z3 : un paquet de contrôle conforme au
standard de description GDPdU, que le contrôleur peut charger directement
dans son logiciel d'analyse.

**Contenu du paquet :** Le paquet est une archive ZIP contenant un
fichier index.xml qui décrit les tables, champs et formats de manière
lisible par machine, ainsi que des fichiers de données CSV séparés par
des points-virgules. Les domaines de données sont sélectionnables
individuellement : factures sortantes, lignes de facture, données de base
des débiteurs et justificatifs de temps de la période contrôlée.

**Période & pré-vérification :** Par défaut, l'année précédente est
prérenseignée comme période de contrôle ; les dates de début et de fin
sont librement modifiables. Avant l'export, une pré-vérification affiche
le nombre d'enregistrements par domaine et signale les anomalies — par
exemple lorsque des factures en brouillon subsistent dans la période ou
qu'aucune facture n'est trouvée.

**Jeu de caractères :** Les fichiers CSV sont générés au choix en CP1252
(« ANSI », le standard et la voie la plus sûre côté contrôleur), en
ISO-8859-15 ou en UTF-8 ; le fichier de description mentionne le jeu de
caractères choisi.

**Hachage reproductible :** Toutes les données sont triées et formatées
de manière déterministe. Le hachage du paquet est calculé sur le contenu
des fichiers (et non sur le binaire ZIP, qui contient des horodatages) —
la même période avec les mêmes domaines et le même jeu de caractères
produit donc de manière reproductible le même hachage. De plus, un
hachage propre est documenté pour chaque fichier. Il est ainsi possible
de prouver ultérieurement, sans équivoque, qu'un paquet remis est resté
inchangé.

**Registre des exports :** Chaque export crée automatiquement un
justificatif infalsifiable : qui a exporté quand, quelle période et quels
domaines, y compris les hachages du paquet et des fichiers ainsi que le
nombre d'enregistrements. Les derniers exports sont visibles directement
sur la page ; l'historique complet est conservé durablement et complète
le journal d'audit.

L'export lit exclusivement des données existantes — il ne modifie ni
pièces ni données de base et peut être répété autant de fois que
nécessaire.
