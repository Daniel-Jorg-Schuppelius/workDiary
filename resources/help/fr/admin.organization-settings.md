---
title: "Organisation et paramètres"
topic: admin.organization-settings
version: 3
keywords:
    - paramètres de l'entreprise
    - réglages du locataire
    - paramètres de l'organisation
    - service cartographique
    - service météo
    - calendrier des jours fériés
    - jours fériés régionaux
    - mode maintenance
    - double authentification obligatoire
    - niveaux de relance
    - géocodage
    - calcul d'itinéraire
audience:
    - admin
related:
    - admin.tenants
    - admin.settings
    - admin.license
    - reports.arbzg-compliance
    - catalog.holidays
    - finance.dunning
    - invoices.manage
    - accounting.fixed-assets
    - account.ai-assistant
    - admin.notification-rules
    - dispatch.board
    - tours.manage
---

Dans la boîte de dialogue **Modifier l’organisation**, vous gérez les données
de base de votre organisation et tous les réglages qui s'appliquent à ses
membres : règles de temps de travail et niveaux d'approbation, valeurs par
défaut des factures et des relances, services de cartographie et de météo,
région des jours fériés et mode maintenance. Vous l'ouvrez depuis le menu
système (icône d'engrenage **Système** dans l'en-tête) sous **Organisation** →
**Organisation**. L'entrée est disponible pour les administrateurs et ouvre
toujours votre propre organisation ; l'exploitation de la plateforme accède à
la même boîte de dialogue depuis la liste **Organisations**.

## Fonctionnement des réglages

- **Portée :** chaque valeur s'applique à toute l'organisation. Lorsque des
  membres, des clients, des projets ou des sites peuvent avoir leurs propres
  valeurs, la section concernée l'indique ; leurs valeurs priment alors.
- **Valeurs par défaut :** de nombreux champs sont vides et affichent en gris
  « Par défaut … », par exemple « Par défaut 25 ». Un champ vide reprend la
  valeur par défaut du système : une valeur que l'exploitant a définie dans
  les paramètres système, sinon la valeur intégrée indiquée par le texte gris.
  Une valeur saisie ne s'applique qu'à votre organisation et prime sur toute
  valeur système.
- **Réinitialiser :** si vous videz un champ et enregistrez, votre valeur est
  supprimée et la valeur par défaut s'applique de nouveau.
- **Champs préremplis :** les sections sans texte gris, comme les limites de
  temps de travail ou les niveaux d'approbation, affichent la valeur en
  vigueur et l'enregistrent de nouveau au prochain enregistrement.
- **Enregistrer :** **Enregistrer** reprend toutes les sections et tous les
  onglets en une fois. Si une valeur sort de la plage autorisée, la boîte de
  dialogue le signale au champ concerné et n'enregistre rien.
- **Supprimer et désactiver :** la désactivation, l'export des données et la
  suppression définitive d'une organisation sont réservés à l'exploitation de
  la plateforme (voir « Organisations et tenants ») ; la boîte de dialogue
  n'affiche aucun bouton pour cela.

## Données de base

- **Nom** (obligatoire, 255 caractères au plus) : nom de l'organisation. Il
  sert aussi de nom d'entreprise de la facture électronique tant qu'aucun
  autre n'y est saisi.
- **Langue** (obligatoire) : langue de l'interface pour tous les membres qui
  n'ont pas choisi leur propre langue, et langue de leurs notifications et
  e-mails. Les factures, devis, relances et bons de livraison paraissent dans
  cette langue si le client n'a pas de langue de document propre.
- **Fuseau horaire** (obligatoire) : fuseau horaire d'affichage pour les
  membres sans fuseau propre. Il détermine aussi les limites de journée comme
  « aujourd'hui » et le début de la semaine.
- **Format de date** et **Format de l’heure** : valeur par défaut pour tous
  les membres qui n'ont pas choisi de format propre dans leur profil. La liste
  montre chaque format avec un exemple ; **— Par défaut —** reprend le format
  du système.
- **Pointages oubliés** : manière de compléter les pointages manquants –
  **Le salarié demande – les RH approuvent** (par défaut) ou **Le salarié peut
  compléter lui-même**. Ces ajouts sont toujours marqués « manuel » et restent
  visibles dans la boîte de correction.

## Plan et statut

- **Plan** : formule de l'organisation – **Gratuit**, **Pro** ou
  **Entreprise**. Il est seulement affiché ici : les modules activés
  découlent de la licence installée, et lors de l'installation d'une licence
  l'organisation reprend son plan. Seule l'exploitation de la plateforme peut
  le modifier, car le passage à un plan plus petit ouvre pour les modules
  supprimés un délai de grâce de 30 jours, après lequel un traitement
  nocturne efface les données des modules supprimables.
- **Active** : le verrouillage de l'organisation est lui aussi réservé à
  l'exploitation de la plateforme. Les membres d'une organisation verrouillée
  ne voient plus que « Cette organisation est désactivée. Veuillez contacter
  l’exploitant. »
- **Sécurité** – **Authentification à deux facteurs obligatoire pour tous
  les membres** : toute personne qui n'a pas encore configuré de second
  facteur est dirigée vers la configuration après la connexion et ne peut
  travailler qu'ensuite ; cela vaut aussi pour les accès au portail client.
  Tant que l'obligation existe, le dernier facteur ne peut pas être supprimé
  et l'authentification à deux facteurs ne peut pas être désactivée.

## Mode conformité

**Mode** détermine la sévérité des contrôles du temps de travail selon la loi
allemande sur le temps de travail (ArbZG) :

- **Désactivé** : aucun contrôle – ni dans la planification des services et
  des équipes, ni dans l'analyse ArbZG des temps saisis et de leurs cas à
  clarifier.
- **Avertir** (par défaut) : les infractions sont affichées, l'enregistrement
  a lieu malgré tout.
- **Bloquer** : un service planifié présentant une infraction grave ne peut
  pas être enregistré, sauf si la personne qui planifie passe outre le
  contrôle de façon délibérée dans la boîte de dialogue du service. Sont
  graves le chevauchement, un repos trop court, un temps de travail journalier
  dépassé et un service pendant un congé approuvé ; les autres règles ne font
  qu'avertir.

## Modèle de temps de travail

**Type de temps de travail par défaut** est le modèle de temps de travail de
tous les membres qui n'en ont pas de propre, ainsi que le préremplissage des
nouveaux modèles : **Horaire flexible** (par défaut), **Durée hebdomadaire
fixe**, **Par jour de la semaine** ou **Temps de travail en confiance**. Un
modèle propre à une personne prime.

## Limites de temps de travail

Les limites s'appliquent à la planification des services et des équipes et à
l'analyse ArbZG des temps saisis :

- **Heures max./jour** (1–24, par défaut 10) : temps de travail journalier
  hors pauses.
- **Repos min. (h)** (1–24, par défaut 11) : repos entre deux journées de
  travail ou deux services.
- **Heures max./semaine** (1–168, par défaut 48).
- **Jours consécutifs max.** (1–14, par défaut 6) : jours de travail
  consécutifs dans la planification des services.
- **Période de nuit à partir de (heure)** (20–23, par défaut 23) et
  **Période de nuit jusqu’à (heure)** (4–7, par défaut 6) : fenêtre de nuit
  pour le contrôle du travail de nuit. Selon l'ArbZG, elle va de 23 h à 6 h,
  dans les boulangeries et pâtisseries de 22 h à 5 h.
- **Tolérance de plage horaire (min.)** (0–240, par défaut 15) : un cas à
  clarifier n'apparaît que si les pointages dépassent la plage horaire du
  modèle de temps de travail de plus de ce nombre de minutes.
- **Feu de l'horaire flexible : jaune à partir de (min.)** (par défaut 1200,
  soit 20 heures) et **Feu de l'horaire flexible : rouge à partir de (min.)**
  (par défaut 2400, soit 40 heures) : coloration du solde d'horaire flexible
  dans le compte de temps de travail et sur le tableau de bord. Les heures en
  plus et en moins comptent de la même façon. Si la valeur rouge est
  inférieure à la valeur jaune, la valeur jaune s'applique aussi au rouge.

## Approbations

- **Étapes d'approbation des congés**, **Niveaux d'approbation des heures
  supplémentaires** et **Niveaux d'approbation des corrections de temps** :
  chacun **Une étape (une approbation)** (par défaut) ou **Deux étapes
  (principe des quatre yeux)** – une demande nécessite alors deux
  approbations.
- **Commercial : rôle responsable**, **Technique : rôle responsable** et
  **RH : rôle responsable** : les étapes d'approbation d'une négociation
  contractuelle apparaissent dans la boîte des approbations sous
  « Approbations » pour le rôle associé à leur type d'étape. Tous les rôles
  sauf Client peuvent être choisis. Vide, la valeur par défaut s'applique :
  **Par défaut (Comptabilité)**, **Par défaut (Chef d'équipe)** ou **Par
  défaut (Gestion du personnel)**. L'approbation directement depuis le
  dossier reste possible.
- **Inscrire immédiatement les absences demandées à titre provisoire (le
  refus les retire)** : les absences demandées prennent effet dans la
  planification dès avant l'approbation, marquées comme provisoires ; un
  refus les retire. Seules les absences approuvées sont toujours facturées et
  exportées.
- **Activer le tableau de présence (présence actuelle)** : débloque la page
  **Présence actuelle** (désactivé par défaut).

## Temps de conduite et de repos

**Appliquer les règles de temps de conduite (véhicules marqués « Appliquer les
règles de temps de conduite et de repos »)** (désactivé par défaut) contrôle
les trajets par rapport aux limites du règlement (CE) 561/2006 et de la FPersV
allemande : temps de conduite journalier et hebdomadaire, interruptions de
conduite ainsi que repos journalier et hebdomadaire. Seuls les trajets avec
des véhicules sur lesquels **Appliquer les règles de temps de conduite et de
repos** est aussi coché sont contrôlés – les deux interrupteurs doivent être
activés. Il ne s'agit pas d'un conseil juridique : l'entreprise détermine
quelles règles s'appliquent dans chaque cas.

## Règles actives

Ici, vous désactivez des contrôles individuels ; par défaut, tous sont
activés. Une règle désactivée n'est plus contrôlée, et le mode **Désactivé**
les désactive toutes. Les huit premières règles concernent la planification
des services :

- **Services qui se chevauchent** : deux services de la même personne se
  chevauchent.
- **Repos minimal** : le repos entre deux services est trop court.
- **Temps de travail journalier** et **Temps de travail hebdomadaire** : la
  limite est dépassée.
- **Jours consécutifs** : plus de jours de travail d'affilée qu'autorisé.
- **Conflit de congé** : le service tombe pendant un congé demandé ou
  approuvé.
- **Correspondance des qualifications** : il manque à la personne une
  qualification exigée par le besoin en effectif du service.
- **Comptabilisation du jour férié** : le service tombe sur un jour férié
  légal de la région des jours fériés (onglet **Région et jours fériés**) ou
  sur un jour férié propre géré sous **Jours fériés**.

Les quatre dernières contrôlent les pointages et créent des cas à clarifier
dans l'analyse ArbZG :

- **Pointage de sortie oublié** : une présence reste ouverte au-delà de la
  journée.
- **Pointage un jour de repos** : pointage un jour libre selon le modèle de
  temps de travail ou le planning de service.
- **Pointage pendant une absence** : pointage malgré une absence approuvée
  d'une journée entière, comme un congé ou une maladie.
- **Plage horaire (pointages)** : pointage hors de la plage horaire, au-delà
  de la tolérance.

## Paramètres avancés

La dernière section regroupe d'autres valeurs par défaut dans des onglets :
**Listes**, **Facturation**, **Téléversements**, **Limites de saisie**,
**Notifications**, **Interface**, **Routage et cartes**, **Trajet**,
**Région et jours fériés**, **Météo** et **Maintenance**. Outre les valeurs
de facturation, l'onglet **Facturation** contient aussi les relances, la
facture électronique, les immobilisations, l'expédition et la douane, le
paiement en ligne, les assistants IA, les conditions de location, le parc
automobile, les schémas de réclamations, les problèmes récurrents et l'import
des temps. La remarque « Laisser vide pour utiliser la valeur par défaut du
système. » vaut pour tous les onglets. Les sections suivantes suivent l'ordre
des onglets.

## Listes

Nombre d'éléments qu'une liste affiche par page, de 1 à 500 :
**Feuilles de temps**, **Plannings de service**, **Clients** (également
fournisseurs et clients tiers), **Tournées**, **Véhicules**, **Tags**,
**Archive** (chaque onglet de la page d'archive), **Notifications, tâches
d'exploitation, fenêtres de maintenance, signalements de problèmes** ainsi
que les trois listes de la boîte de télémaintenance (**Boîte de
télémaintenance : appareils non attribués**, **Boîte de télémaintenance :
appareils multi-clients**, **Boîte de télémaintenance : sessions par carte
d'appareil**). Le nombre d'éléments récents affichés sur le tableau de bord se
règle dans l'onglet **Interface**. La taille de la liste des organisations de
l'exploitation de la plateforme est un paramètre système sous **Paramètres
(registre)**.

## Facturation

- **Taux de TVA par défaut (%)** : vide, workDiary détermine le taux des
  factures nationales à partir des règles fiscales. Un taux saisi s'applique
  à toutes les factures nationales créées localement et prime sur les règles
  fiscales.
- **Devise par défaut (ISO-4217)** : devise par défaut de l'organisation ;
  les montants d'un document restent dans la devise du client.
- **Unité de temps pour les positions** (8 caractères au plus, par défaut h) :
  unité des positions de temps dans le transfert.
- **Prestation par défaut (article)** : fournit le libellé, l'unité, le texte
  standard et – si aucun taux n'est trouvé – le prix des positions de
  transfert. Les règles de facturation du projet priment. Sans articles dans
  le fichier articles, la liste reste vide.
- **Modèle : texte d’introduction du transfert** et **Modèle : remarque finale
  du transfert** (2000 caractères au plus chacun) : copiés dans le
  justificatif à la création d'un transfert et modifiables sur place.
  Variables : :customer, :from, :to, :channel. Sans modèle pour la remarque
  finale, le texte de facture du client s'applique.
- **Taux horaire par défaut (produit)** : s'applique lorsque ni la saisie, ni
  la condition client, ni le collaborateur, ni l'activité, ni le projet, ni le
  client ne définit de taux. Vide, ces temps restent à 0,00 €.
- **Taux horaire de calcul montage** : valorise le temps de montage d'un
  article dans la proposition de prix de vente ; vide, le taux horaire par
  défaut s'applique.
- **Incrément de facturation par défaut (minutes)** (1–1440) : arrondit le
  temps facturable à cet incrément lorsque ni le projet ni le client n'en
  définissent ; vide = à la minute près.
- **Écart de regroupement par défaut (minutes)** (0–1440) : les saisies
  séparées d'au plus cet écart sont regroupées en un bloc lors de la
  facturation ; vide = pas de regroupement.
- **Canal de facturation** : canal de facturation par défaut de
  l'organisation, par exemple **WorkDiary (local)** ou **Lexoffice pilote** ;
  les clients peuvent le remplacer individuellement. Vide, **— WorkDiary (par
  défaut) —** s'applique. Le champ n'apparaît qu'avec le droit « Gérer la
  configuration financière ».

La création des factures est décrite dans le thème « Factures & pièces ».

## Relances

Valeurs par défaut par niveau pour la relance individuelle et la série de
relances ; la procédure est décrite dans le thème « Relances ».

- Pour chacun des niveaux 1 à 3 : **Niveau 1 : carence (jours)** et ainsi de
  suite – au niveau 1, les jours de retard avant que le rappel de paiement
  soit échu, aux niveaux 2 et 3, les jours depuis la dernière relance (par
  défaut 7 chacun) ; **Niveau 1 : frais (EUR)** et ainsi de suite (par défaut
  0,00) ; **Niveau 1 : délai de paiement (jours)** et ainsi de suite (par
  défaut 14, 10 et 7 jours).
- **Calcul des intérêts de retard** : **Taux fixe** (par défaut) ou **Taux de
  base + points de pourcentage** – le taux de base selon le § 247 BGB est
  récupéré chaque mois auprès de la Bundesbank.
- **Majoration (points de pourcentage)** : uniquement en mode taux de base.
  Repère selon le § 288 BGB : 5 points envers les consommateurs, 9 entre
  professionnels ; votre entreprise fixe le montant.
- **Intérêts de retard (% p. a.)** : uniquement pour le taux fixe ; 0 = pas
  d'indication d'intérêts.

Les intérêts de retard n'apparaissent que dans la lettre de relance ; ils ne
sont pas comptabilisés.

## Facture électronique (XRechnung)

Données vendeur pour la sortie XRechnung (EN 16931) des factures créées
localement : **Nom de l'entreprise** (vide = nom de l'organisation), **Rue et
numéro**, **Code postal**, **Ville**, **Code pays (ISO 3166-1)**, **N° TVA
intracommunautaire**, **Numéro fiscal**, **Contact : nom**, **Contact :
e-mail** (adresse valide), **Contact : téléphone**, **IBAN**, **BIC** et
**Titulaire du compte**.

Trois champs agissent en outre sur toutes les factures créées localement :

- **Code pays (ISO 3166-1)** (deux lettres, par défaut DE) : pays du vendeur.
  Le calcul de la taxe s'en sert pour distinguer les factures nationales, UE
  et hors UE.
- **Délai de paiement (jours)** (0–365) : s'applique lorsque ni la facture ni
  le client n'ont de délai de paiement ; vide ou 0 = 14 jours.
- **Petite entreprise (§ 19 UStG)** : toutes les factures créées par workDiary
  n'indiquent pas de TVA et portent la mention « Pas de TVA conformément au
  § 19 UStG (régime des petites entreprises). » ; la XRechnung reçoit la
  catégorie de taxe E (exonérée). La case prime sur **Taux de TVA par défaut
  (%)** et l'autoliquidation.

## Comptabilité : principe des quatre yeux

L’option **Principe des quatre yeux** du groupe **Comptabilité** impose la validation par une seconde personne : la personne qui prépare une écriture ou une écriture directe (escompte, passage en perte, écriture d’attente, virement interne, soldes d’ouverture, acompte spécial) ne la valide pas elle-même ; celle qui constitue un lot de paiements SEPA ne le libère pas elle-même. Les écritures directes sont alors créées comme brouillons dans la **Boîte de saisie comptable** et ne prennent effet qu’après leur validation. Sans cette option, WorkDiary les valide immédiatement.

## Immobilisations : biens de faible valeur et pool

Seuils (nets) pour le registre des immobilisations. Les valeurs par défaut
suivent le § 6 al. 2/2a EStG (état 2026) ; vérifiez-les en cas de
modification légale.

- **Seuil bien de faible valeur** (par défaut 800) : seuil de
  l'amortissement immédiat des biens de faible valeur.
- **Pool à partir de (au-delà)** (par défaut 250), **Pool jusqu’à** (par
  défaut 1000) et **Durée du pool (ans)** (1–20, par défaut 5).
- **Hausse des prix pour la prévision de remplacement (% par an)** (0–50, par
  défaut 0).

Les détails figurent dans le thème « Registre des immobilisations et
amortissement ».

## Expédition et douane

**Numéro EORI** : numéro douanier de l'entreprise (code pays et jusqu'à 15
caractères, p. ex. DE1234567). Il figure comme donnée de l'expéditeur sur les
factures commerciales et pro forma des envois hors de l'UE.

## Paiement en ligne

- **Prestataire de paiement** : nécessaire uniquement si plusieurs
  prestataires sont actifs ; par défaut **Automatique (premier prestataire
  actif)**. Vous activez les prestataires (Stripe, Mollie ou SumUp) comme
  plugin avec leurs propres identifiants.
- **Lien de paiement sur la facture et dans l'e-mail** (activé par défaut) :
  le lien de paiement et le QR code figurent sur la facture et dans l'e-mail.
  Désactivé, le paiement en ligne reste possible dans le portail client.

## Assistants IA (MCP)

**Autoriser les assistants IA via MCP** (désactivé par défaut) : les
assistants IA comme Claude ou ChatGPT peuvent se connecter avec l'accord de
chaque utilisateur et lire ou créer des brouillons avec ses droits. Désactivé,
aucune nouvelle connexion n'est possible ; les connexions existantes
n'obtiennent aucun outil et ne peuvent pas être renouvelées. La connexion
elle-même est décrite dans « Connecter un assistant IA ».

## Conditions de location du matériel

- **Remise uniquement avec des conditions de location signées** : vous gérez
  les conditions de location comme accord client « Conditions de location
  (location de matériel) » avec version et signature.
- **Autoriser la réservation directe dans le portail client** : les clients
  réservent immédiatement et de façon ferme les appareils disponibles ouverts
  au portail ; la direction est informée.
- **Rayon autour du lieu d'intervention (m)** (50–50 000, par défaut 500) : si
  la position signalée d'un appareil loué est plus éloignée du site de la
  location, l'écart est signalé. Sans site, les géorepérages du client
  s'appliquent.

## Parc automobile

**Aucun nouveau trajet si un contrôle obligatoire est en retard** (désactivé
par défaut) : si le contrôle technique, la vérification de sécurité ou un
autre contrôle obligatoire de l'actif associé est en retard ou bloqué, aucun
trajet ne peut être saisi à partir d'aujourd'hui. Les trajets passés restent
enregistrables.

## Schémas de réclamations

À partir de combien de réclamations similaires une indication apparaît –
même lot, même article avec le même type de défaut ou la même cause, ou même
fournisseur : **Seuil (dossiers)** (2–50, par défaut 3) dans la **Fenêtre
(jours)** (7–365, par défaut 90).

## Problèmes récurrents

Cette alerte précoce repère les clients et les objets pour lesquels un nombre
remarquablement élevé de tickets d'assistance arrive pendant la période
choisie.

- **Tickets à partir de** (2–50, par défaut 3) : nombre minimal de tickets
  pour une alerte.
- **Fenêtre (jours)** (7–365, par défaut 90) : période jusqu'à aujourd'hui,
  mesurée d'après la date de signalement des tickets.

Comment workDiary compte :

- Tous les tickets avec un client comptent, quel que soit leur statut. Les
  tickets avec un objet comptent par client et par objet, les tickets sans
  objet par client.
- Si un client ou un objet atteint le seuil, l'alerte « Tickets récurrents :
  … » est créée avec le nombre, la période, un lien vers l'objet ou le client
  et la recommandation de clarifier la cause avec le client, de contrôler ou
  remplacer l'objet et d'envisager une instruction de travail ou une
  formation. Au plus les 20 cas comptant le plus de tickets sont affichés.
- Les alertes apparaissent sous **Rapports** → **Projets et clients** →
  **Problèmes et formation** dans la zone **Problèmes récurrents** (pour les
  administrateurs et les personnes ayant le droit « Voir les rapports ») et
  dans la tuile **Points d’attention** du tableau de bord.
- En outre, la notification « Alerte précoce des analyses » est envoyée une
  fois par client ou par objet aux rôles Chef d'équipe et Administrateur. Vous
  modifiez les destinataires et les canaux sous **Règles de notification**.

## Import des temps

**Affecter les temps aux projets par mot-clé** (activé par défaut) :
s'applique aux temps importés, par exemple depuis l'assistance à distance,
Toggl ou Kimai. Si le texte d'un temps importé contient le nom ou un mot-clé
d'un projet du même client, il y est imputé au lieu du projet par défaut ou de
la boîte d'affectation. Seules les correspondances univoques sont imputées.

## Téléversements

Limites de taille des téléversements en kilo-octets (1 à 1 048 576 Ko, soit
jusqu'à 1 Go) : **Import CSV** (par défaut 10 240 Ko, 10 Mo), **Pièce jointe
client** (10 240 Ko), **Pièces jointes (général)** (25 600 Ko, 25 Mo) et
**Données d'impression** (262 144 Ko, 256 Mo). Les fichiers plus volumineux
sont refusés lors du téléversement.

## Limites de saisie

Limites de caractères et de plage pour les champs de formulaire, chacune à
partir de 1 :

- **Présence** : **Note, caractères max** (par défaut 1000), **ID d'appareil,
  caractères max** (64) et **Pause, minutes max** (600).
- **Tags** : **Nom de tag, caractères max** (60).
- **Commentaires** : **Corps du commentaire, caractères max** (5000).
- **Plannings de service** : **Note, caractères max** (2000).

## Notifications

**Aperçu du message, caractères max** (20–500, par défaut 120) : nombre de
caractères du texte du message qu'affiche une notification push ; le reste
est coupé.

## Interface

- **Calendrier** – **Durée des créneaux en minutes** : grille de la vue
  hebdomadaire ; les rendez-vous sans fin reçoivent cette durée. Valeurs
  autorisées : 10, 15, 20, 30 ou 60 (par défaut 30).
- **Tableau de bord** – **Nombre d'éléments récents** (par défaut 5) : nombre
  d'éléments utilisés récemment affichés par le tableau de bord.

## Nominatim (géocodage)

Nominatim est un service de géocodage fondé sur OpenStreetMap : il convertit
une adresse en coordonnées. workDiary l'utilise dans le **Carnet de bord** :
lorsque vous quittez le champ **De (adresse)** ou **Vers (adresse)**,
workDiary recherche l'adresse, et l'adresse trouvée apparaît en info-bulle sur
le champ. L'adresse saisie est alors transmise au service configuré.

- **URL de base** (adresse complète, 255 caractères au plus) : adresse du
  service Nominatim. Vide, la valeur de l'exploitant s'applique. Si votre
  propre adresse se trouve dans un réseau interne, par exemple un serveur
  hébergé par vos soins, workDiary ne l'interroge que si l'exploitant l'a
  autorisé pour votre organisation.
- **E-mail de contact** : transmis à chaque requête. Les règles d'utilisation
  de Nominatim exigent que l'application qui interroge s'identifie avec une
  adresse de contact.
- **Requêtes par seconde** (1–50, par défaut 1) : workDiary attend en
  conséquence entre deux requêtes. Le service Nominatim public autorise au
  plus une requête par seconde ; des valeurs plus élevées ne sont prévues que
  pour votre propre serveur.
- Les résultats sont mis en cache par adresse de service (365 jours par
  défaut) ; la même adresse n'est pas interrogée de nouveau pendant cette
  période.
- Si le service est injoignable ou ne trouve rien, aucune info-bulle
  n'apparaît ; la saisie elle-même n'est pas affectée.

## OSRM (routage)

OSRM calcule des itinéraires sur le réseau routier. workDiary l'utilise

- lors de l'optimisation d'une tournée : ordre des arrêts selon les distances
  routières réelles, tracé de l'itinéraire sur la carte ainsi que distance et
  durée de trajet prévues, auxquelles s'ajoutent les temps passés aux arrêts ;
- pour les **Suggestions de temps morts** du **Centre de contrôle** : temps de
  trajet supplémentaire aller-retour pour une commande qui tiendrait dans un
  créneau libre.

Si OSRM est injoignable, workDiary poursuit avec les distances à vol
d'oiseau : les tournées restent planifiables, simplement sans tracé, et les
suggestions portent la mention **estimation approximative (à vol d'oiseau)**.

- **URL de base** (adresse complète, 255 caractères au plus) : adresse du
  serveur OSRM. Pour votre propre adresse dans un réseau interne, la même
  autorisation de l'exploitant s'applique que pour Nominatim.
- **Profil (p. ex. driving)** (32 caractères au plus, par défaut driving) :
  profil de déplacement du serveur. Les profils disponibles, par exemple pour
  le vélo ou la marche, dépendent du serveur OSRM.
- **Délai d'attente (secondes)** (1–120, par défaut 10) : durée pendant
  laquelle workDiary attend une réponse avant de revenir aux distances à vol
  d'oiseau.

## Tuiles de carte

Les tuiles de carte sont les fragments d'image qui composent les cartes de
workDiary, par exemple pour les **Tournées**, sur la carte du **Centre de
contrôle** et dans la gestion de crise. Le navigateur de chaque utilisateur
les charge directement depuis le serveur de tuiles configuré.

- **Modèle d'URL de tuile** (adresse complète, 255 caractères au plus) :
  adresse du serveur de tuiles avec les variables {z} pour le niveau de zoom
  ainsi que {x} et {y} pour la position de la tuile. Par défaut, il s'agit du
  serveur de tuiles d'OpenStreetMap. workDiary autorise automatiquement le
  navigateur à charger des images depuis ce serveur.
- **Zoom maximum** (1–22, par défaut 19) : agrandissement maximal des cartes.
  Choisissez au plus le niveau que fournit le serveur de tuiles.
- La mention de source en bas de la carte est fixée par la configuration de
  base de l'exploitant ; elle ne peut pas être modifiée ici.

## Facturation du déplacement

Dans l'onglet **Trajet**, vous décidez si les factures comprennent un
déplacement. Avec **Calculer automatiquement le déplacement** (désactivé par
défaut), workDiary ajoute à la facturation de projet ou de matériel d'un
client une position de déplacement pour chaque tournée comportant un arrêt
chez ce client ; la position porte la date de la tournée. Les tournées annulées et les déplacements
déjà facturés ne comptent pas.

- **Mode** : **Forfait** ou **Kilomètres**.
- **Texte de position** (50 caractères au plus, par défaut « Trajet »
  dans la langue de la facturation) : texte de la position de facture,
  complété par la date ou les kilomètres.
- **Forfait (net €)** : montant par déplacement en mode **Forfait** ; sans
  montant, aucune position n'est créée.
- **Taux (€/km)** : prix par kilomètre en mode **Kilomètres**.
- **Source des kilomètres** : **Toujours depuis le site de l’entreprise** –
  distance à vol d'oiseau du site de l'entreprise à l'adresse du client – ou
  **Selon la tournée (km réels)** – les kilomètres du carnet de bord pour ce
  client ce jour-là, sinon la distance prévue de la tournée.
- **Aller-retour (×2, site de l’entreprise uniquement)** : double la
  distance à vol d'oiseau.
- **Latitude du site de l’entreprise (lat)** et **Longitude du site de
  l’entreprise (lng)** : coordonnées du site de l'entreprise ; vides, le point
  de départ de la tournée s'applique.

Si les coordonnées du client manquent en mode kilomètres, aucune position
n'est créée. Pour certains clients, vous remplacez les valeurs dans la boîte
de dialogue du client sous **Déplacement (remplacement)**.

## Juridiction et jours fériés

**Région des jours fériés (pays / Land)** détermine quels jours fériés légaux
s'appliquent à votre organisation. Vous pouvez choisir l'Allemagne avec
**À l’échelle nationale (sans jours fériés régionaux)** et ses 16 Länder,
**Autriche (national)** ainsi que **Suisse (national)** et les 26 cantons.
Les jours fériés régionaux comme la Fête-Dieu ou le jour de la Réforme ne
s'appliquent que dans certains Länder – choisissez donc la région de votre
entreprise. Vide, la valeur par défaut du système s'applique ; la première
entrée de la liste la nomme « Par défaut … ».

La région agit partout où workDiary tient compte des jours fériés, entre
autres pour :

- les majorations pour jours fériés et les conditions client avec règle de
  jours fériés ;
- les jours ouvrés des congés et des maladies, le compte de congés et
  l'objectif d'horaire flexible ;
- l'analyse ArbZG, par exemple pour le travail les jours fériés, et la règle
  de planning **Comptabilisation du jour férié** ;
- les vues calendrier, vue hebdomadaire, planning de service, calendrier des
  absences et **Présence actuelle** ;
- les délais SLA de l'assistance et les échéances des déclarations fiscales,
  reportées au jour ouvré suivant ;
- les prix des jours fériés dans la location de matériel.

Vous gérez vos propres jours fériés ou jours de repos sous **Jours fériés** ;
ils s'appliquent en plus de la région. Un site peut s'en écarter sous
**Sites** dans le champ **Région des jours fériés** (par défaut **Règle des
jours fériés de l'organisation**) ; cela vaut pour les majorations des temps
saisis sur ce site.

## Récupération météo automatique

**Récupérer la météo automatiquement à la création d’un compte rendu**
(désactivé par défaut) : à la création d'un compte rendu, workDiary récupère
en arrière-plan un instantané météo pour le lieu et l'horaire du compte rendu
et le joint comme preuve.

- Le lieu correspond aux coordonnées du site concerné par le compte rendu,
  sinon à celles du client – y compris via le projet ou la commande du compte
  rendu. Sans coordonnées, rien ne se passe.
- Les projets peuvent s'en écarter : dans le projet, sous **Récupération
  météo automatique**, vous choisissez activé, désactivé ou **Hériter
  (réglage de l’organisation)**. Ce choix vaut aussi pour les sous-projets.
- **Service météo** : **Open-Meteo** (par défaut) fonctionne dans le monde
  entier et sans inscription. **Deutscher Wetterdienst (DWD)** fournit des
  données officielles de stations allemandes (licence CC BY 4.0, mention
  « Deutscher Wetterdienst »), uniquement pour des lieux en Allemagne avec
  une station à portée.
- **DWD : distance maximale de la station (km)** (1–200, par défaut 30) : si
  aucune station DWD active ne se trouve dans cette distance, aucun
  instantané n'est créé – mieux vaut aucune valeur qu'une valeur fausse.

## Alertes météo pour la planification

**Alertes météo pour les interventions planifiées** (activé par défaut) :
workDiary vérifie chaque heure la prévision journalière pour les
interventions des trois prochains jours, aujourd'hui compris, et signale le
dépassement d'un seuil.

- Sont contrôlées les commandes planifiées dans cette période, attribuées à
  quelqu'un ou planifiées, et ni terminées ni annulées. Les coordonnées
  proviennent de l'adresse de la commande, sinon du client ; sans
  coordonnées, aucun contrôle.
- Seul **Open-Meteo** fournit des prévisions. Si le DWD est choisi comme
  **Service météo**, aucune alerte n'est créée.
- Seuils (vide = valeur par défaut) :
  - **Pluie (mm/jour)** – par défaut 20 ; alerte à partir de ce cumul
    journalier.
  - **Rafales (km/h)** – par défaut 60.
  - **Gel dès minimum de (°C)** – par défaut 0 ; alerte lorsque la minimale
    atteint cette température ou descend en dessous.
  - **Chaleur dès maximum de (°C)** – par défaut 30.
- workDiary signale chaque dépassement exactement une fois par intervention,
  jour et seuil – par défaut à la personne attribuée et au rôle Chef
  d'équipe. Vous définissez les destinataires et les canaux sous **Règles de
  notification** pour l'événement « Alerte météo pour une intervention » ; le
  SMS est aussi possible pour cet événement. Si l'événement y est désactivé,
  workDiary ne récupère aucune prévision.

## Mode maintenance

Dans l'onglet **Maintenance**, vous verrouillez temporairement workDiary pour
votre organisation.

- **Activer le mode maintenance** : tous les membres qui ne sont pas
  administrateurs voient une page de maintenance au lieu de l'application ;
  la connexion et la déconnexion restent possibles. Les administrateurs
  continuent de travailler et voient en haut l'avis « Mode maintenance actif
  — les non-administrateurs voient actuellement une page de maintenance. »
  avec le lien **Paramètres** pour revenir à cette boîte de dialogue.
- **Message affiché sur la page de maintenance** (300 caractères au plus).
- **Fin prévue** (facultatif, dans votre heure locale) : après cette date, le
  mode maintenance se termine automatiquement ; l'avis l'affiche sous la forme
  « Jusqu'au : … », la page de maintenance sous la forme « Probablement de
  nouveau disponible : … ».
- **Suspendre aussi les entrées terminal/webhook** (désactivé par défaut) :
  sans cette case, les terminaux de pointage ainsi que les entrées de
  téléphonie et de localisation continuent pendant la maintenance.

L'activation et la désactivation du mode maintenance sont consignées dans le
journal d'audit.
