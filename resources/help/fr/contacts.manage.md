---
title: "Clients & fournisseurs"
topic: contacts.manage
version: 4
keywords:
    - fichier clients
    - base clients
    - données de base
    - créer un client
    - créer un fournisseur
    - débiteur
    - créancier
    - numéro de débiteur
    - fusionner les doublons
    - importer des clients
    - carnet d'adresses
    - partenaire commercial
    - CRM
    - portail client
    - accès au portail
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

**Communication :** consignez appels, e-mails et engagements sous forme de
note de communication sur le client ou le fournisseur. Les notes figurent sur
la page de détail et dans la liste centrale des notes ; une réponse à une
demande d'accès concernant un fournisseur les mentionne avec leur nombre et
leur période.

**Accès au portail :** dans la section **Accès au portail** de la fiche
client, vous invitez des interlocuteurs au portail client avec
**Inviter un accès** ; le contact définit lui-même son mot de passe via le
lien de l'invitation. Tant que l'invitation est en attente ou expirée,
**Renvoyer l'invitation** est disponible. Pour les accès actifs,
**Réinitialiser l’accès** réinitialise l'accès après confirmation : le mot de
passe précédent cesse immédiatement d'être valable, toutes les sessions sont
fermées et le contact reçoit une nouvelle invitation ; les méthodes à deux
facteurs configurées sont conservées. Si le contact a seulement oublié son mot
de passe, ce n'est pas nécessaire : il le réinitialise lui-même sur la page de
connexion du portail via **Mot de passe oublié ?**. **Désactiver** déconnecte
l'accès immédiatement et bloque la connexion, **Réactiver** annule cette
mesure. Les zones visibles pour un accès sont définies par la configuration du
portail du client.

Si un contact a perdu toutes ses méthodes à deux facteurs et ses codes de récupération, **Réinitialiser le second facteur** supprime toutes les méthodes après une confirmation du mot de passe et une demande de confirmation, et ferme toutes les sessions. Le contact en est informé par e-mail et se connecte ensuite avec son mot de passe ; si votre organisation exige l’authentification à deux facteurs, il la configure à nouveau à ce moment-là. Vérifiez auparavant son identité, par exemple en le rappelant.

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
