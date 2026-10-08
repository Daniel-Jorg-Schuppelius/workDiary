---
title: "Importer la liste d'appels FRITZ!Box"
topic: admin.fritzbox
version: 1
keywords:
    - FRITZ!Box
    - liste d'appels
    - enregistrer les appels
    - appels comme temps
    - rapport téléphonique
    - pointage téléphonique
    - pointer par appel
    - import CSV des appels
    - AVM
    - affecter un numéro
    - facturer le temps au téléphone
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - time-entries.edit
    - attendance.manage
    - contacts.manage
    - foreign-customers
---

La page **Import FRITZ!Box** reprend les appels de la liste d'appels d'une
FRITZ!Box sous forme de saisies de temps. WorkDiary enregistre lui-même les
appels des clients et clients finaux connus ; si un appel chevauche un temps
déjà enregistré pour le même client, par exemple une télémaintenance, il est
fusionné avec ce temps au lieu d'être facturé deux fois. Les numéros inconnus
sont regroupés dans la **Boîte de rapprochement**. En outre, les employés
peuvent pointer leur arrivée et leur départ en appelant l'un de vos propres
numéros.

WorkDiary ne se connecte pas à la FRITZ!Box pour cela. Il lit la liste
d'appels exportée – sous forme de fichier téléversé ou de rapport téléphonique
par e-mail. Aucun identifiant de la box n'est nécessaire.

## Prérequis

- Le plugin **FRITZ!Box-Anrufliste** est activé sous **Plugins**. L'entrée
  **Import FRITZ!Box** apparaît ensuite dans le menu système (icône
  d'engrenage **Système**), dans le groupe **Plugins**.
- Les numéros de téléphone de vos clients et clients finaux figurent dans
  leurs données de base (téléphone ou mobile). C'est ainsi que WorkDiary
  reconnaît l'appelant.
- La page est réservée aux administrateurs de votre organisation ; la **Boîte
  de rapprochement** aux personnes autorisées à gérer la facturation.

## Paramètres du plugin

Sous **Plugins**, ouvrez la boîte de dialogue **Configurer** de
**FRITZ!Box-Anrufliste** :

- **Enregistrer les appels comme facturables** (par défaut : activé) :
  désactivé, les appels importés ne sont jamais marqués comme facturables.
- **Enregistrer les temps pour l’ID utilisateur** : l'identifiant (ID) de
  l'utilisateur pour lequel les appels sont enregistrés. Vide, WorkDiary
  enregistre pour le propriétaire de l'organisation ou le premier
  utilisateur.
- **Durée minimale (minutes)** (par défaut : 2) : les appels plus courts sont
  ignorés.
- **Fenêtre de préavis (minutes)** (par défaut : 15) : si un appel se termine
  au plus ce nombre de minutes avant un temps enregistré du même client, il
  est fusionné avec ce temps.
- **Uniquement ses propres numéros** : liste séparée par des virgules de vos
  propres numéros dont les appels doivent être importés, par exemple
  uniquement la ligne principale. Vide, tout est importé. Saisissez les
  numéros exactement comme dans la colonne « Eigene Rufnummer » (numéro
  propre) de la liste d'appels.
- **Considérer le type 3 comme sortant** : uniquement pour les listes des
  anciennes versions de FRITZ!OS, qui exportent les appels sortants en type 3.
- **Rapprocher les contacts externes** (par défaut : activé) : les numéros
  inconnus sont aussi comparés aux annuaires de contacts connectés, comme
  Lexoffice et Microsoft 365.
- **Numéro de pointage : arrivée**, **Numéro de pointage : départ** et
  **Numéro de pointage : arrivée/départ** : vos propres numéros pour le
  pointage téléphonique (voir plus bas).

## Téléverser la liste d'appels

1. Exportez la liste d'appels dans la FRITZ!Box : FRITZ!Box → Téléphonie →
   Appels → Enregistrer (CSV).
2. Sur la page, choisissez le fichier dans la section **Téléverser la liste
   d'appels** (extension .csv ou .txt, 20 Mo au maximum) et cliquez sur
   **Importer**.
3. Un message résume le résultat : enregistrés, fusionnés, pointés, ouverts
   (boîte), ignorés, filtrés et verrouillés.

Vous pouvez téléverser la même liste une nouvelle fois sans risque : WorkDiary
ignore les appels déjà importés.

L'encadré **Rapprochement des contacts** indique quelles sources de contacts
externes sont actuellement connectées. Sans source externe, WorkDiary compare
toujours avec vos clients et clients finaux.

## Rapport téléphonique par e-mail

Au lieu de téléverser le fichier, la FRITZ!Box peut envoyer sa liste d'appels
sous forme de rapport téléphonique par e-mail. Configurez pour cela sous
**Réception d'e-mails** une boîte aux lettres qui reçoit ces e-mails et
activez-y **Boîte des rapports téléphoniques : transmettre les listes d'appels
FRITZ!Box (CSV) à l'import de la liste d'appels**. La réception d'e-mails
consulte les boîtes toutes les cinq minutes par défaut ; les listes d'appels
reconnues passent par le même import qu'un téléversement. Les rapports livrés
deux fois n'entraînent pas d'enregistrements en double. Lorsqu'une telle boîte
est connectée, le contrôle de santé du plugin indique « Prêt — réception des
rapports téléphoniques par e-mail connectée. »

## Ce qui arrive à chaque appel

- **Filtrés :** appels à numéro masqué, appels manqués et rejetés, appels
  passés par des numéros propres absents de **Uniquement ses propres
  numéros**, ainsi que les numéros ignorés dans la boîte.
- **Ignorés :** appels déjà importés et appels inférieurs à la durée minimale.
  Si vous baissez la durée minimale, un nouvel import rattrape ces appels.
- **Fusionnés :** pour un numéro connu, WorkDiary cherche un temps enregistré
  du même utilisateur pour le même client que l'appel chevauche ou qui
  commence au plus tard dans la fenêtre de préavis après l'appel. L'appel est
  joint à ce temps comme justificatif, et son début est avancé au début de
  l'appel.
- **Enregistrés :** s'il n'existe pas de tel temps, une saisie de temps propre
  est créée sur le projet par défaut du client ou du client final (créé au
  besoin). La description indique le sens, le nom et le numéro ; le caractère
  facturable suit le paramètre.
- **Verrouillés :** si l'appel tombe dans un mois clôturé, WorkDiary ne crée
  aucune saisie. Les temps déjà exportés ne sont jamais modifiés.
- **Ouverts (boîte) :** les numéros inconnus et ceux marqués comme partagés
  vont dans la **Boîte de rapprochement**.

Pour la reconnaissance, les numéros mémorisés ont priorité, suivis des données
de base ; si un numéro correspond à un client final, celui-ci l'emporte comme
cible plus précise. Si un numéro est déjà affecté à un client dans un annuaire
de contacts connecté, WorkDiary enregistre directement.

## Affecter les numéros inconnus

La section **Boîte de rapprochement** indique le nombre de groupes d'import
ouverts ; **Vers la boîte de réception** y mène. Les appels d'un même numéro y
figurent sous forme de groupe, souvent déjà avec un client proposé :

- Choisissez un client ou un client final et cliquez sur **Affecter et
  enregistrer**. Tous les appels du groupe sont enregistrés selon les mêmes
  règles qu'à l'import. Avec **Mémoriser durablement le numéro** (présélectionné),
  les futurs appels de ce numéro passent sans question.
- **Numéro partagé** est destiné aux numéros par lesquels plusieurs clients
  appellent, par exemple la hotline d'un prestataire. Les futurs appels de ce
  numéro arrivent un par un dans la boîte pour affectation et ne sont jamais
  enregistrés automatiquement.
- **Ignorer le numéro** écarte définitivement le numéro, par exemple pour des
  appels privés ; les futurs appels ne sont plus importés.
- **Ignorer le groupe** écarte uniquement les appels affichés. Ils ne reviennent
  pas, même lors d'un nouvel import ; les nouveaux appels du numéro
  réapparaissent.

## Pointage téléphonique

Voici comment les employés pointent par téléphone :

1. Dans les paramètres du plugin, saisissez un ou plusieurs de vos propres
   numéros comme **Numéro de pointage : arrivée**, **Numéro de pointage :
   départ** ou **Numéro de pointage : arrivée/départ** – exactement comme dans
   la liste d'appels. La section **Pointage téléphonique** indique ensuite les
   numéros de pointage actifs.
2. Dans la section **Pointage téléphonique**, attribuez à chaque employé son
   numéro : choisissez l'**Employé**, saisissez le **Numéro de téléphone** (par
   exemple +49 151 2345678) et cliquez sur **Affecter**. Sans indicatif de
   pays, l'Allemagne s'applique. Le tableau affiche toutes les affectations ;
   **Supprimer** en retire une.
3. L'employé appelle le numéro de pointage. Il n'est pas nécessaire de
   décrocher – le numéro de l'appelant sert de badge.
4. Lors du prochain import de la liste d'appels, l'appel devient un pointage
   d'arrivée ou de départ à l'heure de l'appel. Pour **arrivée/départ** : si un
   pointage est ouvert, c'est un départ, sinon une arrivée.

Limites : le pointage n'a lieu qu'à l'import, pas au moment de l'appel. Les
appels sortants, les numéros masqués et non affectés sont ignorés, de même
qu'un départ sans arrivée ouverte. La durée minimale ne s'applique pas ici. Les
appels vers un numéro de pointage ne sont jamais enregistrés comme appel
téléphonique.

## Problèmes fréquents

- **Fichier refusé :** si l'import signale qu'aucune liste d'appels FRITZ!Box
  n'a été reconnue (fichier vide ou ligne d'en-tête manquante), utilisez
  l'export CSV de la liste d'appels sans le modifier.
- **« Aucun utilisateur imputable dans l'organisation. »** ou un contrôle de
  santé indiquant que l'utilisateur par défaut configuré n'existe plus :
  vérifiez **Enregistrer les temps pour l’ID utilisateur** ou videz le champ.
- **Appels sortants absents :** si la liste provient d'un ancien micrologiciel,
  activez **Considérer le type 3 comme sortant**.
- **Presque tout est filtré :** vérifiez **Uniquement ses propres numéros** –
  l'écriture doit correspondre exactement à la liste d'appels.
- **Beaucoup de résultats verrouillés :** le mois est déjà clôturé ; les appels
  de cette période ne sont plus enregistrés.
- **Pointage manquant :** le numéro de l'employé est-il affecté et a-t-il été
  transmis lors de l'appel ? Le numéro de pointage des paramètres
  correspond-il à la liste d'appels ?
