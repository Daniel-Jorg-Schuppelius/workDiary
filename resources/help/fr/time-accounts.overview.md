---
title: "Comptes de temps"
topic: time-accounts.overview
version: 2
keywords:
    - compte supplémentaire
    - compte de repos
    - repos compensateur
    - compteur de nuits
    - heures de primes
    - solde du compte
    - feu tricolore
    - journal des écritures
    - contre-passation
    - exporter les comptes
    - travail supplémentaire
    - comparaison de périodes
audience: []
related:
    - time-accounts.flex
---

Les comptes de temps supplémentaires suivent des grandeurs de temps
choisies comme comptes dédiés — par exemple un compteur de services de
nuit effectués, un compte de repos pour le travail supplémentaire ou des
heures de primes cumulées. L'horaire flexible et les congés restent dans
le compte de temps de travail.

L'aperçu montre par compte le solde actuel avec un feu tricolore (les
seuils sont définis par l'organisation), la moyenne mensuelle et une
tendance simple. « Voir le journal » affiche chaque écriture avec date,
quantité, source et note — les corrections apparaissent comme
contre-écritures, rien n'est écrasé.

L'évaluation (pour les rôles de direction) compare solde initial,
mouvement et solde final par employé sur une période et s'exporte en CSV
ou PDF.

## Comparaison de périodes

La comparaison de périodes présente côte à côte les écritures d'un compte de
temps par semaine calendaire ou par mois. Vous la trouvez sous **Rapports** →
**Équipe** → **Comparaison de périodes**.

- Dans la barre de filtres, vous choisissez le **Compte** (tous les comptes
  de temps actifs) et la **Granularité** : **Semaine calendaire** (par défaut)
  ou **Mois**. Le choix s'applique immédiatement.
- La période suit le filtre de dates de l'en-tête. Au plus 53 colonnes sont
  affichées, soit une année en semaines.
- Pour chaque employé, le tableau affiche le **Solde initial** (somme de
  toutes les écritures antérieures à la période), la somme par semaine ou par
  mois, le **Mouvement** de la période et le **Solde
  final**. Le solde final porte la couleur du feu tricolore
  du compte. Toutes les valeurs sont exprimées dans l'unité du compte.
- Les personnes dont le solde initial et le mouvement sont tous deux nuls
  n'apparaissent pas. S'il n'existe aucune valeur sur la période, la page
  indique « Aucune écriture sur la période choisie. »

**Export :** **PDF** ainsi que, sous **Export**, les formats **CSV** et
**Excel**. Le PDF et le CSV contiennent le compte choisi. Excel fournit tous
les comptes actifs, chacun dans une feuille du même classeur.

**Visibilité :** le rôle **Administrateur** voit tous les employés de
l'organisation, toutes les autres personnes ne voient que leur propre ligne.
Si aucun compte de temps actif n'est configuré, la page affiche la mention
« Aucun compte de temps configuré ».
