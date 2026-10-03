---
title: "Commissions"
topic: commissions
version: 1
audience:
    - admin
    - geschaeftsfuehrung
    - buchhaltung
modules:
    - module.vertrieb
related:
    - invoices.manage
    - finance.reconciliation
---

Les commissions naissent de factures **payées**. Les pages montrent trois
choses : les **règles** (qui touche quoi et combien), les **lignes ouvertes**
et les **campagnes** de règlement.

## Le seul moment où naît une commission

Exactement lorsqu’une facture passe à **payée** — quel que soit le chemin
(rapprochement bancaire, livre de caisse, règlement de forfait, action
manuelle). **Émise mais impayée ne crée jamais de commission.**

Ce n’est pas un détail : commissionner à l’émission, c’est payer pour un
chiffre d’affaires qui n’arrivera peut-être jamais — et devoir le récupérer
ensuite.

## Annulation et avoir : contre-passation, pas correction

Une facture annulée ou avoirée **ne modifie pas la ligne de commission
initiale**. Une seconde ligne aux montants négatifs est créée. Deux cas :

- La ligne initiale n’est **pas encore réglée** : les deux lignes passent en
  « contre-passée » et n’entrent dans aucune campagne — rien n’avait été
  déclaré. L’opération reste comme trace écrite.
- La ligne initiale se trouve dans une **campagne clôturée** : elle reste
  inchangée, car la campagne fait foi vis-à-vis de la paie. La ligne négative
  tombe dans la campagne suivante.

La raison de cette lourdeur : une campagne clôturée a déjà été déclarée et
peut-être versée. La modifier après coup reviendrait à falsifier une pièce
que quelqu’un d’autre a déjà traitée.

## Campagnes

Une campagne regroupe les lignes ouvertes d’une période. Une fois clôturée
elle fait foi — les corrections passent par la campagne suivante, jamais par
la retouche de l’ancienne.

## Paliers, plafond, délai de garantie, paiements partiels et intermédiaires

Une règle peut comporter des **paliers** : dès que le chiffre d'affaires d'une
personne sur le mois, le trimestre ou l'année atteint un seuil, le taux du
palier le plus élevé atteint s'applique au nouveau montant. Les lignes déjà
créées ne sont pas recalculées. Un **plafond annuel** limite la commission par
année civile ; l'excédent est perdu, avec une mention sur la ligne.

Avec un **délai de garantie**, une commission ne devient payable qu'après ce
délai et entre dans le décompte de cette période — si la facture est annulée
avant, elle disparaît avant d'avoir été déclarée. Si **« Dès les paiements
partiels »** est coché, la commission naît au prorata de chaque paiement reçu
au lieu d'attendre le paiement complet.

Si un paiement est annulé dans le rapprochement bancaire, WorkDiary reprend la
commission jusqu'à la part alors payée (entièrement sans « paiements
partiels ») — sous forme de ligne négative distincte ; la ligne existante reste.
Si le paiement arrive de nouveau, la commission naît à nouveau.

Les commissions peuvent aussi revenir à des **intermédiaires externes** sans
compte utilisateur. Ils sont gérés sous « Intermédiaires », affectés à la
facture et apparaissent dans le décompte et l'export aux côtés des salariés.
