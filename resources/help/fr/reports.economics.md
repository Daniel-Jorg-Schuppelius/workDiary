---
title: "Rentabilité"
topic: reports.economics
version: 1
audience: []
modules:
    - module.auswertungen_team
related:
    - reports.customer-analysis
    - reports.drilldown
---

La vue rentabilité (post-calcul) montre par client et par projet la
marge sur coûts variables : **produit** (temps facturables × taux +
matériel facturé + frais facturables, en projection — la facture
officielle reste dans le système de facturation externe), **coûts**
(taux de coût interne × temps + matériel et dépenses directes) et
**marge contributive** en valeur et en pourcentage. S'y ajoutent un
**classement** Top/Flop 5 par projet et client, le **temps non
facturable** comme proxy des reprises et gestes commerciaux, et la
comparaison **prévu/réel** contre les budgets temps et euros du projet.
Attention à la qualité des données : les temps sans taux de coût interne
entrent avec 0 € (marqués `*`, marge trop optimiste) et les projets sans
budget affichent « – ». Export CSV ou PDF ; données financières à
l'échelle de l'organisation, réservées aux titulaires du droit de
lecture des rapports.
