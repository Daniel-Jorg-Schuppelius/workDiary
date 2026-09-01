---
title: "Conditions particulières & compte client"
topic: customers.billing
version: 3
audience: []
modules:
    - module.vertrieb
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

Les conditions comprennent aussi un **forfait de déplacement** : chaque
saisie de temps facturable apporte alors x minutes supplémentaires,
valorisées au tarif de la saisie — au choix uniquement pour certaines
activités. Le temps de travail saisi reste inchangé, le compte de temps
et l’horaire variable ne bougent donc pas ; le justificatif et le PDF
présentent le déplacement dans une colonne dédiée. Dans la saisie de
temps, la valeur peut être remplacée au cas par cas (0 compris). Les
temps de trajet ou d’astreinte et les saisies au forfait n’obtiennent
aucun déplacement.

Ce qui compte comme week-end découle des « jours ouvrés par semaine »
(6 = dimanche uniquement). En option, les **jours fériés** comptent aussi
comme week-end, d’après le calendrier des jours fériés de
l’organisation. C’est le jour calendaire du début qui fait foi : une
saisie qui dépasse minuit est entièrement rattachée à son jour de début.

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
