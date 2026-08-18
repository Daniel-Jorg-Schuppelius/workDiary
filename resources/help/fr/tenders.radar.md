---
title: "Radar des avis de marché"
topic: tenders.radar
version: 1
audience: []
related:
    - applications.overview
---

Le radar parcourt les **avis de marché publics fédéraux allemands** à la
recherche d'appels d'offres correspondant à votre entreprise. La source est le
service officiel des avis (oeffentlichevergabe.de), qui publie tous les avis
obligatoires en données ouvertes sous licence CC0 — sans inscription ni
identifiants de portail.

**Les profils de recherche** définissent ce qui est recherché. Deux
nomenclatures portent la recherche : **CPV** indique *ce qui* est acheté,
**NUTS** indique *où*. Les deux sont hiérarchiques, les préfixes suffisent donc
— `45` couvre tous les travaux de construction, `DEA` toute la
Rhénanie-du-Nord-Westphalie. Les mots-clés cherchent en plus dans le titre, la
description et le pouvoir adjudicateur ; **les mots d'exclusion pèsent plus
lourd** : une correspondance y écarte l'avis même si tout le reste convient.
Les avis sans valeur indiquée ne sont jamais écartés par les seuils de valeur —
sinon on perdrait tout ce qui ne chiffre pas son montant.

**La récupération est quotidienne et porte sur la veille.** Un jour de
publication n'est complet que le lendemain ; récupérer le jour même laisserait
des lacunes. Les avis rectifiés arrivent comme nouvelle version, l'ancienne est
conservée.

**La boîte de réception propose, elle ne décide pas.** Ce qui ne convient pas
est masqué et conservé comme preuve ; ce qui convient devient un dossier de
marché avec titre, pouvoir adjudicateur, CPV, région, délai et source
pré-remplis. **Vérifiez ensuite le type de procédure et le seuil** — la source
ouverte ne nomme la procédure que grossièrement, et il serait imprudent d'en
déduire le type de procédure allemand ou la situation au regard des seuils.
