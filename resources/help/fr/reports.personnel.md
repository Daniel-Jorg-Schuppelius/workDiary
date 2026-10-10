---
title: "Personnel : congés, maladie, qualifications, sécurité"
topic: reports.personnel
version: 6
keywords:
    - absentéisme
    - congés restants
    - rapport des congés
    - jours de maladie
    - maintien du salaire
    - rechute de maladie
    - certificat médical
    - matrice des qualifications
    - certificats arrivant à échéance
    - analyser les accidents
    - presqu'accident
    - besoins de formation
    - alertes précoces
audience:
    - admin
    - geschaeftsfuehrung
    - personalverwaltung
    - teamleitung
related:
    - reports.overview
    - absences.manage
    - reports.absence-calendar
    - time-accounts.flex
    - catalog.qualifications
    - safety.overview
    - learning.overview
---

Ces rapports synthétisent des données personnelles : congés et horaire
flexible, maladie et maintien du salaire, qualifications avec dates
d'expiration, événements de sécurité ainsi que problèmes récurrents et besoins
de formation. Vous les trouvez sous **Rapports** → **Équipe** (**Congés et
flex**, **Maladies**, **Qualifications**, **Sécurité au travail**) et sous
**Rapports** → **Projets et clients** (**Problèmes et formation**). Il s'agit
de données particulièrement sensibles : ne transmettez les chiffres qu'aux
personnes qui en ont besoin pour leur mission. Les corrections se font sur la
demande de congé, l'arrêt maladie, la qualification de la personne ou
l'événement de sécurité.

## Congés et flex

**Rapports** → **Équipe** → **Congés et flex** montre les absences et l'horaire
flexible par personne pour la période choisie dans l'en-tête. Ce sont des
jours ouvrés qui sont comptés : du lundi au vendredi hors jours fériés, limités
à la période.

Colonnes par personne :

- **Congé**, **Spécial** et **Non payé** : jours ouvrés issus des demandes
  approuvées du type concerné.
- **Maladie** : jours ouvrés issus des arrêts maladie ; les arrêts annulés ne
  comptent pas.
- **En attente** : jours ouvrés issus des demandes non encore décidées, mis en
  évidence en couleur.
- **Droit** et **Restant** avec l'année : le compte de congés de l'année dans
  laquelle la période se termine. Le droit comprend le droit de base, les
  congés supplémentaires et le report utilisable ; le restant ne déduit que les
  jours approuvés et passe au rouge s'il est négatif. Sans droit enregistré,
  « – » s'affiche.
- **Flex Δ** : variation du solde d'horaire flexible sur les mois de la période
  (réel moins cible selon les relevés mensuels du compte de temps de travail).
- **Solde flexible** : le dernier relevé mensuel jusqu'à la fin de la période.

Tuiles : **Employés**, **Congé (jours ouvrés)** avec les jours en attente,
**Maladie**, **Spécial / non payé** et **Variation flexible Σ**. Le graphique
**Jours d'absence par mois par type** empile congé, maladie, spécial et non
payé ; selon la longueur de la période, il est établi par jour, par semaine ou
par trimestre. **Congés restants par collaborateur (top 15)** montre les soldes
restants les plus élevés.

Les filtres **Zone** (**Uniquement les miens** ou **Toute l’équipe** pour
toutes les personnes de l'organisation), **Employé**, **Équipe** et **Statut**
sont visibles pour les administrateurs et les personnes disposant du droit
**Voir toutes les demandes de congés** ; toutes les autres ne voient que leur
propre ligne. Avec **Statut**, seules les demandes **En attente** ou
**Approuvé** sont comptées. Export : **PDF** avec graphique, **CSV** et
**Excel**.

La colonne **Maladie**, sa vignette et sa part dans le graphique n'affichent les valeurs des autres personnes que si vous disposez en plus du droit **Voir les arrêts maladie** ; sinon, elles sont absentes de l'affichage et de l'export.

## Maladies

**Rapports** → **Équipe** → **Maladies** ouvre le **Rapport de maladie** pour la
période choisie dans l'en-tête. Les arrêts maladie annulés ne comptent pas.

Colonnes par personne ayant des arrêts maladie dans la période :

- **Jours ouvrés** et **Jours cal.** : jours de maladie dans la période, une
  fois sans week-ends ni jours fériés, une fois en jours calendaires.
- **Cas** : nombre d'arrêts maladie ; **Suite** : dont certificats de
  prolongation.
- **Avec certificat médical** : arrêts maladie avec un certificat téléversé,
  rapportés à l'ensemble des cas.
- **Maintien du salaire** : jours consommés par rapport au droit, sous forme de
  barre – verte, orange à partir de 75 %, rouge en cas d'épuisement.
- **Statut** : **Épuisé** avec la date à laquelle le droit prend fin, sinon les
  jours libres restants ou **OK**. Sous le nom, **Chaîne depuis** indique le
  début de la chaîne de maladie en cours.

Calcul du maintien du salaire :

- Dans le réglage standard, le droit est de six semaines, soit 42 jours
  calendaires d'incapacité de travail. Seuls les jours de maladie eux-mêmes
  comptent – pour une maladie en cours jusqu'à aujourd'hui ; les jours
  travaillés entre deux arrêts maladie ne comptent jamais.
- Les arrêts maladie qui se chevauchent, se suivent sans interruption ou sont
  liés comme certificat de prolongation forment un seul cas de maladie. Cela
  vaut aussi lorsqu'une nouvelle maladie survient pendant une maladie en cours.
- Une nouvelle maladie qui ne commence qu'après des jours travaillés ouvre un
  droit complet.
- Si la caisse d'assurance maladie confirme la même maladie (rechute), l'arrêt
  antérieur est choisi sur le nouvel arrêt maladie dans le champ **Rechute de
  la maladie du**. Les cas se partagent alors un seul droit. Pour la même
  maladie, un nouveau droit naît lorsque la personne, dans le réglage standard,
  n'a pas été en incapacité de travail pour cette maladie pendant six mois, ou
  lorsque douze mois se sont écoulés depuis le début de la première incapacité.
- **Chaîne depuis** indique le début du premier cas qui compte pour le droit en
  cours.

Les colonnes **Maintien du salaire** et **Statut** montrent la situation du
jour, indépendamment de la période choisie. Les valeurs servent d'orientation
et ne constituent ni un contrôle juridique ni un conseil juridique.

Tuiles : **Employés**, **Jours ouvrés malades** avec les jours calendaires,
**Cas de maladie** avec les certificats de prolongation, **Avec certificat
médical** et **Droit épuisé**. Graphiques : **Jours de maladie par mois** avec
une ligne de médiane (par jour, semaine ou trimestre selon la période) et la
carte de chaleur **Jours de maladie par collaborateur et par mois**.

Filtres comme dans **Congés et flex** : **Zone**, **Employé** et **Équipe** –
ici pour les administrateurs et les personnes disposant du droit **Voir les
arrêts maladie** ; toutes les autres ne voient que leur propre ligne. Cette
page ne propose pas d'export.

## Qualifications

**Rapports** → **Équipe** → **Qualifications** affiche la **Matrice des
qualifications** : une ligne par personne ayant au moins une qualification
active, une colonne par qualification active du catalogue (abréviation, nom
complet au survol). Les qualifications inactives n’apparaissent ni dans la
matrice ni dans les tuiles, les graphiques et l’export.

- Chaque case montre la date d'expiration, ou ✓ si la qualification est valable
  sans date d'expiration.
- Couleurs : vert **valide**, orange **expire dans 30 jours**, rouge
  **expiré**, gris **aucune attribution**. La légende figure sous la matrice.
- Tuiles : **Employés**, **Qualifications**, **Attributions**, **Expirent (≤30
  j)** et **Expiré**.
- Graphiques : **Titulaires par qualification (top 15)** et **Attributions par
  qualification selon le statut** pour les douze qualifications les plus
  fréquentes.

La date de référence est toujours aujourd'hui ; la période de l'en-tête ne
modifie pas la matrice. Les lignes de toutes les personnes sont visibles pour
les administrateurs et les personnes disposant du droit **Gérer les
qualifications** ; toutes les autres ne voient que leur propre ligne. Les filtres
**Employé** et **Équipe** ne sont disponibles qu'avec ce droit. Export : **PDF** au
format paysage, **CSV** et **Excel** avec une ligne par personne et, par
qualification, la date d'expiration ou une mention de validité. Les
qualifications se gèrent dans le catalogue et sur la fiche de la personne, pas
dans le rapport.

## Sécurité au travail

**Rapports** → **Équipe** → **Sécurité au travail** analyse tous les événements
de sécurité survenus pendant la période de l'en-tête.

- Tuiles : **Total des événements**, **Ouverts** (tous ceux qui ne sont pas
  clôturés), **Clôturés** et **Critiques** (gravité critique).
- Graphiques : **Événements par mois** avec la seconde série **dont clôturés**
  et **Événements par mois par statut**, empilés selon **Signalé**, **En
  investigation**, **Mesures définies** et **Clôturé** ; par jour, semaine ou
  trimestre selon la période.
- **Par type** compte **Accident**, **Presqu’accident**, **Danger** et
  **Défaut**, **Par gravité** compte **Faible**, **Moyen**, **Élevé** et
  **Critique**.

Filtres : **Employé** et **Équipe** – ils portent sur la personne qui a signalé
l'événement. Il n'y a pas d'export ; vous traitez les événements individuels
dans le registre des événements de sécurité.

## Problèmes et formation

**Rapports** → **Projets et clients** → **Problèmes et formation** ouvre
l'**Analyse de direction**. Elle montre la situation actuelle, sans période,
filtre ni export.

La carte **Problèmes récurrents** rassemble les alertes précoces des modules
utilisés par votre organisation, regroupées par type :

- **Reprises par client** : clients dont la part de reprises des 90 derniers
  jours manque la valeur cible. Sans valeur cible enregistrée (voir « Valeurs
  cibles (rapports) »), aucune alerte n'est émise.
- **Défauts récurrents** : objets présentant des défauts répétés au cours des
  douze derniers mois.
- **Schémas de réclamation** : réclamations anormalement fréquentes.
- **Tickets récurrents** : clients ou objets avec de nombreux tickets dans la
  fenêtre de temps ; seuil et fenêtre sont des paramètres de l'organisation,
  par défaut trois tickets en 90 jours.
- **Manques de personnel** : équipes dont le besoin planifié dépasse la capacité
  au cours des quatre prochaines semaines.

Chaque entrée indique le constat, un détail et une recommandation ; lorsque
c'est possible, le titre mène au client, à l'objet ou au rapport concerné. Sans
constat, la carte affiche **Aucun point d’attention.**

La carte **Besoins de formation** liste par **Compétence** les **Personnes avec
écart**, l'**Écart moyen (niveaux)** et les **Cours adaptés** (cours publiés
qui transmettent la compétence). Elle repose sur les exigences de compétences
par rôle de la plateforme d'apprentissage ; les justificatifs expirés ne
comptent pas. Sans plateforme d'apprentissage ou sans exigences de
compétences, le tableau reste vide.

## Qui voit quoi

- **Congés et flex**, **Maladies** et **Qualifications** : sans droit
  supplémentaire, chaque personne ne voit que ses propres données. La vue sur
  toutes les personnes de l'organisation est réservée aux administrateurs et,
  pour chaque rapport, à qui détient le droit de la liste correspondante :
  **Voir toutes les demandes de congés** pour **Congés et flex**, **Voir les
  arrêts maladie** pour **Maladies** et **Gérer les qualifications** pour
  **Qualifications**.
- **Sécurité au travail** : entrée de menu et page uniquement avec le droit
  **Voir le registre des événements de sécurité** ou **Modifier / clôturer les
  événements de sécurité** ; les administrateurs les voient toujours. Dans
  l'attribution standard, Chef d'équipe et Direction disposent du droit de
  lecture.
- **Problèmes et formation** : uniquement avec le droit **Voir les rapports** ou
  en tant qu'administrateur. Dans l'attribution standard, la Direction, le Chef
  d'équipe et la Gestion du personnel, entre autres, en disposent.
- **Congés et flex**, **Maladies** et **Qualifications** nécessitent le module
  complémentaire de rapports d'équipe ; **Sécurité au travail** et **Problèmes
  et formation** sont disponibles sans lui.
