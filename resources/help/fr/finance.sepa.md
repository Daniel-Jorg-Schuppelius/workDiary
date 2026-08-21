---
title: "Paiements sortants SEPA"
topic: finance.sepa
version: 1
audience: []
related:
    - finance.incoming-invoices
    - invoices.manage
    - contacts.manage
---

L’ordre de paiement regroupe les factures fournisseurs validées en un
virement groupé SEPA. workDiary produit un **fichier, pas un ordre de
paiement** : le paiement est déclenché dans le programme bancaire, avec sa
propre autorisation.

**Proposition de paiement :** La liste contient toutes les factures
fournisseurs ouvertes validées pour paiement. Pour chacune, la date
d’exécution la plus avantageuse est proposée — la date d’escompte tant
qu’elle est atteignable, sinon l’échéance. Le montant est alors déjà réduit
de l’escompte. Chaque position est décochable ; une facture sans IBAN
s’affiche comme bloquée et n’entre pas dans l’ordre.

**Trois étapes :** composer (brouillon) → valider → exporter. La validation
est un droit distinct : qui compose l’ordre n’a pas nécessairement le droit
de le valider. Après l’export, l’ordre est immuable ; l’annulation n’est
possible qu’avant et rend les factures de nouveau payables.

**Retenue :** Des positions peuvent être ramenées à un montant inférieur tant
que l’ordre est un brouillon — par exemple pour une retenue de garantie
envers le fournisseur. Un montant réduit exige un motif ; montant facturé et
montant payé figurent alors côte à côte.

**Preuve :** Le fichier produit est archivé comme document confidentiel et
son empreinte SHA-256 conservée sur l’ordre. Une seconde récupération renvoie
le même fichier — jamais un nouveau avec un identifiant de message différent,
que la banque pourrait interpréter comme un second paiement.

**Mandats et prélèvement :** Pour le prélèvement, le registre des mandats
conserve la référence, la date de signature et le type (ponctuel/récurrent).
Un mandat n’est jamais supprimé mais révoqué — la révocation prouve à partir
de quand le prélèvement n’était plus autorisé. Après 36 mois sans
prélèvement, un mandat est caduc. Le délai de préavis est de cinq jours
ouvrés bancaires pour le premier prélèvement et de deux pour les suivants. Le
prélèvement exige l’identifiant créancier de l’organisation (paramètre « identifiant créancier » dans le registre des
paramètres).

**Module complémentaire :** La production du fichier relève du module payant
de formats bancaires. Sans lui, l’ordre de paiement et le registre des
mandats restent utilisables ; seul l’export manque.
