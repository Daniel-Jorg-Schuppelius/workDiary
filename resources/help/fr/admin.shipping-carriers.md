---
title: "Connexions d'expédition DHL, UPS et FedEx"
topic: admin.shipping-carriers
version: 2
keywords:
    - expédition
    - DHL
    - UPS
    - FedEx
    - étiquette d'expédition
    - imprimer une étiquette colis
    - étiquette de retour
    - numéro de suivi
    - transporteur de colis
    - portail clients professionnels
    - sandbox
    - transporteur
    - suivi des envois
    - annuler une expédition
audience:
    - admin
modules:
    - module.versand
related:
    - admin.integrations
    - admin.plugins
    - manufacturing.orders
    - claims.overview
    - admin.organization-settings
    - admin.operations
---

La page **Expédition & logistique** – dans le menu sous **Expédition** –
enregistre les identifiants des transporteurs DHL Paket, UPS et FedEx. Avec une
connexion active, vous créez directement dans WorkDiary des étiquettes
d'expédition pour les livraisons et des étiquettes de retour pour les retours,
et vous suivez vos envois. Il existe une connexion par transporteur et par organisation ; les mots de
passe et les clés sont enregistrés chiffrés.

## Prérequis

- Votre licence comprend le module Expédition et logistique.
- Le plugin du transporteur est activé sous **Plugins** : **DHL Paket**,
  **UPS** ou **FedEx**. L'entrée **Expédition** apparaît ensuite dans le menu
  système (icône d'engrenage **Système**), dans le groupe **Plugins**.
- Vous disposez d'un accès client professionnel auprès du transporteur :
  - **DHL :** utilisateur et mot de passe du portail clients professionnels
    DHL, une clé API activée par DHL (dhl-api-key) et le numéro de
    facturation. Pour les étiquettes de retour, en plus l'ID du destinataire
    des retours, que vous créez dans le portail clients professionnels.
  - **UPS :** ID client et secret client d'une application développeur UPS,
    ainsi que votre numéro de compte UPS (numéro d'expéditeur).
  - **FedEx :** ID client et secret client d'une application développeur
    FedEx, ainsi que votre numéro de compte FedEx.
- Pour UPS et FedEx, WorkDiary reprend l'adresse de l'expéditeur dans les
  paramètres de l'organisation, section **Facture électronique (XRechnung)** :
  **Nom de l'entreprise** (à défaut, le nom de l'organisation), **Rue et
  numéro**, **Code postal** et **Ville**. S'il manque l'une de ces
  informations, l'étiquette échoue.
- La page est réservée aux administrateurs de votre organisation.

## Créer ou modifier une connexion

Vous créez une nouvelle connexion dans le formulaire **Ajouter une connexion** :

1. **Transporteur** : DHL, UPS ou FEDEX.
2. **Désignation** : un nom sous lequel la connexion sera proposée lors de la
   création d'étiquettes, par exemple « DHL expédition entrepôt ».
3. **Utilisateur / ID client** et **Mot de passe / secret client**.
4. **Clé API (DHL uniquement : dhl-api-key)**.
5. **ID du destinataire des retours (DHL uniquement)** : nécessaire pour les
   étiquettes de retour DHL.
6. **Numéro de facturation / de compte** : pour DHL le numéro de facturation,
   pour UPS le numéro d'expéditeur, pour FedEx le numéro de compte.
7. **Sandbox / environnement de test** : se connecte à l'environnement de test
   du transporteur ; aucun envoi réel n'y est créé.
8. **Actif** et **Enregistrer**.

Pour une nouvelle connexion, l'utilisateur/ID client et le mot de passe/secret
client sont obligatoires, et pour DHL aussi la clé API. Si vous choisissez dans
ce formulaire un transporteur qui a déjà une connexion, WorkDiary refuse
l'enregistrement et affiche un avis – les connexions existantes se modifient
uniquement via **Modifier**.

Pour modifier, cliquez sur **Modifier** à côté de la connexion dans la liste
**Connexions existantes**. Le formulaire s'intitule alors **Modifier la
connexion …** avec le transporteur dans le titre, par exemple « Modifier la
connexion DHL » ; le transporteur ne peut pas être changé.

- **Désignation**, **Numéro de facturation / de compte**, **Sandbox /
  environnement de test** et **Actif** sont préremplis avec les valeurs
  enregistrées. Ce que vous modifiez ici s'applique après l'enregistrement.
- L'utilisateur, le mot de passe, la clé API et l'ID du destinataire des
  retours ne sont jamais affichés. Les champs laissés vides conservent la
  valeur enregistrée ; seule une nouvelle saisie la remplace.
- Un **Numéro de facturation / de compte** laissé vide conserve lui aussi la
  valeur enregistrée.
- **Annuler** quitte la modification sans enregistrer.

## Connexions existantes

La liste **Connexions existantes** affiche pour chaque connexion le
transporteur, la désignation, le **Mode** (**Sandbox** ou **Production**) et
le statut (**Actif** ou **Inactif**). **Modifier** ouvre la connexion dans le
formulaire. **Désactiver** désactive une connexion ; elle n'est alors plus
proposée, et les envois de ce transporteur ne sont plus vérifiés. Pour la
réactiver, ouvrez-la avec **Modifier**, cochez **Actif** et enregistrez.

## Créer des étiquettes

- **Étiquette d'expédition pour les livraisons :** sous **Ordres de
  fabrication**, la page de détail d'un ordre contient la section
  **Livraisons**. Pour une livraison avec client, choisissez la connexion,
  indiquez – si aucun colis n'est saisi – le poids en grammes et, en option,
  la longueur, la largeur et la hauteur en centimètres, puis cliquez sur
  **Expédier**. UPS et FedEx n'utilisent les dimensions que si les trois sont
  renseignées. Les colis saisis fournissent eux-mêmes poids et dimensions. Le
  destinataire est le client de la livraison. Ensuite, la livraison affiche le
  statut **Étiquette créée** avec le transporteur et le numéro de suivi. Il
  existe un ordre d'expédition par livraison ; un ordre annulé ne compte pas.
  Sur la livraison, **Télécharger l’étiquette** télécharge de nouveau
  l'étiquette, **Consulter le statut de l’envoi** récupère la situation
  actuelle et **Annuler l’expédition** annule l'envoi – détails dans l'aide sur
  les ordres de fabrication.
- **Étiquette de retour pour les retours :** dans les **Dossiers de
  réclamation**, choisissez pour un retour au statut **Annoncé** la
  connexion, indiquez le poids et cliquez sur **Créer l'étiquette de retour**.
  L'expéditeur est le client ; son adresse doit comporter rue, code postal et
  ville. **Télécharger l'étiquette** vous donne le fichier ; si les retours
  sont activés pour le client dans le portail client, il peut aussi y
  récupérer l'étiquette.
- **Autorisation :** les étiquettes d'expédition peuvent être créées par toute
  personne autorisée à modifier l'ordre de fabrication ; les étiquettes de
  retour par toute personne disposant du droit **Contrôler et stocker les
  retours**.

UPS fournit l'étiquette sous forme d'image (GIF), FedEx sous forme de PDF. Si
le transporteur refuse l'ordre, WorkDiary abandonne le brouillon, et vous
pouvez réessayer après correction.

## Suivi des envois

Dans le réglage standard, WorkDiary vérifie toutes les heures auprès du
transporteur les envois ouverts – statut **Étiquette créée**, **En transit** ou
**Problème de livraison**. Chaque envoi est interrogé au plus toutes les trois
heures et seulement jusqu'à 60 jours après sa création ; ensuite, il n'est plus
considéré comme suivable. La vérification reprend le statut et l'historique de
l'envoi jusqu'à ce qu'il soit **Livré**.

- Si un envoi passe à **Problème de livraison**, WorkDiary déclenche la
  notification **Problème de livraison d'une expédition**.
- L'heure de la dernière vérification s'affiche au survol du statut sur la
  livraison (**Dernière vérification : …**).
- La vérification passe uniquement par une connexion active. Si la requête
  auprès du transporteur échoue, elle compte comme les autres erreurs de
  connexion de cette connexion (voir « Problèmes fréquents »).

## Limites

- Une connexion par transporteur et par organisation.
- Par défaut, les envois DHL partent en DHL Paket national ; seul
  l'exploitant de l'installation peut régler un autre produit.
- Vous créez les documents douaniers pour les envois hors UE séparément sur la
  livraison (voir l'aide sur les ordres de fabrication).

## Problèmes fréquents

- **« Une nouvelle connexion requiert l'utilisateur/ID client et le mot de
  passe/secret client (DHL en plus : clé API). »** Complétez les identifiants
  manquants.
- **Aucune connexion à choisir :** il n'existe pas de connexion active, ou la
  livraison n'a pas de client ou possède déjà un ordre d'expédition.
- **« Une connexion existe déjà pour ce transporteur. Veuillez la modifier via
  « Modifier ». »** Vous avez choisi dans le formulaire **Ajouter une
  connexion** un transporteur déjà connecté. Ouvrez la connexion dans la liste
  avec **Modifier**.
- **« Aucune connexion active n'est configurée pour le transporteur
  sélectionné. »** La connexion a été désactivée entre-temps.
- **« Impossible d’annuler l’expédition : … »** ou **« Impossible de consulter
  le statut de l’envoi : … »** Le transporteur a refusé la demande ou n'était
  pas joignable, ou la connexion est inactive. Une fois l'envoi en transit,
  l'annulation n'est plus possible.
- **« Impossible de créer l'étiquette d'expédition : … »** Vérifiez les
  identifiants, le numéro de facturation ou de compte, l'option **Sandbox /
  environnement de test** et l'adresse du destinataire. Pour UPS et FedEx,
  l'adresse de l'expéditeur manque souvent dans les paramètres de
  l'organisation ; pour les étiquettes de retour DHL, l'ID du destinataire des
  retours.
- **« L'étiquette de retour nécessite l'adresse du client (rue, code postal,
  ville). »** Complétez l'adresse dans la fiche client.
- **Vérifier les identifiants :** vous lancez le contrôle de santé du
  transporteur sous **Plugins**. Si une connexion échoue, WorkDiary signale la
  connexion perturbée sous **Tâches d'exploitation**.
