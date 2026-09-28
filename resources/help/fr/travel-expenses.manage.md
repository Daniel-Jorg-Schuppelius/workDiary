---
title: "Trajets, frais & indemnités forfaitaires"
topic: travel-expenses.manage
version: 1
audience: []
modules:
    - module.spesen
related:
    - invoices.manage
    - exports.payroll
    - reports.overview
---

Le carnet de route, les frais et les indemnités de repas documentent
les déplacements professionnels séparément, mais avec une période et
des justificatifs communs. Saisissez le trajet avec date, distance,
motif, véhicule et relevés kilométriques, complétez les dépenses avec
catégorie, montant, mode de paiement et justificatif, faites calculer
le forfait pour les voyages de plusieurs jours, puis vérifiez le tout
avant de le transmettre pour approbation ou décompte. Justificatifs,
kilométrages et horaires doivent être plausibles ; les enregistrements
approuvés ou décomptés ne sont pas modifiés en silence — les
corrections suivent un chemin traçable.

## Transmettre un frais à la comptabilité comme justificatif

Un frais **approuvé** peut être transmis directement depuis le dialogue des
justificatifs au système comptable de référence comme pièce d’achat — au lieu
de le saisir une seconde fois. L’ID externe revient à la création ; le doublon
ne peut pas naître.

Trois règles :

- **Frais approuvés uniquement.** La transmission est irrévocable — le système
  cible ne connaît ni modification ni suppression des pièces. Les corrections
  y passent par une contre-pièce.
- **Pas de transmission sans catégorie comptable.** La correspondance se gère
  par catégorie de frais (Administration → Catégories de frais) ; une
  catégorie devinée serait pire que le message d’erreur.
- **Dès la transmission, la pièce fait foi.** Le lien ne peut plus être
  défait — la pièce existe, liée ou non.

Les fichiers du frais sont transmis avec — sans fichier, la pièce ne vaut rien
pour la comptabilité.

### Correction par contre-justificatif

Si quelque chose ne va pas dans un frais déjà transmis, vous le corrigez dans la
boîte de dialogue du justificatif **par un contre-justificatif** – motif
obligatoire. Un avoir d'achat du même montant est transmis et annule le
justificatif d'origine en comptabilité. En même temps, un nouveau frais est créé
en **brouillon** avec une référence à l'ancien ; il passe par la validation et la
transmission comme tout autre.

Si le frais d'origine était validé mais pas encore remboursé, il est annulé –
sinon les deux seraient payés. S'il était déjà remboursé, le brouillon indique
que seule la différence doit être remboursée.

## Scanner le justificatif au lieu de le saisir

Au lieu de saisir montant, date et commerçant à la main, vous pouvez
**photographier le justificatif ou le déposer en PDF**. La reconnaissance lit
les champs habituels et préremplit le formulaire.

Le résultat est une **proposition**, pas une écriture finie : vérifiez montant,
date, taux de taxe et commerçant avant d'enregistrer. Les photos mal éclairées,
le papier thermique et les justificatifs manuscrits sont les sources d'erreur
les plus fréquentes.

Le justificatif d'origine reste attaché tel quel — la reconnaissance ne le
remplace pas, elle vous épargne seulement la saisie.

## Carnet de route : signature, correction et comparaison 1 %

En mode carnet de route, la personne qui conduit clôture un trajet **avec sa
signature** ; le trajet est ensuite verrouillé. Un trajet déjà verrouillé en fin
de journée peut encore être signé. Si un trajet au milieu de la chaîne est corrigé
par un trajet d'annulation et que son kilométrage final change, le trajet suivant
commence automatiquement à ce point — sous forme de correction de suite,
l'original est conservé.

La **comparaison 1 %** sous le justificatif du carnet de route oppose, par véhicule
et par année, la méthode du carnet de route à la règle du 1 %. Le véhicule a
besoin du prix catalogue brut et de la distance domicile–travail ; vous y saisissez
les autres coûts annuels (leasing, assurance, taxe), l'énergie provient des
justificatifs de carburant et de recharge. En option, un paramètre bloque les
nouveaux trajets tant que le contrôle obligatoire d'un véhicule est en retard.
