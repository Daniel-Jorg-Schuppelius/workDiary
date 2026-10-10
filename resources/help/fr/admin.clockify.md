---
title: "Import Clockify"
topic: admin.clockify
version: 3
keywords:
    - Clockify
    - importer des temps
    - rapport détaillé
    - Clockify CSV
    - API Clockify
    - migrer depuis Clockify
    - transférer des temps
    - télémaintenance vers Clockify
    - affectation des utilisateurs
    - renvoyer les corrections
    - import horaire
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - admin.kimai
    - admin.toggl
    - admin.scheduler
    - admin.organization-settings
---

La page **Import Clockify** reprend dans WorkDiary les saisies de temps de
Clockify – sous forme de rapport détaillé (CSV) téléversé ou directement via
l’API Clockify. Si vous le souhaitez, elle transfère aussi vers Clockify les
temps saisis dans WorkDiary, par exemple des sessions de télémaintenance, et
renvoie les corrections apportées aux temps importés. Vous trouvez la page dans
le menu système (la roue dentée **Système** dans l’en-tête) sous **Plugins** →
**Import Clockify**, dès que le plugin est actif.

## Prérequis

- Le plugin est activé pour votre organisation : **Système** → **Plugins** →
  **Plugins**, puis **Activer** sur l’entrée Clockify. L’activation et les
  paramètres ne valent que pour l’organisation courante.
- La page et les paramètres sont réservés aux administrateurs. La **Boîte de
  rapprochement**, où vous traitez les cas ouverts, est aussi accessible à la
  comptabilité.
- Pour la voie CSV, un rapport détaillé de Clockify suffit.
- Pour la voie API, il vous faut une clé API (dans Clockify sous Profile →
  Advanced → API). Le forfait gratuit de Clockify n’autorise que 30 requêtes
  API par heure ; la voie CSV y est recommandée.

## Configuration

Vous enregistrez les accès sur la page **Plugins** via **Configurer** sur
l’entrée Clockify :

1. **Clé API Clockify** : la clé issue de Clockify. Elle est stockée chiffrée ;
   un champ vide conserve la valeur précédente à l’enregistrement.
2. **ID d’espace de travail** : facultatif. S’il est vide, WorkDiary utilise
   l’espace de travail par défaut de la clé API.
3. **URL de base de l’API** et **URL de base de l'API Reports** : à modifier
   uniquement si votre compte se trouve sur une instance Clockify régionale ;
   le texte d’aide de la boîte de dialogue donne un exemple.
4. **Fenêtre de synchronisation (jours)** : jusqu’où remonte un import API sans
   période – y compris l’import horaire – et jusqu’où porte le transfert
   horaire (30 jours par défaut).
5. **Reprendre l’état facturable** : activé, l’indicateur facturable de
   Clockify est repris ; désactivé, les temps importés ne sont jamais marqués
   comme facturables.
6. **Mode mono-utilisateur** et **Enregistrer les temps pour
   l’utilisateur** : uniquement pour les postes individuels, voir plus bas.
   Vous choisissez l’utilisateur dans la liste.
7. Éventuellement **Activer le transfert des temps**, **Renvoyer les
   corrections** et **Secret du webhook** (voir « Webhook »).
8. **Enregistrer**. **Tester la connexion** dans la boîte de dialogue vérifie
   l’accès. Sans clé API, le plugin signale le mode CSV – ce n’est pas une
   erreur.

## Importer des temps

**Téléverser un CSV :** dans Clockify, exportez le rapport détaillé au format
CSV (Clockify → Reports → Detailed → Export → CSV), choisissez le fichier sur
la page **Import Clockify** et cliquez sur **Importer**. WorkDiary reconnaît
les colonnes à la ligne d’en-tête – Project, Client, Description, Task, Email,
Tags, Billable, Start Date, Start Time, End Date, End Time ainsi que Duration
(h) ou Duration (decimal). Les colonnes inutiles peuvent manquer ; Start Date
et soit une heure de fin, soit une durée sont obligatoires. La virgule comme le
point-virgule conviennent, et le fichier peut peser au plus 20 Mo.

**Importer directement depuis l'API Clockify :** choisissez si besoin une
période (**De**, **Jusqu’à**) et cliquez sur **Importer depuis l’API**.
WorkDiary récupère les saisies de tous les utilisateurs de l’espace de
travail ; sans période, les derniers jours selon la fenêtre de
synchronisation. L’import ignore les saisies en cours, sans fin.

**Import horaire :** dès qu’une clé API est enregistrée, l’import API
s’exécute en plus automatiquement toutes les heures – sur la fenêtre de
synchronisation et avec rapprochement des suppressions (voir plus bas). Vous
modifiez la fréquence sous **Tâches planifiées**, à l’entrée « Import
Clockify ». Chaque exécution consomme des requêtes du quota de votre offre
Clockify. Sans clé API, il n’y a pas d’import automatique ; vous chargez
toujours les fichiers CSV ici.

Si l’API Clockify signale une erreur, la page l’affiche et rien n’est
importé. Après un import sur cette page, la page indique combien d’entrées ont été créées, ignorées ou
laissées en attente dans la boîte, et combien n’ont pu être rattachées à aucun
utilisateur.

## Affecter clients, projets et personnes

- **Projets :** l’import ne crée ni clients ni projets. Une entrée est
  comptabilisée lorsque son projet est trouvé : par une affectation mémorisée,
  sinon par le même nom de projet chez le client correspondant. Si **Affecter
  les temps aux projets par mot-clé** est activé dans les paramètres de
  l’organisation, une correspondance univoque par mot-clé aide en dernier
  recours.
- **Boîte de rapprochement :** tout le reste s’y accumule, regroupé par client,
  projet et tâche Clockify. La carte **Boîte de rapprochement** de la page
  indique le nombre de groupes ouverts, **Vers la boîte de réception** y mène.
  Vous y choisissez le client, éventuellement le client final, et le projet,
  puis vous comptabilisez le groupe. L’affectation est mémorisée ; les imports
  suivants comptabilisent alors sans demander.
- **Personnes :** chaque temps appartient à la personne qui l’a saisi dans
  Clockify. WorkDiary compare son adresse e-mail (colonne Email ou valeur de
  l’API) à l’adresse e-mail des utilisateurs actifs. Sans correspondance, un
  cas « Utilisateur inconnu » ou « Entrée sans signal d’utilisateur » est créé
  dans la boîte, au lieu que le temps atterrisse sans bruit chez l’utilisateur
  principal. Choisissez-y l’utilisateur ; le choix est mémorisé.
- **Mode mono-utilisateur :** seulement s’il est activé, l’import affecte les
  entrées sans personne identifiable à l’utilisateur par défaut. C’est
  l’utilisateur indiqué dans **Enregistrer les temps pour l’utilisateur**,
  sinon le propriétaire de l’organisation ou le premier utilisateur.

## Nouvel import et modifications

- Un nouvel import ne crée jamais deux fois des entrées déjà importées.
- Lors de l’import API, WorkDiary reconnaît chaque entrée à son identifiant
  Clockify. Si une entrée connue a changé dans Clockify (début, fin, durée,
  description), WorkDiary reprend la modification. Si le temps est déjà
  facturé ou exporté ici, WorkDiary ne change rien ; le cas figure dans la
  boîte pour information.
- Si un import API ne retrouve plus, dans la période interrogée, une entrée
  importée ou transférée auparavant, celle-ci est considérée comme supprimée
  dans Clockify. WorkDiary supprime alors aussi le temps, sauf s’il est
  facturé ; dans ce cas un cas est créé dans la boîte.
- Pour la voie CSV, WorkDiary reconnaît une entrée à son heure, son client, son
  projet, sa tâche, sa description et son e-mail. Si elle a été modifiée dans
  Clockify, un nouveau téléversement crée une entrée supplémentaire. Les
  imports CSV ne déclenchent aucune suppression.
- Les étiquettes de Clockify sont ajoutées, jamais retirées.

## Transférer les temps vers Clockify

Avec **Activer le transfert des temps**, WorkDiary copie vers Clockify les
temps de travail saisis dans WorkDiary avec un début et une fin :

- Seuls les temps des projets reliés à un projet Clockify sont transférés. Un
  projet est considéré comme relié dès que vous avez comptabilisé sur lui un
  groupe Clockify dans la Boîte de rapprochement et qu’un projet portant le
  même client et le même nom existe dans Clockify. Les projets que l’import a
  trouvés par leur seul nom ne comptent pas.
- Les saisies sont toujours créées pour le titulaire de la clé API – Clockify
  ne permet pas autre chose.
- Les nouveaux temps partent dès leur saisie. En outre, une exécution horaire
  rattrape ce qui manque encore dans la fenêtre de synchronisation. Avec
  **Transférer vers Clockify** dans la section **Transférer les temps vers
  Clockify**, vous lancez le transfert manuellement, éventuellement pour une
  période.
- Contrairement à une réimputation, le temps reste facturable dans WorkDiary.
  Il se comporte ensuite comme un temps importé : modifications et
  suppressions sont synchronisées dans les deux sens.
- Les temps déjà transférés et ceux importés de Clockify sont ignorés.

## Renvoyer les corrections

Avec **Renvoyer les corrections**, WorkDiary transmet à Clockify les
modifications des temps importés ou transférés via l’API (description, début,
fin, durée, facturable) ainsi que leur suppression. Au préalable, WorkDiary
compare l’état actuel dans Clockify : si l’entrée y a été modifiée
entre-temps, WorkDiary n’écrase rien et crée un conflit dans la boîte. Les
temps facturés et les temps importés par CSV ne sont jamais renvoyés.

## Webhook

Avec un forfait Clockify payant, Clockify peut informer WorkDiary des entrées
nouvelles et modifiées ; l’import démarre alors de lui-même :

1. La page **Import Clockify** indique dans la section **Webhook
   (facultatif)** l’adresse que Clockify doit appeler.
2. Dans Clockify, créez sous Paramètres de l’espace de travail → Webhooks un
   webhook vers cette adresse.
3. Saisissez son jeton de signature dans les paramètres du plugin sous
   **Secret du webhook** et renseignez-y également l’**ID d’espace de
   travail**.

De nombreux événements rapprochés ne déclenchent qu’un seul import. Sans
secret du webhook, le webhook reste désactivé. La récupération horaire reste
la source fiable : elle rattrape ce qu’un webhook défaillant a manqué.

## Erreurs fréquentes

- **Aucune clé API configurée** au lieu de la section d’import : la clé API
  manque dans les paramètres du plugin.
- Le message mentionne le plan Free avec 30 requêtes par heure : le quota est
  épuisé. Importez par CSV ou patientez ; l’exécution suivante reprend un
  transfert interrompu.
- « Clockify : aucun espace de travail déterminable » : saisissez l’**ID
  d’espace de travail**.
- « Aucun projet n'est associé à un projet Clockify » : comptabilisez d’abord
  des groupes Clockify sur les projets voulus dans la boîte.
- Beaucoup de cas « Utilisateur inconnu » : les adresses e-mail de Clockify
  diffèrent de celles de WorkDiary. Affectez chaque personne une fois dans la
  boîte.
- Dates erronées à l’import CSV : les dates au format avec barre oblique dont
  le jour et le mois valent tous deux 12 au plus sont lues comme mois/jour.
  Réglez un format de date univoque dans Clockify.
- Si les erreurs se multiplient, WorkDiary désactive automatiquement le
  plugin ; une fois la cause corrigée, réinitialisez-le sur la page
  **Plugins** avec **Réinitialiser et réactiver**.
