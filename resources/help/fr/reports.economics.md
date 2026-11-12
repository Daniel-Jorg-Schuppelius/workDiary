---
title: "Rentabilité"
topic: reports.economics
version: 3
keywords:
    - calcul a posteriori
    - marge sur coûts variables
    - marge
    - rentabilité des projets
    - comparaison prévu réalisé
    - comparaison budgétaire
    - taux de coût interne
    - projets déficitaires
    - contrôle de gestion
    - top et flop
audience: []
modules:
    - module.auswertungen_team
related:
    - reports.customer-analysis
    - reports.drilldown
---

La page **Rentabilité** (post-calcul) sous **Rapports** → **Finances et
audit** → **Rentabilité** affiche la marge par client (**Rentabilité par
client**) et par projet (**Rentabilité & prévu-vs-réel par projet**) sur
la **Période** choisie :

- **Revenu** = temps facturables × taux + matériel facturé + frais
  facturables. La facture qui fait foi est tenue par le système de
  facturation externe ; ici, les montants saisis servent de projection.
- **Coûts** = taux de coût interne du temps × temps + charges directes de
  matériel et de justificatifs.
- **Marge sur coûts variables** = revenu − coûts, également exprimée en
  **Marge** en pourcentage.

Autres analyses :

- **Classement** : « Top 5 clients (marge sur coûts variables) »,
  « Flop 5 clients (marge de contribution) » ainsi que la même chose pour
  les projets – les clients et projets déficitaires deviennent visibles.
- **Temps non facturable** : par client, **Facturable (min.)**, **Non
  facturable (min.)** et **Part %** montrent combien de temps a été saisi
  sans facturation – un indice de reprises et de gestes commerciaux. Par
  projet, **Retouche (min.)**, **Geste commercial (min.)** et **Retouche %**
  indiquent le temps saisi avec un motif de reprise ou de geste
  commercial.
- **Prévu vs réel** par projet : **Réel (min.)** face à **Plan (min.)**
  issu du budget temps du projet (**Δ Min.**) et coûts réels face au
  **Budget du plan** en euros (**Δ Budget**).

Remarques sur la qualité des données :

- Si **aucun taux de coût interne** n’est renseigné pour une partie des
  temps, ceux-ci sont comptés avec 0 € de coûts – la marge est alors trop
  optimiste. Les coûts portent alors un astérisque avec la mention « Taux
  de coût non entièrement renseignés ».
- Les projets **sans budget temps/budget** affichent « – » dans les
  colonnes du plan.

Export en **PDF**, **CSV** ou **Excel** pour la direction et le contrôle
de gestion. La page présente des données financières de toute
l’organisation et n’est accessible qu’aux personnes disposant du droit
**Voir les rapports**.

## Gérer le taux de coût par collaborateur

Le **taux de coût interne** par personne est la base du calcul a posteriori.
Il se trouve dans la boîte de dialogue du collaborateur (**Organisation** →
**Employés** → modifier), dans le groupe **Taux pour la facturation et le
calcul a posteriori**, à côté du **Taux horaire (facturation)**. Les deux
champs ne sont visibles et modifiables qu'avec le droit « Gérer les données du
personnel et de la paie » ; ils n'apparaissent pas dans le profil personnel, et
chaque modification est journalisée.

Ordre de détermination des taux : le taux de coût de la personne prime sur
celui de la tâche, du projet et du client. Le taux horaire de la personne
prime lui aussi sur celui de la tâche, du projet et du client — seules les
conditions particulières et un taux saisi directement sur l'entrée de temps
passent avant. Un nouveau taux s'applique aux temps saisis ou recalculés par la
suite.
