---
title: "Temps ouverts"
topic: finance.open-times
version: 2
audience: []
modules:
    - module.finance
related:
    - finance.transfers
    - roles.buchhaltung
    - glossary.core
---

La liste de travail **Temps ouverts** affiche toutes les saisies de
temps de l'organisation qui n'ont **pas encore été facturées** —
quelle que soit la personne qui les a saisies. C'est l'instrument de
contrôle de la comptabilité pour qu'aucun temps ne passe à la trappe
avant un cycle de facturation.

Qu'est-ce qui compte comme « ouvert » ? Une saisie qui n'a encore
été consommée par aucun canal de facturation — ni par une facture
locale, ni par la clôture du compte client, ni par une transmission
de facturation.

Les clients avec un solde courant (conditions particulières en mode
« compte client » ou « forfait ») n'apparaissent **pas** dans la liste :
leurs temps ne sont pas facturés mais réglés via le bloc mensuel de la
fiche client — ils y resteraient en permanence. Une note au-dessus de la
liste indique le nombre de saisies ainsi masquées. Les clients en mode
« facture mensuelle » restent visibles, ils passent par la facturation
normale.

Fonctionnalités :

1. **Indicateurs** en haut : nombre de saisies ouvertes, temps
   ouvert (format horloge et décimal), revenu net prévisionnel. Les
   tuiles d'alerte « Retardataires » et « Plus de 45 jours » comptent
   toujours sur l'ensemble du stock — indépendamment de la période
   sélectionnée.
2. **Période** : la liste suit la sélection de période globale dans
   l'en-tête de la page. Les paramètres de/à dans la barre d'adresse
   (signets) la remplacent.
3. **Filtres** : client, projet, collaborateur/trice et le
   commutateur « facturable ». « Non facturables uniquement » permet
   de vérifier les temps marqués non facturables volontairement ou
   par erreur.
4. **Totaux par client & projet** dans un bloc dépliable au-dessus
   de la liste détaillée.
5. **Export CSV** avec la durée dans les deux formats (H:MM et
   décimal).
6. **Marquer comme facturé** : pour la mise en service, clôture tous
   les temps ouverts jusqu'à une date butoir qui ont déjà été
   facturés en dehors du système — au choix pour un seul client et,
   si souhaité, y compris les saisies non facturables. L'action est
   réservée à l'administration et à la comptabilité et ne peut pas
   être annulée d'un clic.

La page est visible pour les rôles disposant de l'autorisation
« afficher toutes les saisies de temps » (par défaut comptabilité,
direction et administration).
