---
title: "Cellule de signalement – traitement des cas"
topic: whistleblowing.cases
version: 1
audience: []
modules:
    - module.compliance
related:
    - whistleblowing.portal
    - whistleblowing.report
    - admin.security
    - privacy.overview
---

Vous traitez ici les signalements reçus (`/compliance/meldungen`). Le
droit de la cellule de signalement est volontairement **séparé** de
l'administration : sans affectation au cas, même un admin global n'a
aucun accès, chaque consultation étant contrôlée par la politique du
cas — sans contournement admin — et une double authentification propre
à la cellule est exigée. La liste n'affiche que des métadonnées (numéro,
catégorie, statut, priorité, délais), jamais d'aperçu du contenu ; les
contenus sont chiffrés par cas avec une clé dédiée. Dans le détail vous
pouvez **accuser réception** (délai de 7 jours), faire évoluer le
**statut** jusqu'à la clôture (avec justification), **affecter des
gestionnaires**, saisir des **notes internes**, **écrire à la personne
signalante** via la boîte anonyme et télécharger les pièces jointes
chiffrées. Un **conflit d'intérêts déclaré** ou le marquage d'une
**personne concernée** verrouille l'accès au cas ; une **libération
d'urgence** justifiée accorde l'accès à une personne supplémentaire,
chaque étape étant consignée dans la chaîne de hachage d'événements. La
suppression contrôlée en fin de conservation se fait par
crypto-shredding et est irréversible.
