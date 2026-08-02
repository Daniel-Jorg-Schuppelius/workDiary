---
title: "Comportement de paiement"
topic: reports.payment-behavior
version: 2
audience: []
related:
    - reports.economics
    - reports.customer-value
---

Vue comportementale et tendancielle des **factures gérées localement** —
le rapport de facturation montre l'état des lieux (statut, ancienneté),
ce rapport le comportement sous-jacent. La date de référence est toujours
la **fin de période** (rapports reproductibles).

## DSO avec exemple chiffré

**DSO** (days sales outstanding) = créances ouvertes en fin de mois
÷ chiffre d'affaires des 90 derniers jours × 90. Exemple : 12 000 €
ouverts pour 48 000 € de CA sur 90 jours → 12 000 ÷ 48 000 × 90 =
**22,5 jours** d'immobilisation moyenne du capital. Une courbe qui monte
signifie que l'activité immobilise de plus en plus de trésorerie.

## Délai de paiement vs retard

- **Délai de paiement** = jours entre émission et paiement (indépendant
  de l'échéance) — en tendance mensuelle et en distribution par client.
- **Retard** = jours **après échéance** ; les payeurs anticipés comptent
  pour 0. Le top liste les clients au retard moyen le plus élevé.

Lire la boîte à moustaches : trait = médiane, boîte = moitié centrale,
moustaches = étendue. Un client à médiane 40 jours pour une échéance à
14 jours paie systématiquement tard — question de conditions, pas un cas
isolé.

## Qu'en faire ?

- **DSO en hausse** → revoir les relances, raccourcir les échéances,
  envisager l'escompte.
- **Clients au retard moyen élevé** → renégocier les délais, acompte/
  paiement anticipé pour les nouvelles commandes, limite de crédit interne.
- **Factures ouvertes en retard** (tableau en bas) → accéder directement
  à la facture ou aux factures ouvertes du client.

Un clic sur un client dans la boîte à moustaches ou le top des retards
filtre ce rapport sur lui ; si Lexoffice gère les factures, elles
arrivent via le miroir de pièces du plugin — la synchronisation récupère
aussi les données de paiement (endpoint payments). Sans aucune source,
le rapport l'indique ouvertement.
