---
title: "Clients & fournisseurs"
topic: contacts.manage
version: 2
audience: []
modules:
    - module.vertrieb
schema: process
related:
    - projects.manage
    - invoices.manage
    - admin.import
    - communication.notes
---

## Objectif et contexte

Clients et fournisseurs sont les données de base centrales de
WorkDiary : projets, commandes, factures, communication, déplacements
et analyses en dépendent. Des données propres décident si les
processus ultérieurs — de la saisie des temps à la remise DATEV —
fonctionnent sans reprise.

## Prérequis

- Le droit de gérer les clients ou fournisseurs (en général
  administration ou ventes).
- Pour l'import plutôt que la saisie manuelle : l'assistant d'import
  CSV.
- Les identifiants externes (n° de débiteur, identifiants des
  intégrations de facturation) si des pièces doivent être remises.

## Déroulement recommandé

1. **Chercher avant de créer :** vérifiez si le partenaire existe
   déjà — cela évite les doublons. Les doublons existants peuvent être
   fusionnés ; l'historique suit.
2. Créez le contact avec nom, adresse et interlocuteurs.
3. Complétez les données de paiement et de facturation ainsi que les
   identifiants externes — ils pilotent la facturation et la remise
   comptable.
4. Reliez projets, sites et accords au fur et à mesure.

![Liste des clients avec numéros, coordonnées, taux horaires et nombre de projets](media/kunden/kundenliste.png)
*La liste des clients : données de base, taux horaire et projets liés par partenaire.*

## Exemple pratique

Un prestataire informatique crée « Müller GmbH » avec adresse de
facturation, délai de paiement et le numéro de débiteur du cabinet.
Quand le premier lot DATEV est créé plus tard, aucune pièce n'est
bloquée par des données manquantes.

## Erreurs fréquentes

- **Créer des doublons** faute d'avoir cherché — analyses et
  historique se fragmentent.
- **Supprimer des relations historiques :** désactivez ou archivez
  les contacts inutilisés ; pièces et temps restent traçables.
- **Modifier les données de facturation « au passage » :** les
  changements valent pour l'avenir ; les pièces déjà créées gardent
  volontairement leur état documenté.

## Effets et prochaines étapes

Les modifications de données de base ne valent que pour l'avenir —
les remises clôturées restent inchangées. Ensuite : créer les projets
du client, vérifier les données de facturation et utiliser l'import
CSV pour les gros volumes.
