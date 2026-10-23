---
title: "Clients & fournisseurs"
topic: contacts.manage
version: 11
keywords:
    - Aperçu en tant que client
    - Mot de passe initial
    - Créer un accès
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
    - admin.number-formats
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
   Si, selon les **Formats de numérotation**, un logiciel de comptabilité
   attribue les numéros client, le champ est verrouillé : le client est
   transféré au logiciel à la création et reçoit son numéro.
   Saisissez l'interlocuteur avec civilité, titre, prénom, autres prénoms
   et nom ; chaque ligne de la liste des interlocuteurs a les mêmes champs.
   Les logiciels de comptabilité les reçoivent séparément.
3. Complétez les données de paiement et de facturation ainsi que les
   identifiants externes — ils pilotent la facturation et la remise
   comptable.
4. Reliez projets, sites et accords au fur et à mesure.

![Liste des clients avec numéros, coordonnées, taux horaires et nombre de projets](media/kunden/kundenliste.png)
*La liste des clients : données de base, taux horaire et projets liés par partenaire.*

**Administrations et organismes de prélèvement :** si vous enregistrez le
centre des impôts, les caisses d'assurance maladie, la caisse d'assurance
accidents du travail ou la commune comme fournisseurs pour que la
comptabilité puisse comptabiliser impôts et cotisations, cochez-y
**Masquer dans les analyses**. Les justificatifs sont conservés ; les
analyses fournisseurs laissent ces organismes de côté. Pour les clients, la
même case agit sur les rapports portant sur les clients et les projets.

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

**Créer un accès** crée un accès sans e-mail d'invitation, par exemple au téléphone ou en rendez-vous : après **Nom** et **E-mail**, la fiche client affiche une seule fois un **mot de passe initial** aléatoire. Remettez-le en personne ou par téléphone, jamais par e-mail ; le portail ne l'envoie pas et ne l'enregistre nulle part en clair. Jusqu'à la première connexion, l'accès affiche **Mot de passe initial en attente** ; à la première connexion, le contact doit définir son propre mot de passe. S'il l'a perdu, **Générer un mot de passe initial** en crée un nouveau (le mot de passe précédent cesse immédiatement d'être valable, toutes les sessions sont fermées). Les deux actions exigent une confirmation de votre mot de passe et sont journalisées.

**Voir le portail** ouvre le portail client dans un nouvel onglet tel que ce contact le voit – avec ses zones, documents et factures – et aide pour des questions comme « Où trouver … ? ». L'aperçu est une simple vue : les actions sont verrouillées, les pages de sécurité et de mot de passe ne sont pas accessibles, et il ne compte pas comme une connexion du contact. Il se termine avec **Terminer l'aperçu** dans la barre jaune ou dans la fiche client, au plus tard après 60 minutes, et est journalisé avec début et fin. Il exige le droit **Voir le portail client en tant que client** ; sans accès, pas d'aperçu : créez-en d'abord un.

**Visibilité du portail :** à côté des zones, vous choisissez ici la **période visible des temps** – **valeur par défaut de l'organisation** (réglage **Portail client** dans les paramètres de l'organisation, par défaut trois années civiles, année en cours comprise), un nombre fixe d'années ou **illimitée**. C'est une limite d'affichage dans le portail, pas une suppression. L'ouverture **Analyses** montre au client des indicateurs et des graphiques – uniquement issus des zones également ouvertes (temps au niveau de détail configuré, factures, compte de facturation, tickets) ; comme toute nouvelle ouverture, elle démarre « désactivée ».

**Retirer un accès :** un accès qui ne s'est **jamais connecté** (invitation en attente ou expirée, mot de passe initial en suspens) est supprimé définitivement avec **Supprimer l'accès** – aucun justificatif n'y est rattaché. Si le contact s'est déjà connecté, l'enregistrement reste : désactivez l'accès et choisissez **Retirer l'accès**. Le nom, l'adresse et les moyens de connexion disparaissent, l'adresse e-mail redevient disponible pour une nouvelle invitation, et les confirmations de photos, questions et demandes restent comme justificatifs (« Ancien accès au portail »). Les deux exigent une confirmation du mot de passe et sont journalisés chez le client ; les accès retirés n'apparaissent plus que dans la ligne récapitulative sous la liste. Le contrôle des délais de conservation (zone **Accès au portail**) propose les accès désactivés au retrait après un an.

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

## Fournisseur : données fiscales et autofacturation

Le fournisseur porte le **numéro de TVA** et le **numéro fiscal**. Si votre organisation facture elle-même les prestations d’un fournisseur, saisissez la date de l’**accord d’autofacturation** ; ce n’est qu’ensuite que des autofactures peuvent être émises. La page du fournisseur affiche l’accord et les dernières autofactures.

## Cockpit client et fournisseur

La fiche réunit ce que les autres modules savent du contact : pour les clients
les tickets ouverts, les contrats avec leur prochaine échéance, les accords,
les délais de garantie, les cautions, les enquêtes avec NPS, les domaines
ainsi que les abonnements et licences ; pour les fournisseurs les commandes,
les justificatifs obligatoires, les contrats et les échéances. Une section
n'apparaît que si le module est activé, que vous disposez du droit
correspondant et qu'il existe des entrées ; les chiffres correspondent à la
liste concernée. L'administration voit les commandes boutique d'Etsy et de
Billbee. Comme les clients, les fournisseurs disposent de champs personnalisés
et de documents.
