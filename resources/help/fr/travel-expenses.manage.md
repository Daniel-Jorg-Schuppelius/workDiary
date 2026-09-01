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
