---
title: "Clients & fournisseurs"
topic: contacts.manage
version: 14
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
   Un nouveau site reprend l’adresse, le pays et les coordonnées du client choisi comme proposition.

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

## Facture électronique

Dans la boîte de dialogue du client, les champs de la facture électronique se
trouvent dans la section **Facturation et affichage** :

- **Format e-facture par défaut** : présélection pour les nouvelles factures et
  l'import de fichiers de ce client, par exemple **XRechnung (XML)** ou
  **ZUGFeRD (PDF hybride)** ; **Aucun standard (PDF)** conserve le PDF.
- **Leitweg-ID / référence acheteur (BT-10)** : mention obligatoire de la
  XRechnung – la Leitweg-ID pour les administrations, sinon une référence
  imposée par le client.
- **Identifiant de participant Peppol** : identifiant du destinataire pour
  l'envoi via Peppol sous la forme schéma:identifiant, par exemple avec le
  numéro de TVA ou la Leitweg-ID ; vide signifie pas d'envoi Peppol à ce
  client.
- **Profil de destinataire de la facture électronique** : **Administration
  publique** n'envoie que le XML par e-mail, exige la Leitweg-ID, intègre les
  pièces et joint automatiquement le justificatif de prestations ;
  **Standard** vaut pour tous les autres clients, **Valeur de l’organisation**
  reprend le réglage de l'organisation.
- **Joindre le justificatif de prestations** : **Selon le profil de
  destinataire**, **Toujours** ou **Jamais** ; le justificatif est produit à
  l'émission à partir des temps facturés.
- **Plus grande facture électronique avec pièces (Mo)** : limite supérieure de
  la facture électronique avec ses pièces intégrées ; vide, la limite du profil
  s'applique (standard 15 Mo, administration 10 Mo).
- **Canal d’envoi en cas de facturation externe** : si un système de
  facturation gère le numéro et le contenu de la facture, soit ce système
  l'envoie, soit WorkDiary envoie la facture avec les justificatifs – désactivez
  alors l'envoi automatique dans le système de facturation. Vous ne voyez ce
  champ qu'avec le droit **Gérer la configuration financière**.

La manière dont les pièces entrent dans la facture électronique et les règles
pour les administrations sont décrites dans la rubrique d'aide **Factures &
pièces**.

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

## Factures et justificatifs dans le dossier

Outre les factures créées dans WorkDiary, la carte **Factures et justificatifs**
affiche les pièces des logiciels comptables connectés pour la période choisie
dans l’en-tête : Lexoffice, sevDesk, easybill, orgaMAX, Invoice Ninja,
Akaunting, JTL-Wawi et InvoicePlane pour les clients et les fournisseurs. Un
logiciel apparaît tant que son import des pièces est configuré
pour votre organisation ; la colonne « Source » le nomme. Les pièces sont
attribuées lors de l’import via le lien de contact, le numéro client ou le nom
du fournisseur ; si le lien manque, la carte vide l’indique. Lorsque le
logiciel fournit un PDF (factures et avoirs sevDesk, easybill, factures
orgaMAX), **PDF original** le charge directement. Les pièces entrantes de ces logiciels
peuvent aussi être affectées comme coûts de matériel du client et dans les
achats d’abonnements et de licences.

## Transférer les fournisseurs à la comptabilité

Lorsque WorkDiary pilote les données de base, les fournisseurs sont transférés
au logiciel comptable comme les clients : Lexoffice, sevDesk, easybill et
orgaMAX. Le transfert a lieu à la création, si le logiciel attribue le numéro
fournisseur selon les **Formats de numérotation**, et après une modification
d’un fournisseur déjà lié. easybill et orgaMAX peuvent attribuer le numéro
fournisseur ; sevDesk crée un contact de la catégorie « Fournisseur » sans
numéro fournisseur propre, le numéro de WorkDiary reste alors valable. orgaMAX
n’accepte les fournisseurs que si la capacité « Fournisseurs » est active dans
l’administration orgaMAX et que « WorkDiary pilote » est choisi ; sinon
orgaMAX pilote les fournisseurs et WorkDiary se contente de les associer.

## Données de base issues du logiciel comptable

Si la maîtrise des données de base est réglée sur « La comptabilité dirige »,
WorkDiary ne transfère aucun contact. Les clients et fournisseurs viennent alors
du logiciel : Lexoffice et orgaMAX par leur propre synchronisation des
contacts, sevDesk et easybill chaque heure selon ces règles :

- Un contact déjà lié reprend l’état du logiciel (nom, numéro de TVA, numéro
  fiscal ; pour easybill aussi l’adresse, l’e-mail, le téléphone et
  l’interlocuteur).
- Une correspondance sûre, par exemple par le numéro de TVA, est associée.
- Tout cas incertain apparaît comme cas à clarifier dans la **Boîte de
  rapprochement** ; rien n’est créé.

sevDesk indique le rôle par la catégorie du contact : « Client » et
« Fournisseur » sont repris, les partenaires, prospects et interlocuteurs
d’une entreprise ne le sont pas. easybill distingue par numéro client et
numéro fournisseur ; un contact avec les deux numéros est client et
fournisseur. Les contacts easybill archivés restent exclus.

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
