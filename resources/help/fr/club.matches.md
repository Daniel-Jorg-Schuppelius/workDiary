---
title: "Équipes, journées et compositions"
topic: club.matches
version: 1
audience: []
modules:
    - module.club
related:
    - club.groups
    - club.events
    - club.attendance
---

Les sports collectifs et de raquette (football, handball, basket, volley,
hockey, tennis de table, tennis …) s'appuient sur les groupes, les rendez-vous
et la présence. Un sport est un **profil sportif**, c'est-à-dire une
configuration et non un cas particulier du système : famille sportive, postes,
tailles d'effectif (terrain/banc), format de résultat (buts, points par période,
sets), simple/double, date de référence de la catégorie d'âge, disciplines et
types de ressources. L'association adapte les profils ou en crée d'autres ; les
règles fédérales ne sont pas codées en dur.

**Équipes :** Une équipe est un groupe marqué « équipe » avec un profil sportif
(le sien ou celui de la section). La catégorie d'âge est une étiquette libre
(p. ex. U15) ; les critères d'âge d'une équipe sont vérifiés à la date de
référence du profil dans la saison, pas au jour calendaire.

**Saisons et effectifs :** Les saisons sont des périodes nommées (p. ex.
2026/27). Par équipe et saison existe un effectif avec validité par personne,
numéro de maillot, poste et, en sport de raquette, l'ordre de force tenu
manuellement. Les saisons précédentes restent inchangées. Les **joueurs
invités** d'un club partenaire figurent dans l'effectif avec leur club
d'origine ; ce sont des personnes de type « invité » sans attribution de
cotisation, sans connexion et sans appartenance à un groupe.

**Journées :** Une journée est un rendez-vous de l'association avec des données
sportives : équipe, adversaire (sans fiche client ni utilisateur), compétition,
domicile/extérieur, lieu, heure de rendez-vous et responsable. L'équipe est le
groupe cible du rendez-vous ; d'autres groupes peuvent être ajoutés.

**Disponibilité et composition :** Les membres répondent dans le portail
disponible, indisponible ou « peut-être » — une réponse n'est pas une
sélection. L'encadrement compose (terrain/banc avec poste et numéro ; en sport
de raquette paires de simple et de double) et valide. Tailles d'effectif et
postes viennent du profil. Une personne en simple et en double reste une
personne. Avant la validation, les conflits sont affichés : engagement
simultané dans une autre composition ou refus explicite. Valider malgré un
conflit exige un motif et est journalisé. Les sélectionnés deviennent
participants du rendez-vous ; la présence réelle est saisie séparément dans la
feuille de présence.

**Rôles du rendez-vous :** Arbitre, chronométreur/jury, transport, service de
terrain/vestiaire ou surveillance de stand sont attribués par rendez-vous — à un
membre, à un utilisateur du personnel ou comme nom externe. Un membre avec un
rôle compte comme participation associative, pas comme place dans l'effectif.

**Résultat :** Le résultat est saisi manuellement au format du profil (buts,
points par période avec total, sets avec score). Buteurs et remarques vont dans
la note. Les modifications sont journalisées ; pas de classement automatique à
partir de résultats incomplets.

**Import de calendrier :** CSV ou ICS produisent une **liste de propositions**
qui ne crée rien avant confirmation. L'encadrement vérifie adversaire, lieu et
heure et accepte ou écarte chaque proposition ; les lignes connues sont
ignorées lors d'un nouvel import, les doublons possibles avec des journées
existantes sont signalés. Colonnes CSV (ligne d'en-tête, ordre libre) : date,
heure, fin facultative, adversaire et domicile/extérieur — ou domicile et
visiteur comme noms d'équipe — plus lieu et compétition. La synchronisation
fédérale n'est pas incluse.

## Packs de démarrage par sport

Sur la page **Sports**, un **pack de démarrage** crée un sport en une étape : profil
sportif, section, groupes ou équipes habituels et installations, plus, selon le
sport, un système de grades (arts martiaux), des chevaux d'école (équitation) ou une
exigence de présence (tir). Onze sports sont fournis : arts martiaux, tennis de
table, hockey, équitation, football, handball, basket-ball, volley-ball, tennis,
athlétisme et tir sportif. Un pack est une configuration, pas un cas particulier :
tout ce qu'il crée peut ensuite être modifié ou supprimé, les entrées existantes du
même nom restent intactes et aucune règle fédérale ni aucun seuil légal n'est
intégré.

Le secteur de démonstration **Association sportive** installe les onze packs et les
remplit de personnes, rendez-vous, présences, cotisations, journées de match,
compétitions, examens et leçons d'équitation fictifs.
