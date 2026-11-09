---
title: "Location de matériel"
topic: rental.overview
version: 5
keywords:
    - bailleur
    - location de vacances
    - bail
    - louer du matériel
    - location d'outils
    - location d'engins
    - parc locatif
    - contrat de location
    - caution
    - réservation de matériel
    - retour de matériel
    - état des lieux
    - calendrier de disponibilité
    - tarif de location
    - télématique
audience: []
modules:
    - module.rental
related:
    - claims.overview
---

Le module gère la location d'appareils et de machines sous forme de
dossiers traçables — de la réservation à la restitution, caution et
facturation comprises.

**Parc d'appareils :** un profil de location rend un actif louable
(groupe, temps tampons, accessoires, grille tarifaire par défaut).

**Disponibilité :** le calendrier affiche réservations, locations et
fenêtres de maintenance. Les doubles réservations et les appareils
bloqués sont empêchés de manière visible.

**Dossier de location :** chaque opération reçoit un numéro (VER-…) ;
la version de la grille tarifaire appliquée est figée en instantané.
L’interlocuteur est prérempli depuis la fiche client au choix du client et reste modifiable.

**Remise & retour :** des protocoles distincts documentent état,
accessoires, compteurs, photos et signature ; le retour porte la décision
de suite (nettoyage, réparation/blocage, réclamation).

**Facturation :** les postes sont validés puis facturés localement ou
transmis au système de facturation principal. La caution est une
opération financière distincte.

**Lieu d'intervention et géorepérage :** Lorsque des positions d'appareils
arrivent par import (par exemple d'un export télématique), WorkDiary
vérifie pendant une location si l'appareil se trouve sur le lieu
d'intervention : au site de la location avec le rayon défini dans les
paramètres, sinon dans les géorepérages du client. S'il le quitte, la
personne responsable et la direction d'équipe sont informées.

## Logements et locations de vacances

Le profil **Bailleurs** recommande le module de location. La vue des logements utilise la période globale et affiche aussi les logements libres. Les baux enregistrent le locataire, le début, la fin facultative, le loyer, les charges et la caution. Un bail confirmé bloque le logement jusqu’au dernier jour inclus, sans limite si la fin est vide. Indiquez la fin au départ ; annuler supprime toute l’occupation.

Accepter une demande de vacances crée une réservation ferme. Le tarif journalier sert de prix par nuit ; le nettoyage final est facultatif. Capacité, séjour minimum et horaires sont contrôlés. Publiez le logement et autorisez les demandes de location pour le client. Les loyers et cautions restent des données administratives du MVP, sans facturation automatique.

Le profil fournit aussi trois procédures (**Remise du logement**, **État des lieux de sortie**, **Changement de locataires**), le **procès-verbal de remise du logement** avec relevés de compteurs et clés, des modèles de contrat pour le bail d’habitation avec ou sans loyer indexé et pour les conditions de location d’un meublé de tourisme, avec des échéances comme le décompte annuel des charges, ainsi que des champs personnalisés du logement (surface, pièces, étage, animaux, diagnostic énergétique). Vous confirmez, refusez ou annulez une location demandée ; une location confirmée peut être annulée. Les locations annulées ou refusées restent inchangées.

## Réservation directe avec acompte en ligne

Si un **Acompte en réservation directe** est défini sous « Organisation et
paramètres » (ou un taux propre sur l’objet de location), la réservation
directe dans le portail client mène à la page de paiement du prestataire de
paiement :

- Le dossier est réservé immédiatement et retient l’appareil jusqu’au **délai
  de paiement**. Jusque-là, la réservation affiche **paiement en attente** sous
  **Demandes de location**.
- À réception du paiement (notification du prestataire ou retour du client), la
  réservation est **acceptée** ; ce n’est qu’alors que vous êtes notifié.
- Sans paiement dans le délai, la réservation **expire**, le dossier est annulé
  et l’appareil redevient libre. Si un paiement n’arrive qu’ensuite, WorkDiary
  ne confirme rien mais vous le signale pour un remboursement ou une nouvelle
  réservation.
- Le dossier affiche l’acompte payé. Lors du transfert des postes de location
  vers la facture, il est rattaché à la facture et la couvre. Si un logiciel de
  comptabilité gère les factures, le montant figure dans le journal du
  transfert ; tenez-en compte là-bas. Dans la boîte de comptabilisation,
  l’acompte ne devient comptabilisable qu’avec la facture.
