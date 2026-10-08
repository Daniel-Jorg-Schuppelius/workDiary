---
title: "Import Kimai"
topic: admin.kimai
version: 1
keywords:
    - Kimai
    - importer des temps
    - reprendre des feuilles de temps
    - Kimai CSV
    - API Kimai
    - migrer depuis Kimai
    - réécrire les temps
    - écriture retour
    - affectation des utilisateurs
    - renvoyer les corrections
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - admin.clockify
    - admin.toggl
    - finance.open-times
    - admin.organization-settings
---

La page **Import Kimai** reprend dans WorkDiary les saisies de temps de l’outil
Kimai – sous forme d’export CSV téléversé ou directement via l’API Kimai. Si
vous le souhaitez, elle réinscrit aussi dans Kimai, sous forme de feuilles de
temps, les temps saisis dans WorkDiary et renvoie vers Kimai les corrections
apportées aux temps importés. Vous trouvez la page dans le menu système (la
roue dentée **Système** dans l’en-tête) sous **Plugins** → **Import Kimai**,
dès que le plugin est actif.

## Prérequis

- Le plugin est activé pour votre organisation : **Système** → **Plugins** →
  **Plugins**, puis **Activer** sur l’entrée Kimai. L’activation et les
  paramètres ne valent que pour l’organisation courante.
- La page et les paramètres sont réservés aux administrateurs. La **Boîte de
  rapprochement**, où vous traitez les cas ouverts, est aussi accessible à la
  comptabilité.
- Pour la voie CSV, un export des feuilles de temps de Kimai suffit.
- Pour la voie API, il vous faut l’adresse d’une instance Kimai 2 et le jeton
  API d’un utilisateur Kimai (dans Kimai sous Profil → Accès API). Pour
  recevoir les temps de toutes les personnes, cet utilisateur a besoin dans
  Kimai du droit view_other_timesheet.
- L’instance Kimai doit être joignable publiquement. Seul l’exploitant de votre
  installation peut autoriser une instance située sur un réseau interne.

## Configuration

Vous enregistrez les accès sur la page **Plugins** via **Configurer** sur
l’entrée Kimai :

1. **URL de base Kimai** : l’adresse à laquelle vous ouvrez Kimai dans le
   navigateur – sans /api à la fin.
2. **Jeton API Kimai** : le jeton issu de Kimai. Il est stocké chiffré ; un
   champ vide conserve la valeur précédente à l’enregistrement.
3. **Consulter les temps de tous les utilisateurs** : activé (par défaut) si
   l’utilisateur du jeton peut lire les temps des autres ; sinon seuls ses
   propres temps arrivent.
4. **Fenêtre de synchronisation (jours)** : jusqu’où remonte un import API sans
   période (30 jours par défaut).
5. **Reprendre l’état facturable** : activé, l’indicateur facturable de Kimai
   est repris ; désactivé, les temps importés ne sont jamais marqués comme
   facturables.
6. **Mode mono-utilisateur** et **Enregistrer les temps pour l’ID
   utilisateur** : uniquement pour les postes individuels, voir plus bas.
7. Pour la réimputation, **Activer l'écriture retour**, **ID d'activité Kimai
   pour les réimputations** et éventuellement **Report immédiat des nouveaux
   temps** ; pour le renvoi des corrections, **Renvoyer les corrections**.
8. **Enregistrer**. **Tester la connexion** dans la boîte de dialogue vérifie
   l’accès. Sans jeton, le plugin signale le mode CSV – ce n’est pas une
   erreur.

## Importer des temps

**Téléverser un CSV :** exportez les temps de Kimai au format CSV (Kimai →
Temps → Export → CSV), choisissez le fichier sur la page **Import Kimai** et
cliquez sur **Importer**. WorkDiary reconnaît les colonnes à la ligne
d’en-tête, en allemand ou en anglais – par exemple date, début, fin ou durée,
client, projet, activité, description, facturable, étiquettes et e-mail. La
virgule comme le point-virgule conviennent comme séparateur, et le fichier
peut peser au plus 20 Mo. Les heures sont lues comme heure locale de votre
organisation.

**Importer directement depuis l'API Kimai :** choisissez si besoin une période
(**De**, **Jusqu’à**) et cliquez sur **Importer depuis l’API**. Sans période,
WorkDiary interroge les derniers jours selon la fenêtre de synchronisation.
L’import ignore les feuilles de temps en cours, sans fin.

Il n’existe pas d’import Kimai planifié : chaque import démarre sur cette
page. La page indique ensuite combien d’entrées ont été créées, ignorées ou
laissées en attente dans la boîte, et combien n’ont pu être rattachées à aucun
utilisateur.

## Affecter clients, projets et personnes

- **Projets :** l’import ne crée ni clients ni projets. Une entrée est
  comptabilisée lorsque son projet est trouvé : par une affectation mémorisée,
  lors de l’import API par le numéro de projet issu de Kimai, sinon par le même
  nom de projet chez le client correspondant. Si **Affecter les temps aux
  projets par mot-clé** est activé dans les paramètres de l’organisation, une
  correspondance univoque par mot-clé aide en dernier recours.
- **Boîte de rapprochement :** tout le reste s’y accumule, regroupé par
  client, projet et activité. La carte **Boîte de rapprochement** de la page
  indique le nombre de groupes ouverts, **Vers la boîte de réception** y mène.
  Vous y choisissez le client, éventuellement le client final, et le projet,
  puis vous comptabilisez le groupe. L’affectation est mémorisée ; les imports
  suivants comptabilisent alors sans demander.
- **Personnes :** chaque temps appartient à la personne qui l’a saisi dans
  Kimai. L’import CSV utilise la colonne e-mail, l’import API le nom
  d’utilisateur Kimai. WorkDiary compare l’un ou l’autre à l’adresse e-mail
  des utilisateurs actifs. Sans correspondance, un cas « Utilisateur
  inconnu » ou « Entrée sans signal d’utilisateur » est créé dans la boîte,
  au lieu que le temps atterrisse sans bruit chez l’utilisateur principal.
  Choisissez-y l’utilisateur ; le choix est mémorisé.
- **Mode mono-utilisateur :** seulement s’il est activé, l’import affecte les
  entrées sans personne identifiable à l’utilisateur par défaut. C’est
  l’utilisateur indiqué dans **Enregistrer les temps pour l’ID utilisateur**,
  sinon le propriétaire de l’organisation ou le premier utilisateur.

## Nouvel import et modifications

- Un nouvel import ne crée jamais deux fois des entrées déjà importées.
- Lors de l’import API, WorkDiary reconnaît chaque entrée à son numéro Kimai.
  Si une entrée connue a changé dans Kimai (début, fin, durée, description),
  WorkDiary reprend la modification. Si le temps est déjà facturé ou exporté
  ici, WorkDiary ne change rien ; le cas figure dans la boîte pour
  information.
- Si un import API avec **Consulter les temps de tous les utilisateurs** ne
  retrouve plus, dans la période interrogée, une entrée importée auparavant,
  celle-ci est considérée comme supprimée dans Kimai. WorkDiary supprime alors
  aussi le temps, sauf s’il est facturé ; dans ce cas un cas est créé dans la
  boîte.
- Pour la voie CSV, WorkDiary reconnaît une entrée à son heure, son client, son
  projet, son activité, sa description et son e-mail. Si elle a été modifiée
  dans Kimai, un nouveau téléversement crée une entrée supplémentaire. Les
  imports CSV ne déclenchent aucune suppression. La voie API convient mieux à
  une synchronisation continue.
- Les étiquettes de Kimai sont ajoutées, jamais retirées.

## Réécrire les temps vers Kimai

La section **Réécrire les temps vers Kimai** apparaît dès qu’un accès API est
enregistré et que **Activer l'écriture retour** est activé.

- Sont réimputés les temps saisis dans WorkDiary avec un début et une fin, pas
  encore exportés, dont le projet est relié à un projet Kimai. Ce lien naît de
  l’import API – pour les projets trouvés automatiquement et pour les groupes
  API que vous comptabilisez dans la boîte. Un import CSV ne le fournit pas.
- WorkDiary ne réimpute jamais les temps importés de Kimai.
- Kimai exige une activité pour chaque feuille de temps : l’**ID d'activité
  Kimai pour les réimputations** – le numéro de l’activité dans Kimai –
  s’applique à tous les temps réimputés. La description et l’indicateur
  facturable sont transmis.
- **Exporter vers Kimai** lance la réimputation après confirmation,
  éventuellement pour une période. Le message indique les entrées
  comptabilisées, ignorées et échouées.
- Un temps réimputé est considéré comme exporté dans WorkDiary : il
  n’apparaît plus sous **Temps ouverts** et n’est plus facturé ici. Avec
  **Report immédiat des nouveaux temps**, cela se produit dès la saisie, sans
  possibilité de correction.

## Renvoyer les corrections

Avec **Renvoyer les corrections**, WorkDiary transmet à Kimai les
modifications des temps importés via l’API (description, début, fin, durée,
facturable) ainsi que leur suppression. Au préalable, WorkDiary compare l’état
actuel dans Kimai : si l’entrée y a été modifiée entre-temps, WorkDiary
n’écrase rien et crée un conflit dans la boîte. Les temps facturés et les
temps importés par CSV ne sont jamais renvoyés. Le transfert s’exécute en
arrière-plan et est répété en cas d’erreur.

## Erreurs fréquentes

- **Aucun accès API configuré** au lieu de la section d’import : l’URL de base
  ou le jeton manque dans les paramètres du plugin.
- « Aucun ID d'activité Kimai défini — réimputation impossible. » : saisissez
  le numéro d’une activité Kimai.
- « Aucun projet n'est associé à un projet Kimai » : lancez d’abord un import
  API ou comptabilisez les groupes API dans la boîte.
- Beaucoup de cas « Utilisateur inconnu » : les noms d’utilisateur Kimai ou la
  colonne e-mail ne correspondent pas aux adresses e-mail de WorkDiary.
  Affectez chaque personne une fois dans la boîte.
- Seuls les temps de l’utilisateur du jeton arrivent : il lui manque dans
  Kimai le droit view_other_timesheet, ou **Consulter les temps de tous les
  utilisateurs** est désactivé.
- L’import CSV ne crée rien : la ligne d’en-tête doit contenir au moins une
  date et une heure de fin ou une durée.
- L’API est injoignable : saisissez l’adresse sans /api, vérifiez le jeton et
  utilisez **Tester la connexion**. Si l’instance se trouve sur un réseau
  interne, WorkDiary signale une adresse privée. Si les erreurs se
  multiplient, WorkDiary désactive automatiquement le plugin ; une fois la
  cause corrigée, réinitialisez-le sur la page **Plugins** avec
  **Réinitialiser et réactiver**.
