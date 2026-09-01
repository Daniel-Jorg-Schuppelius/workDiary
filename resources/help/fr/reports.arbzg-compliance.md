---
title: "Conformité au temps de travail (ArbZG)"
topic: reports.arbzg-compliance
version: 1
audience: []
modules:
    - module.auswertungen_team
related:
    - reports.overview
    - reports.drilldown
---

Ce rapport contrôle le **temps de travail réellement saisi** (pointages,
net après pauses) par collaborateur et par jour contre les seuils de la
loi allemande sur le temps de travail (ArbZG) — c'est la vue du réalisé,
indépendante de la conformité du planning. Sont vérifiés : la durée
maximale journalière (10 h par défaut), le temps de repos minimal entre
deux journées (11 h), la pause obligatoire (30 min dès 6 h, 45 min dès
9 h) et, en avertissement, la durée hebdomadaire maximale (48 h en
moyenne). Les seuils proviennent des paramètres de conformité de
l'organisation. Chaque ligne renvoie via **Vers la clôture journalière**
au jour concerné ; une correction de temps approuvée est signalée par
**corrigé**, et la liste s'exporte en CSV ou PDF.
