---
title: "Conformité au temps de travail (ArbZG)"
topic: reports.arbzg-compliance
version: 1
keywords:
    - loi sur le temps de travail
    - infractions au temps de travail
    - durée maximale de travail
    - repos quotidien
    - pause obligatoire
    - temps de pause
    - jeunes travailleurs
    - travail de nuit
    - obligation d'enregistrement
    - droit du travail
    - contrôle des horaires
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

**Période de nuit et jeunes :** Vous définissez la période de nuit (par défaut
23 h–6 h, 22 h–5 h dans les boulangeries) dans les paramètres de conformité ;
la moyenne selon le § 3 ne compte plus les jours fériés comme jours ouvrables.
Si une date de naissance est enregistrée pour le collaborateur, l’évaluation
contrôle en plus les jours précédant ses 18 ans selon la loi allemande sur la
protection des jeunes travailleurs : au plus 8 h par jour et 40 h par semaine,
pauses (30 min à partir de 4,5 h, 60 min à partir de 6 h), 12 h de repos, pas
de travail entre 20 h et 6 h, au plus 5 jours de travail par semaine. Le
travail le week-end et le travail de nuit à partir de 16 ans apparaissent comme
remarques, car la loi prévoit des exceptions selon le secteur.

Le délai d'enregistrement MiLoG (sept jours) se mesure à partir de
l'enregistrement d'origine : pour les pointages, le moment du pointage, même si
un appareil hors ligne le transmet plus tard ; pour les imports, la colonne
« erfasst am » (enregistré le). Les imports sans cette indication ne sont pas
contrôlés pour ce délai.
