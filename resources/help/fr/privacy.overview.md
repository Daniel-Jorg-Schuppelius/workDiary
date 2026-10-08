---
title: "Aperçu de la gestion de la protection des données"
topic: privacy.overview
version: 2
keywords:
    - RGPD
    - registre des traitements
    - sous-traitant
    - contrat de sous-traitance
    - mesures techniques et organisationnelles
    - droits des personnes
    - demande d'accès
    - violation de données
    - notification sous 72 heures
    - politique de conservation
    - legal hold
audience: []
modules:
    - module.datenschutz
related:
    - documents.manage
    - isms.overview
    - glossary.core
    - privacy.portal
---

Le module de protection des données couvre le registre des traitements
(art. 30 RGPD, versionné avec instantanés immuables à chaque
validation), les sous-traitants et contrats (art. 28), les demandes des
personnes concernées (art. 15–21, délai de **30 jours**, vérification
d'identité, décision documentée), les mesures techniques et
organisationnelles ainsi que les violations de données (délai de
72 heures ; la notification à l'autorité et la communication aux personnes
concernées (art. 34) sont consignées séparément). Les contenus des demandes sont stockés **chiffrés** avec une
clé propre à chaque dossier ; il n'existe **volontairement aucun
contournement admin** — les droits doivent être attribués explicitement.
Attention : après le délai de conservation, la destruction de la clé
(crypto-shredding) rend les contenus **irrécupérables**, et les versions
validées du registre ne sont plus modifiables. Les justificatifs se
gèrent dans le module **Documents**.

Le rapport d’accès (art. 15/20) peut aussi être créé pour les **membres du
club** si votre organisation utilise la gestion associative : données de base
avec représentants légaux, adresses et coordonnées bancaires, ainsi qu’un
aperçu des données du club (périodes d’adhésion, groupes, présences,
cotisations, dons, grades, performances et autres) avec un extrait par
domaine. La recherche trouve les membres par nom, e-mail ou numéro de membre.

Toutes les pages suivantes se trouvent dans la barre latérale sous
**Protection des données**. Pour les consulter, le droit de lecture du module
de protection des données suffit (exception : portail des personnes
concernées) ; les modifications exigent un droit propre à chaque domaine. Le
rôle **Protection des données** dispose de tous ces droits.

## Responsabilité conjointe

**Protection des données** → **Registres** → **Responsabilité conjointe**. Le
**Registre des accords de responsabilité conjointe** recense les accords de
responsabilité conjointe au sens de l'art. 26 RGPD. La liste affiche
**Titre**, **Partenaire**, **Statut** et **Éléments essentiels fournis**.

**Créer un nouvel accord de responsabilité conjointe** :

- **Partenaire (prestataire)** issu du registre des prestataires et **Titre**
  (obligatoires), éventuellement **Valable à partir du** et **Point de contact
  commun**.
- **Matrice des responsabilités** : pour **Obligations d'information (Art.
  13/14)**, **Droits des personnes concernées**, **Violations de données** et
  **Contact de l'autorité de contrôle**, vous définissez chaque fois qui est
  responsable : **Nous**, **Partenaire** ou **Conjoint** (par défaut).
- Case à cocher **Éléments essentiels de l'accord de responsabilité conjointe
  fournis aux personnes concernées**.
- En option, le **Document contractuel** (PDF, DOC ou DOCX, jusqu'à 20 Mo).

Un nouvel accord commence au statut **Brouillon**. Dans l'accord, vous
modifiez la matrice, le **Point de contact**, le statut (**Brouillon**,
**Active**, **Résilié**, **Expiré**) et **Éléments essentiels fournis**. Sous
**Traitements liés**, vous cochez les traitements concernés du registre et
enregistrez avec **Enregistrer les liens**. Le document contractuel se
télécharge via le lien dans les données clés.

**Autorisation :** consultation avec le droit de lecture ; création et
modification avec le droit relatif aux prestataires et contrats de
sous-traitance.

## Catalogue TOM

**Protection des données** → **Registres** → **Catalogue TOM**. Le catalogue
rassemble en un seul endroit les mesures techniques et organisationnelles
(art. 32 RGPD). La liste affiche **Mesure**, **Zone**, **Statut** et **Revue à
échéance** ; si la date de revue est dépassée, elle est mise en évidence en
rouge.

**Nouvelle mesure** : **Désignation**, **Domaine de mesures** (par exemple
**Contrôle d'accès physique**, **Contrôle de l'accès aux systèmes**, **Contrôle de l'accès aux données**, **Contrôle de
transmission**, **Contrôle de saisie**, **Contrôle de disponibilité**,
**Récupérabilité**, **Contrôle de séparation** ou **Gestion de la protection
des données**), **Description**, **Risques traités** et **Preuves
(politiques, procès-verbaux, certificats …)**. La mesure est créée en version
1 comme brouillon.

Dans la mesure :

- **Versions** : vous enregistrez les modifications via **Nouvelle version**
  avec description, risques traités et **Note de modification** ; les
  versions antérieures sont conservées. **Valider** fait d'une version la
  **Version en vigueur**.
- **Traitements attribués** : choisissez un traitement puis **Affecter**.
  Lorsqu'un traitement est validé, sa version fige aussi l'état des mesures
  attribuées.
- **Contrôles d'efficacité** : **Documenter le contrôle** avec **Résultat**
  (**Efficace**, **Écart** ou **Sans effet**), éventuellement **Mesure de
  suivi à échéance** et **Écart / mesure de suivi**. La prochaine revue est
  fixée à la date de la mesure de suivi, ou à un an plus tard sans date ;
  cette date apparaît dans la liste sous **Revue à échéance**.
- **Preuves** : déposez des fichiers avec **Téléverser un justificatif**, en
  option avec **Valable jusqu'au (facultatif)**. Les preuves expirées sont
  signalées ; l'analyse des écarts signale les preuves qui expirent bientôt
  ou ont expiré.

**Autorisation :** consultation avec le droit de lecture ; créer, versionner,
valider, affecter, contrôler et téléverser des preuves avec le droit relatif
au catalogue TOM.

## Analyse des écarts

**Protection des données** → **Incidents et contrôle** → **Analyse des
écarts**. L'analyse vérifie, à l'aide de règles, si des contrats, des
évaluations ou des preuves manquent ou arrivent à expiration :

- sous-traitants sans contrat de sous-traitance,
- contrats de sous-traitance qui expirent bientôt ou ont expiré (par défaut
  30 jours à l'avance),
- responsables conjoints sans accord de responsabilité conjointe,
- traitements nécessitant une AIPD sans AIPD achevée,
- traitements sans TOM attribuées,
- preuves TOM qui expirent bientôt ou ont expiré.

**Lancer l'analyse maintenant** démarre une exécution. En outre, l'analyse
s'exécute automatiquement une fois par jour dès que l'analyse des écarts a
été ouverte une première fois dans votre organisation. En haut, un feu
tricolore indique le nombre de constats par statut ; en dessous figurent les
constats – les ouverts d'abord – avec **Exigence**, **Statut**,
**Déclencheur** et **Référence** (lien vers le traitement, le contrat ou le
prestataire).

L'analyse attribue **Manquant** ou **Expire**. Sous **Décision**, vous
définissez manuellement **Présent**, **En cours d'examen**, **Non
applicable**, **Écart accepté** ou **Rouvert**, chaque fois avec
**Justification** et **OK**. Pour « Non applicable » et « Écart accepté », la
justification est obligatoire. Les exécutions ultérieures ne modifient plus un
constat décidé manuellement. Les écarts posés par l'analyse elle-même qui ne
se présentent plus passent à **Présent** lors de l'exécution suivante.

Dans le **Catalogue d'exigences** en bas de la page, vous définissez quels
contrôles sont exécutés : chaque exigence peut être renommée et désactivée
avec l'interrupteur ; les exigences désactivées sont ignorées. Les entrées
issues d'un profil sectoriel portent la mention **Profil sectoriel**.

**Autorisation :** consultation avec le droit de lecture ; lancer l'analyse,
décider et gérer le catalogue avec le droit relatif à l'analyse des écarts.

## Conservation et suppression

**Protection des données** → **Incidents et contrôle** → **Conservation et
suppression**. Le concept de suppression propose à la suppression les données
dont la durée de conservation est expirée ; rien n'est supprimé ni anonymisé
sans confirmation en deux étapes. En haut figure le ressort juridique de votre
organisation (par exemple DE), qui détermine les délais.

- **Délais par domaine** : pour chaque domaine de données – par exemple le
  journal d'audit, les candidatures ou la plateforme d'apprentissage –
  l'**Échéance** en années ou en jours et la **Base juridique**. Les domaines
  portant la mention « inscription au registre uniquement, sans scan » ne
  documentent que le délai ; aucune proposition n'est créée pour eux.
- **Scanner maintenant** recherche les enregistrements dont le délai est
  expiré et crée des **Propositions de suppression**. Le scan s'exécute aussi
  automatiquement à intervalles réguliers (par défaut chaque semaine). Les
  enregistrements sous gel juridique et les exceptions métier ne reçoivent
  pas de proposition.
- Chaque proposition affiche **Zone**, **Enregistrement**, **Délai expiré
  depuis**, **Justification** et **Statut**.

La suppression se fait en deux étapes : d'abord **Confirmer** (statut
**confirmé**) ou **Refuser** (**refusé**), puis, pour les propositions
confirmées, **Supprimer définitivement** – individuellement ou par domaine
avec **Supprimer les confirmés dans …**. Selon le domaine, l'enregistrement
est supprimé ou anonymisé. Si un gel juridique a été posé entre-temps, la
confirmation et la suppression sont refusées ; lors de la suppression groupée,
ces enregistrements sont ignorés et comptés dans le message. Chaque décision
est journalisée.

**Autorisation :** consultation avec le droit de lecture ; scanner et décider
avec le droit relatif à l'analyse des écarts.

## Gel juridique

**Protection des données** → **Incidents et contrôle** → **Gel juridique**. Un
gel juridique est une mention de blocage pour une procédure en cours, à
l'initiative d'une personne concernée ou contentieuse : tant qu'il est actif,
rien n'est supprimé ni anonymisé concernant la personne ou le client.

La liste affiche d'abord les gels actifs, avec **Concerné** (nom, personne ou
client), **Numéro de dossier**, **Motif** (lisible uniquement par les
personnes disposant du droit de décision), **Posé** (date et auteur) et
**Statut** (**actif** ou « levé le … »).

**Poser un gel juridique** : sous **Type**, choisissez **Personne** ou
**Client**, puis exactement une personne de l'organisation ou un client ; en
option un **Numéro de dossier**. Le **Motif** est obligatoire (au moins 10
caractères) et enregistré chiffré.

Tant que le gel est actif, aucune proposition de suppression n'est créée ; les
suppressions confirmées, l'anonymisation et la suppression de comptes ou de
clients sont refusées, et les points de localisation bruts de la personne sont
conservés. Lors d'une fusion de clients, le gel passe au client cible.

**Lever le gel juridique** exige un **Motif de la levée** (au moins 10
caractères). Ensuite, le concept de suppression et les nettoyages
s'appliquent à nouveau ; le motif est conservé comme preuve. La pose et la
levée figurent dans le journal de la personne ou du client.

**Autorisation :** consultation avec le droit de lecture ; poser et lever avec
le droit relatif à l'analyse des écarts – qui décide des suppressions les
bloque aussi.

## Portail des personnes concernées

**Protection des données** → **Incidents et contrôle** → **Portail des
personnes concernées** (titre de la page **Gérer le portail des personnes
concernées**). Vous configurez ici le formulaire public par lequel les
personnes concernées présentent leurs demandes ; le sujet consacré au portail
d'accès décrit le déroulement pour la personne.

- Tant qu'aucun portail n'existe, la page affiche une mention ; le premier
  **Enregistrer** crée le portail avec un lien aléatoire.
- **Lien public** : vous publiez ce lien dans votre politique de
  confidentialité ; il ne peut pas être déduit du nom de l'organisation. À
  côté, vous voyez si le portail est **actif** ou **inactif**. **Renouveler le
  lien** crée un nouveau lien après une demande de confirmation – les liens
  déjà publiés deviennent invalides.
- **Paramètres** : **Portail actif (accessible publiquement)** – désactivé au
  départ –, **Autoriser les pièces jointes**, **Texte d’introduction
  (facultatif)** et **Langue par défaut (facultatif, p. ex. fr)**.

Les demandes reçues apparaissent comme dossier sous **Demandes des personnes
concernées**. Elles y sont marquées comme entrée par le portail ; les
indications d'identité valent comme auto-déclaration non vérifiée.

**Autorisation :** un droit propre pour gérer le portail des personnes
concernées ; sans ce droit, l'entrée de menu n'apparaît pas.
