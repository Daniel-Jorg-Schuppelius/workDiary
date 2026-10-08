---
title: "Moyens de contrôle et étalonnage"
topic: asset-compliance.overview
version: 2
keywords:
    - métrologie
    - gestion des instruments de mesure
    - constat de vérification
    - vérification périodique
    - contrôle réglementaire
    - échéances de contrôle
    - rapport de contrôle
    - contrôle électrique
    - contrôle technique
    - blocage du matériel
    - ISO 17025
audience: []
modules:
    - module.asset_compliance
related:
    - rental.overview
    - asset-finance.overview
---

Le module gère les équipements soumis à contrôle : vérification,
étalonnage, contrôles réglementaires, contrôle technique, contrôle
électrique, maintenance constructeur et contrôles internes — avec
preuves et blocages d'utilisation.

**Profils de contrôle (catalogue) :** les modèles globaux sont remplacés
par les profils d'organisation portant le même code (intervalle,
préavis, tolérance, délai de grâce, effet bloquant).

**Obligations :** l'affectation d'un profil à un actif crée une
obligation avec échéance et responsable. Les contrôles échus alertent ;
après le délai de grâce, le système bloque via le modèle de blocage
commun — location, planification et utilisation lisent le même statut.

**Protocoles et certificats :** valeurs mesurées contre limites figées,
résultat, validité, signature et certificat d'étalonnage optionnel. Les
preuves sont immuables — corrections versionnées.

**Dérogations** limitées dans le temps, justifiées et auditées.
**Contrôleurs externes** via un accès limité. **Matrice de référence
normative** sans promesse de conformité.

## Calendrier des contrôles

Menu **Moyen de contrôle** → **Calendrier des contrôles** ; la page est aussi
accessible par un onglet sur les autres pages des moyens de contrôle. La
liste affiche les rendez-vous de contrôle ouverts (**Planifié**, **Annoncé**,
**En cours**), triés par échéance. Le filtre de statut montre aussi les
rendez-vous réalisés, manqués ou annulés. Colonnes : **Échéance** (avec la
date planifiée, le cas échéant), **Actif**, **Profil de contrôle**,
**Contrôleur / organisme de contrôle** et **Statut**.

**Planifier un rendez-vous de contrôle** (en bas de la page) : choisissez
l'**Obligation de contrôle** – la liste indique l'actif, le profil et la
prochaine échéance –, **À échéance le** (obligatoire), éventuellement
**Planifié le**, **Contrôleur interne** ou **Organisme de contrôle externe**,
puis **Planifier un rendez-vous**. Un nouveau rendez-vous commence à l'état
**Planifié**. Pour les rendez-vous avec un organisme externe, **Inviter un
accès** invite l'organisme via un accès limité dans le temps.

**Saisir un contrôle** est proposé pour les rendez-vous ouverts et ouvre le
procès-verbal de contrôle :

- **Résultat** : **Réussi**, **Réussi avec réserves** ou **Non réussi**.
- **Effectué le (vide = maintenant)** et **Valable jusqu'au (vide =
  intervalle)** : sans indication, la preuve vaut pour un intervalle de
  contrôle à compter de la réalisation. Un contrôle non réussi ne reçoit pas
  de date de validité.
- Une valeur mesurée par exigence du profil ; les valeurs limites figurent à
  côté.
- **Décision de suite** : **Aucune / validation**, **Réétalonnage
  (bloquant)**, **Réparation (bloquante)**, **Utilisation restreinte**
  (seulement consignée, ne bloque pas), **Blocage**, **Mise au rebut** (bloque
  également) ou **Ouvrir une réclamation** (crée une réclamation si votre
  organisation utilise le module réclamations et garantie), ainsi que la
  **Justification de la mesure**.
- **Certificat / preuve de contrôle** (dépliable) : numéro de certificat,
  émetteur (obligatoire dès qu'un numéro est saisi), date d'émission,
  validité, plage de mesure, tolérance et un document dont le hachage est
  enregistré.
- **Signature (nom)**, **Coût du contrôle (net, €)** et **Remarque**.

**Documenter le contrôle** crée une preuve immuable et clôt le rendez-vous à
l'état **Réalisé**. Un contrôle réussi fixe la prochaine échéance à un
intervalle de contrôle après la réalisation. Réussi sans mesure de suite lève
les blocages dus à un contrôle en retard ou non réussi ; « Non réussi » sans
mesure choisie bloque l'actif. Si le profil exige un certificat, un contrôle
réussi sans numéro de certificat est refusé.

**Autorisation :** consultation avec **Lister les obligations de contrôle et
moyens de contrôle** ; planification des rendez-vous avec **Gérer les profils
et obligations de contrôle** ; saisie des contrôles avec **Réaliser les
contrôles et consigner les preuves**. **Inviter un accès** exige l'un des deux
derniers droits.

## Ordres d'inspection

Menu **Moyen de contrôle** → **Ordres d'inspection**. Vous confiez ici des
contrôles échus à un prestataire de contrôle ; celui-ci n'a pas besoin de
compte utilisateur. La liste affiche **Intitulé**, **Prestataire de
contrôle**, le nombre d'**Équipements**, **Statut** et **Prix de l'offre** ;
**Ouvrir** mène à l'ordre.

Déroulement d'un ordre :

1. **Créer un ordre d'inspection** : choisissez **Intitulé**, **Prestataire de
   contrôle** (parmi vos fournisseurs), **E-mail du prestataire** et au moins
   un rendez-vous de contrôle à l'état **Planifié** ou **Annoncé**, puis
   **Envoyer l'ordre**. Le prestataire reçoit un e-mail avec un lien valable
   90 jours. Les rendez-vous choisis passent à **Annoncé**, l'ordre est à
   l'état **Demandé**.
2. Via le lien, le prestataire soumet une offre avec prix, date prévue et
   remarque ; l'ordre passe à **Offre reçue**. Dans la vue de l'ordre, vous
   choisissez **Accepter l'offre** (statut **Commandé**) ou **Refuser l'offre**
   (retour à **Demandé** ; le prestataire peut soumettre une nouvelle offre).
3. Après la commande, le prestataire déclare pour chaque équipement le
   résultat, la date de contrôle, la validité, le numéro de certificat et une
   remarque, avec en option le certificat sous forme de fichier. L'ordre passe
   alors à **Résultats déclarés**.
4. **Reprendre les résultats** crée une preuve de contrôle pour chaque
   équipement déclaré – comme un contrôle du calendrier des contrôles, avec le
   prestataire comme contrôleur et émetteur et le certificat avec sa somme de
   contrôle. L'ordre est ensuite **Terminé** ; le tableau indique « repris »
   pour les équipements.

**Annuler l'ordre** est possible tant qu'aucun résultat n'a été déclaré ; les
rendez-vous annoncés redeviennent **Planifié**. Si un profil exige un
certificat et qu'un résultat réussi n'a pas de numéro de certificat, la
reprise s'interrompt avec un message.

**Autorisation :** consulter la liste et l'ordre avec **Lister les obligations
de contrôle et moyens de contrôle** ; créer, décider de l'offre et annuler avec
**Gérer les profils et obligations de contrôle** ; reprendre les résultats
avec **Réaliser les contrôles et consigner les preuves**.

## Tournées de contrôle

Onglet **Tournées de contrôle**, par exemple dans le **Calendrier des
contrôles**. Une tournée de contrôle est une liste cible des contrôles échus
d'un site ou d'un groupe, que vous traitez sur place par scan. La liste
affiche **Désignation**, **Échéance jusqu'au**, **Effectués** (contrôles
effectués sur l'ensemble) et **Statut** (**Ouverte** ou **Clôturée**).

**Créer une tournée** : **Désignation**, **Échéance jusqu'au** (prérempli avec
aujourd'hui plus 30 jours) et éventuellement **Site**, **Groupe (catégorie)**,
**Profil de contrôle** et **Client**. La tournée reprend toutes les
obligations de contrôle actives échues à cette date ; les actifs mis au rebut
sont exclus. Les obligations qui arrivent à échéance plus tard ne sont pas
ajoutées. Si rien n'est échu pour la sélection, aucune tournée n'est créée.

Dans la tournée, vous voyez les indicateurs **Effectués**, **Manquants** et
**En retard** ainsi que toutes les positions avec actif, profil de contrôle,
échéance et état (**Ouvert**, **En retard** ou le résultat saisi).

- **Scanner l'objet** : saisissez ou scannez un code QR, un numéro
  d'installation, un numéro d'inventaire ou un numéro de série, puis
  **Ouvrir**. Sur les appareils compatibles NFC, **Lire le tag NFC** apparaît
  en plus. Si un seul contrôle est ouvert pour l'objet, la saisie s'ouvre ;
  s'il y en a plusieurs, vous choisissez le profil de contrôle.
- **Saisir le contrôle** (saisie rapide) : **Résultat**, **Remarque** et
  **Signature (nom)** (préremplie avec votre nom), puis **Enregistrer le
  contrôle**. Cela crée la même preuve immuable que dans le calendrier des
  contrôles, mais sans valeurs mesurées ni certificat. « Non réussi » bloque
  l'actif. Si le profil exige un certificat, saisissez un contrôle réussi dans
  le calendrier des contrôles – la saisie le signale.
- **Clôturer la tournée** : les contrôles encore ouverts restent comme
  manquants ; ensuite, plus aucune saisie n'est possible dans la tournée.

**Autorisation :** consultation avec **Lister les obligations de contrôle et
moyens de contrôle** ; créer, scanner, saisir et clôturer des tournées avec
**Réaliser les contrôles et consigner les preuves**.

## Tournée d'inspection

Dans le **Calendrier des contrôles** via le bouton **Tournée d'inspection**.
Vous planifiez ainsi les rendez-vous de contrôle ouverts d'un contrôleur
interne sous forme de tournée.

- En haut, vous choisissez l'**Inspecteur** (prérempli : vous-même) et
  **Échéance jusqu'au** (prérempli : aujourd'hui plus 14 jours).
- Le tableau affiche les rendez-vous ouverts pour lesquels cette personne est
  indiquée comme contrôleur interne et qui ne sont encore rattachés à aucune
  intervention, avec échéance, actif, profil de contrôle et **Emplacement**.
  Tous les rendez-vous sont présélectionnés.
- Avec **Date de la tournée** (préremplie : demain) et **Planifier la
  tournée**, chaque rendez-vous choisi devient une intervention du contrôleur
  à l'emplacement de l'appareil. Le rendez-vous reçoit la date de la tournée
  comme date planifiée, la planification des tournées crée la tournée et
  optimise l'ordre ; la tournée s'ouvre ensuite.
- Les appareils marqués **sans coordonnées** restent dans la tournée mais ne
  sont pas pris en compte dans le calcul de l'itinéraire.
- Les tournées d'inspection nécessitent le module Planification. Sans ce
  module, la page affiche une mention et aucun bouton de planification.

**Autorisation :** **Gérer les profils et obligations de contrôle**.

## Rapport d'audit

Menu **Moyen de contrôle** → **Rapport d'audit** (titre de la page **Rapport
d'audit du système de contrôle**). Vous choisissez la période dans la barre de
filtres de la page ; par défaut, ce sont les trois derniers mois.

- État actuel, indépendamment de la période : **Obligations de contrôle**
  (obligations actives), **En retard** (échéance et tolérance dépassées),
  **Échéance proche** (dans le délai de préavis du profil) et **Bloqué
  (contrôles)** (blocages actifs dus à un contrôle en retard ou non réussi).
- Liés à la période : **Contrôles sur la période**, **Non réussi**, **Taux de
  contrôle** (part des contrôles réussis, y compris avec réserves),
  **Certificats**, **Coûts de contrôle sur la période** et les coûts de trois
  types de contrôle au plus.
- Tableaux : **Obligations de contrôle par type de contrôle**, **Contrôles par
  contrôleur (top 10)** et **Écarts (échec)** avec actif, moment et remarque.

**Figer l'instantané** enregistre de manière immuable les indicateurs de la
période choisie. Les dix derniers instantanés figurent sous **Instantanés
gelés (P2)** avec période, date de création, nombre d'obligations en retard
et taux de contrôle.

**Autorisation :** **Lister les obligations de contrôle et moyens de
contrôle** – cela vaut aussi pour figer un instantané.
