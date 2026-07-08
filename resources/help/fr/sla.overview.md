---
title: "SLA, contrats & niveaux de service"
topic: sla.overview
version: 1
audience: []
related:
    - glossary.core
---

Les contrats SLA définissent, par client ou par défaut, les délais de
réaction et de résolution convenus selon la priorité ; WorkDiary en
déduit le statut SLA de chaque ticket de service et documente les
dépassements de façon vérifiable. Chaque ticket affiche un badge :
**SLA dans les temps**, **SLA menacé** (moins de 20 % du délai restant)
ou **SLA dépassé**. Les dépassements sont enregistrés une seule fois
par ticket et par type dans un registre des violations, détectés par le
scan nocturne et lors des changements de statut, et peuvent être
acquittés avec indication de la cause. Le scanner d'échéances notifie
le responsable du ticket et, en escalade, la direction d'équipe. Le
rapport SLA (Analyses → SLA) montre le taux de conformité et les
violations par type, priorité et client, exportable en CSV et PDF.
