---
title: "Plateforme d'apprentissage"
topic: learning.overview
version: 3
audience: []
related:
    - training.overview
    - safety.overview
    - learning.standards
    - learning.subtitles
---

La plateforme d'apprentissage répond à la question **comment on apprend et
comment on est évalué**. *Ce que* chacun doit suivre et jusqu'à quand reste
dans la gestion des formations — les deux s'articulent sans se dupliquer.

## Construire un cours

Un cours se compose de sections et d'unités. Une unité est un contenu, une
évaluation, un devoir, une session en présentiel ou un contenu externe. Le
contenu est construit à partir de blocs (texte, titre, encadré, liste de
contrôle, image, galerie, fichier, vidéo, audio, intégration, code, accordéon,
tableau, article de la base, procédure, question de compréhension, séparateur)
— le HTML libre n'est volontairement pas proposé.

**Chaque bloc est utilisable sans la vue, sans l'ouïe et sans souris.** Les
images et chaque image d'une galerie ont besoin d'un texte alternatif, l'audio
d'une transcription et les colonnes d'un tableau d'un en-tête. L'accordéon et
la solution d'une question de compréhension s'ouvrent au clavier. Une question
de compréhension n'est pas notée : dans l'éditeur, commencez les bonnes réponses
par `*`. Le bloc procédure affiche la version en vigueur ; la procédure se
lance depuis une entrée du journal.

**Les intégrations exigent un hôte autorisé.** Sinon la politique de sécurité
de l'application bloquerait silencieusement la page dans le cours ; l'éditeur
refuse donc immédiatement et visiblement un hôte non autorisé. Les hôtes
autorisés se règlent dans les paramètres.

Un cours peut avoir des **prérequis** (tous, ou un seul suffit) : ils bloquent
le démarrage, pas l’attribution — les inscriptions obligatoires en sont
exemptées. Un **examen sans cours** est un cours de type « examen » avec
exactement une unité de quiz ; la réussite valide le cours cible configuré —
avec le même retour vers certificat, preuve de formation et qualification.

Avec **Ordre imposé**, un cours ne libère chaque unité qu'une fois la précédente
terminée ; les unités verrouillées affichent « Après l'unité précédente ». Une
date de publication de l'unité s'applique en plus.

Depuis LearnDash, reprenez le **ZIP d'export** (catalogue → « Import LearnDash ») : cours, leçons, sujets et évaluations sont créés en brouillon, les questions vont au catalogue avec leur catégorie. Images et médias ne sont pas copiés (espaces réservés à compléter), les vidéos de leçon seulement depuis des hôtes autorisés. Les cours achevés sont notés comme inscriptions « importées » pour les personnes avec e-mail correspondant — sans certificat ni preuve d'instruction, car un achèvement importé n'est pas une preuve en soi. L'essai montre au préalable ce qui serait créé.

## La publication fige le contenu

La publication crée une version du cours contenant une image complète du
contenu. Les participations en cours restent sur leur version — la matière ne
change pas sous quelqu'un qui est déjà à mi-parcours. Après publication le
contenu est verrouillé ; les corrections passent par une version suivante.

Si le cours est rattaché à une formation obligatoire, la publication y inscrit
également la version. La preuve ultérieure porte alors le même numéro.

Les options de cours pilotent le déroulement : un **calendrier de déblocage**
(jours après l'inscription et/ou date fixe — la plus tardive s'applique)
verrouille une unité jusqu'à ce jour, à chaque point d'achèvement — lecteur,
portail, accès externe et synchronisation hors ligne — pas seulement à
l'affichage. Une **durée minimale** compte dès la première ouverture de
l'unité ou via le temps d'apprentissage. Les **unités d'aperçu** se lisent
dans le portail sans inscription (texte seul). Les **catégories** des
paramètres classent le catalogue, les **mots-clés** ajoutent un axe transversal
et restent modifiables après la publication ; **fenêtre de disponibilité** et **limite de
participants** s'appliquent à l'auto-inscription — l'administration peut
toujours affecter, les inscriptions obligatoires contournent la limite. Les
devoirs portent des **règles de fichiers** (extensions, nombre, taille — jamais
plus permissives que le système) et, au choix, une **validation automatique**
avec points complets, incompatible avec le principe des quatre yeux.

## Le temps d'apprentissage est du temps de travail

La formation obligatoire à la sécurité doit avoir lieu **pendant le temps de
travail** (§ 12 al. 1 ArbSchG). Chaque cours porte donc une politique de
temps :

- **Uniquement pendant le temps de travail** (défaut pour les cours
  obligatoires) : le démarrage en dehors est refusé.
- **Compte toujours comme temps de travail** : pour une formation prescrite.
- **En dehors, uniquement avec accord**.
- **Volontaire, non rémunéré** : uniquement pour de véritables offres
  complémentaires — bloqué pour les cours liés à une obligation.

Le temps passé **pendant** les heures de travail n'est pas compté deux fois ;
il est déjà enregistré par la présence. Le temps **en dehors** crée une plage
de présence afin que repos, durée maximale et travail de nuit soient vérifiés.

## Évaluations

Une tentative fige les questions posées. Si une question est modifiée plus
tard, un ancien résultat reste explicable — c'est précisément ce que demande
un contrôleur après un incident. Les tentatives ne sont jamais supprimées ;
une correction s'ajoute à la valeur initiale au lieu de la remplacer.

Les dissertations sont évaluées par un humain. L'IA propose des cours et des
questions et répond aux questions dans le contexte du cours — **elle ne doit
ni évaluer ni décider**.

Les questions vivent dans la **banque de questions** de l’organisation (menu
« Formation » → « Banque de questions ») avec catégorie et nom court ; un quiz
renvoie à des questions de la banque et une même question peut figurer dans
plusieurs quiz. En plus de la liste fixe, des **règles de tirage** puisent à
chaque tentative un nombre de questions aléatoires dans une catégorie (« 5 en
protection incendie »). Retirer une question d’un quiz la laisse dans la
banque ; seules les questions inutilisées peuvent être supprimées — et une
tentative passée garde toujours sa propre copie des questions.

Le déroulement se règle par quiz : toutes les questions sur une page ou une
question par page, retour et passage autorisés, réponses obligatoires, textes de
résultat par plage de pourcentage et un indice par question. Les réponses sont
sauvegardées à chaque modification — rien n’est perdu après une coupure ; une
fois le temps écoulé, seul ce qui a été sauvegardé à temps compte. L’aperçu des
questions montre les questions répondues et marquées.

Les évaluateurs consultent le **dossier de tentative** (questions de la copie
figée, réponses données, points, corrections) — chaque consultation est
consignée. Chaque quiz dispose de **statistiques** (tentatives, taux de
réussite, durée, taux d’erreur par question — taux à partir du groupe minimal).
Depuis la liste des participants, une **tentative supplémentaire** peut être
accordée malgré la limite ou le délai, une seule fois et avec un motif.

Le **carnet de notes** par cours montre apprenants × composantes. Sans composantes, il additionne les points des évaluations et devoirs ; si l'encadrement définit des **composantes** (évaluation, devoir, note manuelle), il peut leur donner des poids — tous totalisant 100 ou aucun. Les notes manuelles sont additives : une correction est une nouvelle entrée, la plus récente compte. Le **bulletin** (PDF) et l'export CSV viennent du même calcul ; tant qu'une composante est ouverte, le bulletin porte la mention « provisoire ».

Finesse par question : les options de réponse peuvent porter **leurs propres points** (« Libellé {3} », négatif possible) — l'option choisie compte alors au lieu du tout-ou-rien ; une **auto-évaluation** est une échelle sans bonne réponse, le niveau choisi est le score ; une **rédaction** accepte du texte, un fichier ou les deux — le fichier est disponible à l'évaluation. Par évaluation, la réussite peut en plus être exigée **en points** et le sous-ensemble par tentative fixé **en pourcentage** des questions disponibles.

## Preuves

Un cours réussi produit ses effets en un seul endroit : certificat avec code
de vérification, preuve de formation au registre de sécurité, obligation
satisfaite et qualification prolongée. Aucun second système de preuve n'est
créé.

Les certificats se vérifient par un lien. La page affiche cours, date,
validité et émetteur — le nom seulement abrégé.

Les données d'apprentissage appartiennent à la personne : le **rapport
d'accès** (module protection des données) énumère inscriptions, tentatives,
certificats, temps d'apprentissage et réservations sous forme de compteurs
avec période — jamais les questions ni les réponses. Le **plan de
conservation** propose la suppression des inscriptions terminées sans
certificat une fois le délai régional écoulé (tentatives et temps
d'apprentissage suivent) ; les certificats restent plus longtemps comme preuve,
puis sont réduits aux initiales — le lien de vérification continue de répondre.

## Compétences

La **matrice des compétences** (Apprentissage → Compétences) montre
le niveau atteint par chaque personne pour chaque compétence. Les niveaux
naissent de deux façons : un cours lié à une compétence attribue son niveau à la
réussite — le refaire ne l'abaisse jamais, et pour les cours à durée de validité
le niveau n'est attribué que pour cette durée. Une **évaluation** par la gestion
de la formation peut en revanche aussi abaisser un niveau.

Un **niveau requis** peut être défini par rôle. Si une personne se situe en
dessous, la matrice signale l'écart ; les niveaux expirés ne comptent pas. Les
compétences ne bloquent rien — le blocage reste du ressort des qualifications.

## Qui apprend

Outre les salariés, les clients peuvent apprendre via le portail et des
intervenants externes sans compte utilisateur. Ces derniers reçoivent un lien
à usage unique et limité dans le temps ; leur preuve est identique.

Les participants d'un cours se gèrent depuis la fiche du cours sous
« Participants » : inscrire des personnes de l'organisation ou des externes,
modifier l'échéance et l'accès avec un motif, annuler (jamais les inscriptions
obligatoires) et créer le lien d'accès pour les externes — un nouveau lien
invalide l'ancien. Une réservation confirmée envoie le lien automatiquement.

Les **paramètres de la plateforme** (catalogue, droit de gestion) contiennent
le réglage des points et du classement, les hôtes d’intégration autorisés et la
**vue formateur** : activée, les personnes ayant un droit d’auteur ou
d’évaluation ne voient que les cours qui leur appartiennent ou auxquels elles
sont rattachées — catalogue, cockpit d’évaluation, statistiques et analyse
suivent la même règle. La gestion voit toujours tout.

Dans le lecteur, chaque personne conserve des **notes privées** sur une unité ou
le cours — visibles par elle seule, pas même par l'administration, absentes de
la recherche d'activité ; « Mes formations » les rassemble. Une **question au
formateur** part vers la personne responsable et les formateurs du cours : en
ticket avec le module helpdesk, sinon par e-mail — les deux sont aussi
notifiés. Deux tuiles du tableau de bord (masquées par défaut) montrent vos
formations ouvertes et le retard d'évaluation ; la recherche d'activité trouve
les cours publiés — les apprenants les leurs, les auteurs tous.

Le **catalogue** s'affiche en liste ou en vignettes (le choix est mémorisé par personne) et porte une **note en étoiles** issue du retour de cours — seulement à partir de cinq réponses, pour que rien ne se rapporte à une personne. Le portail affiche en plus le **prix** de l'article lié. Dans le lecteur, le **mode concentration** masque la barre latérale ; « Dupliquer » crée un nouveau brouillon à partir d'un cours — le matériel oui, les inscriptions et preuves non.

## Analyse et cogestion

L'analyse montre des taux et des anomalies, pas des profils individuels. Les
taux n'apparaissent qu'à partir de cinq inscriptions afin qu'on ne puisse pas
remonter aux personnes. Points, badges et classement sont désactivés par
défaut ; le classement n'affiche en outre que les personnes consentantes.

Les notifications suivent les règles de l’organisation : attribution,
échéance proche, retard (avec escalade), remise reçue, évaluation
disponible, certificat, passage de la liste d’attente, décision de
réservation et validation du temps d’apprentissage. L’IA dispose de trois
entrées — plan et questions en brouillon dans l’éditeur, tuteur dans le
lecteur — et ne fait que proposer ; reprise et évaluation restent manuelles.
Points et badges apparaissent sous « Mes formations » ; le classement ne
montre que les personnes ayant elles-mêmes consenti.
