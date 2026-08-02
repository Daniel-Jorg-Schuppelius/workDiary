---
title: "Conditions particulières & compte client"
topic: customers.billing
version: 2
audience: []
related:
    - contacts.manage
    - invoices.manage
    - customer-portal.billing
---

Dans la fiche client, vous pouvez définir des **conditions
particulières** : taux horaires propres par activité et type de jour
(semaine/week-end, définis via « jours ouvrés par semaine ») ainsi que le
mode de facturation — **compte client** sans factures avec solde courant,
**facture mensuelle** ou **forfait (Lexoffice)**.

En mode compte, chaque mois reçoit un bloc de facturation : total (heures
× taux), réglé (paiements), mois précédent (report) et solde dû. Le solde
est reporté automatiquement au mois suivant. Les mois sont **clôturés**
chronologiquement (verrou + instantané, les temps comptent comme réglés)
et peuvent être rouverts en ordre inverse.

Enregistrez les paiements manuellement sur le panneau ou via le
rapprochement bancaire (le compte client est une cible d'affectation).
Les saisies tardives dans des mois clôturés sont signalées — rouvrez le
mois ou changez la date.

En **mode forfait**, Lexoffice gère le document et le paiement. Le
forfait mensuel est saisi hors taxes (« acompte mensuel attendu ») ; le
solde local oppose heures × taux au forfait payé. Deux voies pour le
document :

- **Envoyer le forfait** crée la facture dans Lexoffice (également
  chaque mois, automatiquement, pour le mois précédent).
- **Associer un document** rattache au mois une facture que vous avez
  déjà créée dans Lexoffice. Si exactement une facture du client
  correspond par mois et montant HT, cela se fait automatiquement lors
  de la synchronisation des documents.

Dès qu'un document est rattaché, « Envoyer le forfait » disparaît —
sinon un second document apparaîtrait dans Lexoffice. Le statut de
paiement revient lors de la synchronisation et est enregistré **hors
taxes** (Lexoffice travaille en TTC).

Si les conditions particulières n'ont été créées qu'après coup, les
temps plus anciens affichent d'abord 0,00 € sous « total ».
**Recalculer** les valorise avec les taux enregistrés ; les taux forcés
manuellement restent intacts.

Le client voit ses présences et son solde dans le portail client sous
« Facturation » et peut y télécharger le relevé de présence en PDF.
