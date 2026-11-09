---
title: "Conditions particulières & compte client"
topic: customers.billing
version: 5
keywords:
    - prix spéciaux
    - taux horaire client
    - tarif week-end
    - tarif jours fériés
    - forfait de déplacement
    - frais de déplacement
    - solde
    - forfait mensuel
    - acompte mensuel
    - Lexoffice
    - sevDesk
    - easybill
    - clôture mensuelle
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
**facture mensuelle** ou **forfait** via le logiciel comptable (Lexoffice, sevDesk ou easybill).

Les conditions comprennent aussi un **forfait de déplacement** : chaque
saisie de temps facturable apporte alors x minutes supplémentaires,
valorisées au tarif de la saisie — au choix uniquement pour certaines
activités. Le temps de travail saisi reste inchangé, le compte de temps
et l’horaire variable ne bougent donc pas ; le justificatif et le PDF
présentent le déplacement dans une colonne dédiée tant que **Indiquer le déplacement séparément** est coché ; sans la coche, le déplacement est compris dans la durée dans le portail et le justificatif, le montant reste identique. Dans la saisie de
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

En **mode forfait**, le logiciel comptable de la souveraineté de
facturation du client gère le document et le paiement : Lexoffice, sevDesk
ou easybill. Le forfait mensuel est saisi hors taxes (« acompte mensuel
attendu ») ; le solde local oppose heures × taux au forfait payé. Deux
voies pour le document :

- **Envoyer le forfait** crée la facture dans le logiciel (également
  chaque mois, automatiquement, pour le mois précédent). sevDesk attribue
  le numéro de facture puis tient la facture comme envoyée et ouverte ;
  easybill la finalise et la numérote.
- **Associer un document** rattache au mois une facture que vous avez
  déjà créée dans le logiciel. Si exactement une facture du client
  correspond par mois et montant HT, cela se fait automatiquement lors
  de la synchronisation des documents.

Dès qu'un document est rattaché, « Envoyer le forfait » disparaît —
sinon un second document apparaîtrait dans le logiciel. Le statut de
paiement revient lors de la synchronisation et est enregistré **hors
taxes** (les logiciels tiennent le montant ouvert TTC) ; les paiements
partiels comptent au prorata. Pour sevDesk et easybill, le statut de
paiement provient du miroir des pièces, donc de la récupération régulière
des pièces ; une pièce annulée ou supprimée dans le logiciel annule le
paiement enregistré. Si l’envoi vers sevDesk ou easybill est
interrompu, la facture reste en brouillon ; un nouvel envoi la poursuit au lieu de créer une seconde pièce
dans le logiciel. Les paiements issus du logiciel ne peuvent pas être
annulés à la main dans le panneau : cela passe par la pièce dans le
logiciel.

Si les conditions particulières n'ont été créées qu'après coup, les
temps plus anciens affichent d'abord 0,00 € sous « total ».
**Recalculer** les valorise avec les taux enregistrés ; les taux forcés
manuellement restent intacts.

Le client voit ses présences et son solde dans le portail client sous
« Facturation » et peut y télécharger le relevé de présence en PDF.
