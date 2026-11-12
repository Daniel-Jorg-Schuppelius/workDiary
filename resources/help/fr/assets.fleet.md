---
title: "Équipements & parc de véhicules"
topic: assets.fleet
version: 9
keywords:
    - gestion de flotte
    - gestion des véhicules
    - inventaire
    - matériel
    - prêt de matériel
    - sortie de matériel
    - retour de matériel
    - carnet de carburant
    - journal de recharge
    - entretien
    - signaler un défaut
    - cycle de vie
audience: []
modules:
    - module.fuhrpark
related:
    - documents.manage
    - travel-expenses.manage
    - reports.overview
---

Les actifs et les véhicules représentent des objets d'exploitation avec
leur statut, leurs responsabilités, leurs documents et leurs
informations de maintenance. Les journaux de carburant et de recharge
complètent l'historique de consommation.

Saisissez les données de base et les identifiants uniques, associez un
site ou des responsables et tenez à jour les intervalles de maintenance
ainsi que les documents pertinents. Les changements de statut doivent
refléter le cycle de vie réel.

Avant une suppression ou une mise au rebut, vérifiez si des
maintenances, opérations, trajets ou documents ouverts y sont liés. Un
historique critique doit être archivé et ne doit pas être perdu par
écrasement.

## Catégories

Le champ « Catégorie » propose les catégories informatiques générales et
celles de votre organisation. Les profils sectoriels apportent des catégories
adaptées, par exemple caméra d’inspection et sertisseuse pour le génie
climatique ou oxymètre de pouls et armoire à stupéfiants pour les soins. Les
administrateurs créent leurs propres catégories sous Classifications, dans le
domaine « Catégories d’actifs ». Une catégorie désactivée n’est plus proposée
pour les nouveaux actifs mais reste sur ceux qui la portent déjà.

## Attribution et restitution

Le panneau « Attribution / restitution » de la page de détail de
l'actif permet d'attribuer un appareil à une personne ou à une équipe,
éventuellement avec une référence à une commande et une date de
restitution prévue. Chaque actif ne peut avoir qu'une seule attribution
ouverte ; un actif déjà attribué ou bloqué pour cause de défaut ne peut
pas être attribué à nouveau. Lors de la restitution, l'actif redevient
disponible. Si une attribution dépasse la date de restitution prévue,
un avertissement de retard s'affiche et le scanner d'échéances avertit
la personne qui l'a emprunté ou le responsable d'équipe.

## Défauts et blocages

Le panneau « Défauts / blocages » permet de saisir des défauts avec un
niveau de gravité. Si « Verrouiller l'actif (aucun retrait possible) »
est coché, le défaut ouvert bloque toute nouvelle attribution jusqu'à
ce qu'il soit résolu ou sorti. Une note de résolution est obligatoire
pour résoudre ou sortir un défaut.

## Plans de maintenance

Dans la carte **Plans de maintenance**, **Créer un plan** enregistre des
maintenances récurrentes : **Désignation**, **Interval** avec **Valeur**,
**Tolérance (jours)** et **Première échéance**. Pour les jours, semaines
et mois, **Terminé** calcule l'échéance suivante à partir de
l'intervalle ; pour les heures de fonctionnement et les kilomètres, vous
la saisissez vous-même. **Mettre en pause** retire le plan du contrôle
des échéances.

Qui peut voir les contrats SLA rattache le plan à un **Contrat SLA** –
un contrat du client de l'actif ou un contrat sans client – et coche
**Obligation contractuelle** lorsque le plan représente une échéance
obligatoire de ce contrat. Le plan apparaît alors sur le contrat sous
**Maintenances contractuelles obligatoires** ; la marque figure aussi sur
l'actif, dans le dossier d'objet et dans le portail client. Si le
helpdesk est activé, **À l'échéance** définit ce que fait le contrôle
quotidien des échéances : **Créer un ticket de service** crée
exactement un ticket par échéance avec le contrat choisi, **Simple avis
(aucune fiche)** note seulement l'échéance.

## Contrat SLA de l'actif

Qui peut voir les contrats SLA choisit un **Contrat SLA** pour l'actif sous
**Contrats & SLA** dans le formulaire **Modifier** – un contrat du client
enregistré ou un contrat sans client. Les OLA, les contrats liés à un projet et
les contrats inactifs ne sont pas proposés. Le contrat s'applique aux tickets de
service de cet actif avant les contrats du projet et du client (thème « SLA,
contrats & niveaux de service ») ; il figure dans l'en-tête de la page de
détail et dans le dossier d'objet comme **Attribution directe**. Sans le droit
de voir les contrats SLA, le champ n'apparaît pas et le rattachement reste
inchangé à l'enregistrement.

## Dossier d'objet (cycle de vie)

Le « Dossier d'objet » regroupe l'ensemble du cycle de vie d'un actif
dans une vue cohérente et imprimable : données de base, site et local,
statut de cycle de vie dérivé (en service, remplacé ou désaffecté),
mise en service, mise hors service et garantie. En dessous figurent
les maintenances, les attributions et restitutions, les défauts et
blocages, les commandes liées, les procès-verbaux, les consommations de
matériel, les points ouverts et les pièces jointes ainsi que
l'historique complet du cycle de vie.

Le dossier est accessible via le bouton « Dossier du site » de la page
de détail de l'actif et peut être sorti sous forme de document via la
fonction d'impression du navigateur (l'ajout de « ?print=1 » ouvre
directement la boîte de dialogue d'impression). Le statut de cycle de
vie est dérivé du statut, de la mise hors service et de la garantie –
il n'y a pas de saisie séparée.

## Mise hors service

**Mettre hors service**, sur la page de détail de la ressource, retire
définitivement une ressource de l'exploitation. La date et le motif sont
obligatoires ; ils figurent ensuite dans l'en-tête de la page de détail et
dans le dossier d'objet. Reprenez d'abord une ressource encore remise.
L'opération met en pause les plans de maintenance, suspend les obligations de
contrôle et annule les rendez-vous de contrôle ouverts. La ressource ne peut
ensuite plus être remise, louée, réservée ni utilisée dans une procédure, et
elle n'apparaît plus dans les listes de sélection de la location et des
ordres d'impression. Le dossier avec tous les justificatifs est conservé.
L'opération est irréversible ; le statut « Hors service » n'est pas proposé
dans le formulaire « Modifier ».

**Autorisation :** **Mettre hors service des ressources/objets**.

## Changement de propriétaire

**Changer de propriétaire**, sur la page de détail de la ressource,
enregistre un changement de propriété (organisation, client, externe) et de
client, avec une note si vous le souhaitez. Chaque changement figure dans le
dossier d'objet sous « Historique des changements de propriétaire » avec la
date, l'origine, la destination, la personne et la note ; les entrées ne
peuvent pas être modifiées. Choisir un autre client dans le formulaire
« Modifier » constitue le même changement : il est enregistré avec la note
saisie à cet endroit.

Si le client change, les rattachements à des contrats de l'ancien client sont
supprimés – sur l'actif et sur ses plans de maintenance, avec l'**Obligation
contractuelle** ; les contrats sans client restent. Choisissez un contrat du
nouveau client après l'enregistrement, dans le formulaire rouvert.

**Autorisation :** **Transférer la propriété des ressources** – également
pour le changement de client dans le formulaire « Modifier ».

## Statut, blocage et état

Les statuts **Verrouillé** et **En réparation** bloquent une ressource
partout où un blocage issu des contrôles ou de la location s'applique aussi :
remise, location, réservation de véhicule, ordre d'impression et démarrage
d'une procédure sur la ressource ou sur un ordre concernant cette ressource.
Un modèle de procédure qui autorise expressément les ressources bloquées (la
réparation, par exemple) démarre malgré tout. Le statut crée pour cela un
blocage avec le motif « Bloqué manuellement » ou « Maintenance ». Il prend
fin lorsque vous réinitialisez le statut ou levez le blocage sous **Moyen de
contrôle** ; la ressource est alors de nouveau **Active**. Les dérogations
temporaires s'appliquent à ce blocage comme à tout autre.

L'**état** affiché à côté du statut est déduit et n'est pas saisi à la main :
**Critique** en cas de blocage actif ou de défaut bloquant, **Dégradé** en
cas de défaut ouvert ou de maintenance ou contrôle en retard, sinon **En
ordre**. Il est mis à jour à chaque modification et pendant la nuit.

Si vous modifiez l'emplacement en texte libre ou la pièce, le changement
figure dans la chronologie sous « Site modifié » avec l'ancien et le
nouvel emplacement.

## Reprise avec état

**Retirer** ouvre une boîte de dialogue pour l'**État au retour** et la
**Note**. Les deux figurent ensuite dans le tableau d'historique du panneau
« Attribution / restitution », à côté de l'état à la remise, ainsi que dans
le dossier d'objet.

## Cockpit

Sous les sections de la page de détail, les modules affichent ce qui existe pour l'actif : **tickets de service** ouverts, **leasing et financement** (contrats auxquels l'actif est rattaché), **réclamations** ouvertes, **délais de garantie** ouverts issus des commandes et procès-verbaux de l'actif, **points ouverts** et **procédures**. Un panneau n’apparaît que si le module est activé, que vous avez le droit sur la liste correspondante et qu’il existe des entrées ; un clic ouvre l’entrée.

**Remettre en service :** vous remettez en service un équipement mis hors service avec l’action du même nom — avec un motif et une entrée au journal d’audit, avec le même droit que la mise hors service. Les plans de maintenance suspendus et les obligations de contrôle en sommeil restent désactivés et sont réactivés un par un. Un équipement éliminé via un ordre d’élimination reste hors service ; le formulaire de modification ne change pas ce statut.

## Nomenclature

La **nomenclature** d’un appareil recense ce qui est monté dans cet exemplaire :
article ou texte libre, quantité, emplacement, numéro de série, date de montage
et intervalle de remplacement. Remplacer crée une nouvelle pièce et laisse
l’ancienne dans l’historique avec sa date de démontage ; les pièces d’usure
échues apparaissent comme suggestion. Sous **Matériel des interventions**
figurent les consommations de matériel affectées à cet appareil sur une feuille
de temps. **Reprendre** inscrit un matériel dans la nomenclature avec le jour de
l’intervention comme date de montage – avec l’article s’il en a un ; chaque
consommation ne peut être reprise qu’une fois.
