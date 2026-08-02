---
title: "Valeur client"
topic: reports.customer-value
version: 1
audience: []
related:
    - reports.customer-analysis
    - reports.customer-retention
---

Le rapport de valeur client répond à la question : **de quels clients
vit l'entreprise, où se situe le risque de concentration, quels
clients A sont menacés ?**

- **Segments RFM** : chaque client reçoit des scores par quintile 1–5
  pour la **R**écence (jours depuis la dernière prestation), la
  **F**réquence (jours d'activité sur la période) et le **M**ontant
  (chiffre d'affaires). Il en résulte les segments *Champions*,
  *Fidèles*, *À développer*, *Nouveaux*, *Menacés* et *Inactifs*.
- **Concentration** : part des top 5/top 10 dans le chiffre d'affaires
  et indice de Herfindahl-Hirschman (HHI). En dessous de 1500 :
  non critique ; au-dessus de 2500 : concentration élevée.
- **Clients A menacés** : chiffre d'affaires élevé (M ≥ 4) mais aucune
  prestation depuis le seuil configuré — avec l'évolution du chiffre
  d'affaires sur 12 mois.

Le **chiffre d'affaires** provient des instantanés de temps
facturables (même source que le rapport de rentabilité) ; les montants
facturés ne sont qu'une valeur secondaire.
