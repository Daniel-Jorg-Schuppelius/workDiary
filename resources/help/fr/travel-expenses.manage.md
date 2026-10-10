---
title: "Trajets, frais & indemnités forfaitaires"
topic: travel-expenses.manage
version: 3
keywords:
    - notes de frais
    - frais de déplacement
    - carnet de route
    - indemnités kilométriques
    - indemnité de repas
    - per diem
    - scanner un justificatif
    - ticket de caisse
    - rembourser des frais
    - véhicule de fonction
    - avantage en nature
    - temps de conduite
    - carnet de trajets
    - saisir un trajet
audience: []
modules:
    - module.spesen
related:
    - invoices.manage
    - exports.payroll
    - reports.overview
---

Le carnet de bord, les frais et les indemnités de repas documentent
les déplacements professionnels séparément, mais avec une période et
des justificatifs communs. Saisissez le trajet avec date, distance,
motif, véhicule et relevés kilométriques, complétez les dépenses avec
catégorie, montant, mode de paiement et justificatif, faites calculer
le forfait pour les voyages de plusieurs jours, puis vérifiez le tout
avant de le transmettre pour approbation ou décompte. Justificatifs,
kilométrages et horaires doivent être plausibles ; les enregistrements
approuvés ou décomptés ne sont pas modifiés en silence — les
corrections suivent un chemin traçable.

## Saisir un trajet

Vous créez un nouveau trajet via **Nouveau …** dans la barre latérale : dans le
groupe **Planification**, **Carnet de trajets** ouvre la boîte de dialogue
**Enregistrer un nouveau trajet**. Tous les trajets saisis figurent sous
**Déplacements et frais** → **Carnet de bord**.

- **Trajet :** **Date**, **Véhicule** (type de véhicule avec son tarif
  kilométrique), **Véhicule de flotte (optionnel)**, **Type de trajet** ainsi
  que **De (adresse)** et **Vers (adresse)**.
- **Distance et tarif :** **Distance (km, aller simple)** est obligatoire. Si
  **Tarif €/km (facultatif)** reste vide, le tarif du véhicule de flotte
  s'applique, sinon celui du type de véhicule. S'y ajoutent **Kilométrage au
  départ (km)**, **Kilométrage à l’arrivée (km)**, **Début (heure)** et **Fin
  (heure)** ; si le trajet se termine après minuit, saisissez simplement l'heure
  la plus petite.
- **Attribution :** **Projet (facultatif)**, **Client (facultatif)** et
  **Objet**.
- **Options et notes :** **Aller-retour (double les km)**, **Remboursable**
  (présélectionné) et **Notes**.

**Saisir** enregistre le trajet à votre nom et revient à la liste. Si le début
et la fin sont renseignés, WorkDiary crée par défaut une saisie de temps non
facturable pour le temps de trajet. Un kilométrage d'arrivée plus élevé est
repris dans le véhicule de flotte. Si le véhicule de flotte est en **Mode
carnet de bord**, les relevés kilométriques sont obligatoires et le trajet est
figé après la fin de la journée. Toute personne connectée peut saisir des
trajets si votre organisation utilise le module ; un trajet appartient toujours
à la personne qui l'a saisi.

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

## Carnet de bord : signature, correction et comparaison 1 %

En mode carnet de bord, la personne qui conduit clôture un trajet **avec sa
signature** ; le trajet est ensuite verrouillé. Un trajet déjà verrouillé en fin
de journée peut encore être signé. Si un trajet au milieu de la chaîne est corrigé
par un trajet d'annulation et que son kilométrage final change, le trajet suivant
commence automatiquement à ce point — sous forme de correction de suite,
l'original est conservé.

La **comparaison 1 %** sous le justificatif du carnet de bord oppose, par véhicule
et par année, la méthode du carnet de bord à la règle du 1 %. Le véhicule a
besoin du prix catalogue brut et de la distance domicile–travail ; vous y saisissez
les autres coûts annuels (leasing, assurance, taxe), l'énergie provient des
justificatifs de carburant et de recharge. En option, un paramètre bloque les
nouveaux trajets tant que le contrôle obligatoire d'un véhicule est en retard.

**Temps de conduite et de repos.** Si votre organisation applique les règles
de temps de conduite, indiquez un second conducteur sur le trajet (équipage
multiple) ou marquez les traversées où le véhicule voyage sur un ferry ou un
train. Le second conducteur n'accumule pas de temps de conduite, mais son temps
dans le véhicule ne compte pas comme repos ; en équipage multiple, 9 heures de
repos en 30 heures suffisent. Une traversée en ferry ou en train n'interrompt
pas le repos.
