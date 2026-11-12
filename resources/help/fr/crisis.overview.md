---
title: "Gestion des urgences & des crises"
topic: crisis.overview
version: 4
keywords:
    - cellule de crise
    - plan d'urgence
    - continuité d'activité
    - PCA
    - alerte de crise
    - point de situation
    - délai de notification
    - NIS2
    - violation de données
    - reprise d'activité
    - exercice de crise
audience: []
modules:
    - module.crisis_management
related:
    - documents.manage
---

Le dossier de crise maintient un état de conduite coordonné au-dessus des
processus métier liés (tickets de service, incidents de sécurité ou de
protection des données, événements de sécurité au travail, exécutions de
playbooks) — les modules métier restent maîtres.

**Cellule de crise & alerte :** Rôles avec personne, suppléance et
joignabilité. L'alerte outrepasse volontairement les temps de repos
(alarme de crise) ; chaque membre acquitte son alerte, les alertes non
acquittées sont escaladées manuellement vers la suppléance. La nomination
dans la cellule constitue en même temps l'accès d'urgence audité au
dossier.

**Tableau de situation & décisions :** Les rapports de situation sont
versionnés et en append-only ; le journal des décisions consigne le
moment, la décision et la justification.

**Délais de notification :** Les modèles de délais par catégorie (RGPD
72 h, NIS2 24 h/72 h/1 mois, KRITIS sans délai) sont des données de
catalogue — l'organisation peut les surcharger ; les échéances concrètes
courent à partir de l'activation.

**Rejeter une fausse alerte :** Si une crise signalée s'avère être une
fausse alerte avant que quiconque n'ait évalué la situation, « Rejeter
comme fausse alerte » clôt le dossier avec une justification obligatoire.
La justification figure dans l'historique d'audit, le dossier reste en
lecture seule. Après l'évaluation, on ne rejette plus : on lève l'alerte.

**Communication :** Le brouillon, la validation (jamais par le rédacteur)
et la diffusion documentée restent traçables séparément.

**Reprise & retour d'expérience :** processus critiques avec RTO/RPO,
solutions de contournement et processus de substitution ; après la levée
de l'alerte suit le retour d'expérience (obligatoire avant la clôture).
Les exercices sont menés séparément et ne faussent aucun dossier réel.

Vous gérez vos propres délais dans l’onglet **Délais de déclaration** :
reprendre les valeurs par défaut d’une catégorie comme délais propres, créer,
modifier et désactiver des délais. Dès qu’une catégorie possède un délai
propre actif, seuls les délais propres s’y appliquent ; sans délai propre
actif, les valeurs par défaut s’appliquent de nouveau.

**Mesures avec prédécesseur :** lors de la saisie d'une mesure, vous pouvez
choisir un prédécesseur du même dossier. La mesure ne peut être terminée
qu'une fois le prédécesseur terminé ou annulé ; vous pouvez la commencer avant.

**Processus de substitution et état résiduel :** pour un processus critique,
vous saisissez le processus de substitution en plus de la solution de
contournement. Avec l'état de reprise, vous notez la restriction restante.

**Ouvrir une crise à partir d'un incident :** l'incident de sécurité,
l'incident de protection des données, l'événement de sécurité au travail et le
ticket d'incident majeur portent le bouton « Ouvrir une crise ». La boîte de
dialogue préremplit titre, catégorie et déclencheur et lie l'incident au
nouveau dossier. Vous liez d'autres dossiers via « Lier un dossier » à partir
d'une sélection ; seuls ceux que vous pouvez lire sont proposés.

**Liste de présence d'urgence :** avec le droit correspondant, un dossier de
crise en cours y mène directement.

**Modifier le prédécesseur :** vous modifiez ou supprimez le prédécesseur d’une mesure directement dans la liste des mesures. Seules les mesures du même dossier sont proposées ; WorkDiary refuse un choix qui créerait une boucle (A attend B, B attend A).
