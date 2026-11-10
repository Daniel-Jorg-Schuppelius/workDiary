---
title: "Import CSV"
topic: admin.import
version: 6
keywords:
    - import de données
    - importer des données de base
    - importer des clients
    - assistant import
    - correspondance des colonnes
    - import en masse
    - reprise des données
    - rapport des erreurs
    - import iCal
    - importer un calendrier
audience:
    - admin
    - geschaeftsfuehrung
    - personalverwaltung
    - teamleitung
    - buchhaltung
    - user
    - aussendienst
schema: process
related:
    - admin.handbook
    - admin.tenants
    - contacts.manage
---

## Objectif et contexte

L'assistant d'import amène les données de base dans WorkDiary par
CSV — avec analyse **avant** écriture et rapport d'erreurs complet.
C'est la voie la plus rapide pour reprendre un existant (clients,
utilisateurs, projets, équipes, fournisseurs, matériaux) de façon
structurée, sans laisser la qualité des données au hasard.

## Prérequis

- Droits d'administration.
- Un fichier CSV par entité ; le mappage des colonnes se fait dans
  l'assistant.
- Pour les données dépendantes : le bon **ordre** (d'abord
  clients/équipes, puis projets, etc.).

## Déroulement recommandé

1. **Choisir l'entité** (clients, utilisateurs, projets, équipes,
   fournisseurs, matériaux…).
2. **Téléverser le CSV** — l'**analyse préalable** vérifie structure
   et contenu sans rien écrire.
3. **Contrôler l'aperçu :** lignes reconnues, avertissements,
   erreurs.
4. **Confirmer** — l'import tourne en tâche de fond.
5. **Télécharger le CSV d'erreurs :** toutes les lignes rejetées avec
   motif ; corriger et réimporter.

![Assistant d’import avec choix d’entité, modèle et analyse préalable](media/administration/import-assistent.png)
*L’assistant d’import : choisir l’entité, télécharger le modèle, déposer le fichier — l’analyse n’écrit rien.*

## Affecter vos propres noms de colonnes

Si l'import ne reconnaît pas un en-tête de colonne, le lot affiche la carte
**Affectation des colonnes**. Choisissez la colonne cible de chaque en-tête et
enregistrez — le fichier est aussitôt revérifié. L'affectation s'applique
ensuite à chaque fichier du même type d'import, y compris l'import de clients
depuis la liste des clients. Une proposition de l'IA ne fait que préremplir le
choix. Les affectations enregistrées figurent dans le menu de la liste des
imports sous **Affectations de colonnes enregistrées**, où vous pouvez les
supprimer une à une.

## Exemple pratique

Lors d'une migration, une entreprise importe d'abord un fichier test
de dix clients, vérifie aperçu et mappage, puis charge les 800 lignes
du stock complet. Douze lignes atterrissent motivées dans le rapport
d'erreurs, sont corrigées et reprises au second passage.

## Erreurs fréquentes

- **Charger le stock complet sans fichier test** — les erreurs de
  mappage se multiplient inutilement.
- **Ignorer l'ordre :** des projets avant leurs clients échouent sur
  des références manquantes.
- **Ignorer le rapport d'erreurs :** les lignes fautives n'arrêtent
  pas l'import — mais elles manquent au stock tant qu'elles ne sont
  pas réimportées.

## Effets et prochaines étapes

Rien n'est écrit avant la confirmation — analyse et aperçu sont sans
risque. L'historique montre tous les passages avec leur statut,
filtrable par entité et état. Ensuite : contrôler les données par
sondage et fusionner les doublons.

## Données de mouvement et de mesure

Outre les données de base, l'import reprend aussi des données courantes :
relevés de compteur, consommations ESG (activité, quantité, période et
qualité des données), mesures d'inspection pour des inspections existantes
et positions d'appareils issues d'exports télématiques. Pour les positions,
WorkDiary signale lorsqu'un appareil loué quitte son lieu d'intervention.

## Pointages et temps de projet depuis le calendrier

Pour les pointages et les temps de projet, vous pouvez choisir une source
de calendrier connectée au lieu d'un fichier (CalDAV, Google Agenda,
Microsoft 365). Les événements de la période choisie sont récupérés et
vérifiés comme un fichier iCal, avec aperçu, filtre de catégories et
résolution des séries.

L'import des présences accepte la colonne facultative **erfasst am** (date et
heure de l'enregistrement d'origine). Elle sert au délai d'enregistrement
MiLoG ; sans elle, le délai n'est pas contrôlé pour ces lignes.

## Abonnements de calendrier : en continu depuis un calendrier

Sous **Abonnements de calendrier** (menu de la vue d’ensemble des imports),
vous vous abonnez à un calendrier iCal via son adresse d’abonnement
(`https://` ou `webcal://`) ou choisissez comme **Source** une connexion de
calendrier de l’organisation (CalDAV, Google Agenda, Microsoft 365) – les
mêmes que celles proposées par l’import depuis le calendrier. WorkDiary le lit
toutes les heures et reprend les rendez-vous comme l’import de fichier :

- **Personne :** si le calendrier appartient à une personne, tous les
  rendez-vous lui reviennent. Sans personne, l’adresse e-mail de
  l’organisateur ou du participant décide.
- **Période :** les 30 derniers jours sont lus ; un rendez-vous ne compte
  qu’une fois terminé. Les séries sont développées dans cette période.
- **Seulement les changements :** WorkDiary ignore les rendez-vous inchangés
  (mêmes SEQUENCE et LAST-MODIFIED) ; un rendez-vous modifié met à jour la
  saisie. Si le serveur répond « non modifié », tout le passage est ignoré.
- **Pas de doublons :** un rendez-vous déjà saisi par un import de fichier
  (même UID) est repris et pas saisi une seconde fois.
- **Suppressions :** si un rendez-vous saisi disparaît du calendrier, le temps
  reste. Une indication « Abonnement de calendrier » apparaît dans la boîte
  d’affectation ; supprimez ou corrigez-y le temps en connaissance de cause.
- **Affectation :** les rendez-vous sans projet correspondant arrivent dans la
  boîte d’affectation comme pour l’import de fichier ; le passage suivant
  réessaie.

L’adresse est stockée chiffrée ; seul le serveur est affiché. Les adresses du
réseau interne nécessitent **Autoriser les calendriers du réseau interne** et
l’accord de l’exploitant. **Récupérer maintenant** lance un passage
immédiatement.

Une connexion de calendrier fournit à chaque passage les rendez-vous des 30
derniers jours ; les règles ci-dessus s’appliquent sans changement. Si la
connexion est déconnectée ou désactivée, l’abonnement signale une erreur
jusqu’à ce que vous choisissiez une autre source. Seules les connexions de
votre organisation peuvent être choisies.
