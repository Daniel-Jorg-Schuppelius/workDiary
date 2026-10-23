---
title: "Import Toggl"
topic: admin.toggl
version: 4
keywords:
    - Toggl Track
    - importer des temps
    - importer les saisies de temps
    - CSV Toggl
    - API Toggl
    - export workspace
    - migration depuis Toggl
    - correspondances
    - mappage utilisateurs
    - rapport détaillé
    - nouveau Toggl
    - Toggl 2.0
    - Toggl Focus
    - synchronisation en direct
audience:
    - admin
related:
    - admin.plugins
    - admin.import
    - admin.openproject
---

L'import Toggl reprend les saisies de temps de Toggl Track ou du
nouveau Toggl dans WorkDiary. Par défaut, l'import ne fait que lire ; en option, les
corrections peuvent être réécrites et les temps saisis localement
transférés (réglages du plugin). Deux voies existent : l'**import API** (jeton API
et période) et l'**import de fichier** (rapport détaillé CSV ou
archive d'export du workspace). Les clients/projets Toggl sans
correspondance automatique s'accumulent dans la boîte de réception, où
vous les affectez à un client/projet existant, en créez de nouveaux ou
les rejetez ; les mappings enregistrés permettent l'affectation
automatique des imports futurs. Attention : des imports répétés de la
même période peuvent créer des doublons, et le rejet d'entrées est
définitif.

Toggl Track ou le nouveau Toggl :

- Sous **Produit Toggl** dans les paramètres du plugin, vous choisissez
  d'où viennent les temps : **Toggl Track** ou le **nouveau Toggl**
  (anciennement Toggl 2.0 ou Toggl Focus). La lecture, la réécriture et le
  transfert ne concernent que le produit choisi.
- Pour le nouveau Toggl, vous renseignez sa clé d'API (commence par
  `toggl_sk_`). Les ID d'organisation et d'espace de travail sont
  facultatifs ; sans eux, le plugin détermine les espaces de travail via
  votre compte Toggl. Toggl n'autorise qu'une clé active par utilisateur –
  en créer une nouvelle invalide la précédente.
- Si vous utilisez la synchronisation en direct entre Track et le nouveau
  Toggl, choisissez malgré tout une seule source : Toggl assure lui-même
  l'alignement entre les deux produits.
- Après un changement, l'import suivant rattache les saisies déjà reprises
  à la nouvelle source (même utilisateur, début et fin) au lieu de les
  recréer ; les cas ouverts de la boîte de réception suivent. Cela vaut
  pour la période de récupération. Les saisies plus anciennes conservent
  leur lien actuel et leurs corrections ne sont plus envoyées vers Toggl.
  Au besoin, importez une fois sur une période plus longue
  (`toggl:import --days=365`).
- La détection des suppressions ne considère que les saisies de la source
  choisie.

Attribution des utilisateurs (MVP-509) : chaque entrée Toggl est
attribuée à l'utilisateur WorkDiary correspondant via l'e-mail
utilisateur du workspace : d'abord les correspondances enregistrées
(« Gérer les correspondances »), puis l'égalité d'e-mail. Les
utilisateurs Toggl inconnus ou non consultables ne sont jamais
comptabilisés silencieusement sur l'utilisateur principal : ils
arrivent comme cas ouvert dans la boîte d'attribution, où vous
choisissez l'utilisateur ; le choix est mémorisé. Seul le mode
mono-utilisateur explicitement activé (réglage du plugin) comptabilise
les entrées sans signal utilisateur sur l'utilisateur par défaut. Les
anciens imports mal attribués se réparent avec
`toggl:repair-entry-users` (d'abord simulation, écrire avec
`--apply`) ; les temps facturés ou signés ne sont jamais modifiés
automatiquement.

Pour l'import unique de workspace (dossier/ZIP ou API), vous choisissez
explicitement l'attribution des utilisateurs : attribuer uniquement aux
utilisateurs existants (les entrées inconnues restent visiblement non
comptabilisées et sont listées par e-mail), créer les utilisateurs
manquants par e-mail, ou mode mono-utilisateur (tout sur l'utilisateur
par défaut configuré — clairement indiqué dans l'aperçu et le
résultat). Les adresses Toggl individuelles peuvent en outre être
attribuées explicitement ; l'import est idempotent et peut simplement
être relancé une fois les attributions maintenues.

Webhook (facultatif) :

- Pour recevoir plus vite les nouvelles saisies, la page d'import indique
  dans la section **Webhook (facultatif)** l'adresse d'un abonnement webhook
  Toggl. Créez l'abonnement pour l'espace de travail dans Toggl et saisissez
  le secret attribué à cette occasion dans les paramètres du plugin sous
  **Secret du webhook** ; l'**ID d’espace de travail** doit également y
  figurer.
- Le webhook ne déclenche que l'import que la récupération horaire exécute
  aussi. S'il fait défaut, la récupération rattrape.
- Le nouveau Toggl ne propose pas de webhooks. Si la synchronisation en
  direct est active, un abonnement dans Toggl Track peut tout de même
  déclencher la récupération plus tôt ; la lecture se fait toujours depuis
  le nouveau Toggl.
- Vous choisissez l'utilisateur par défaut du mode mono-utilisateur dans les
  paramètres du plugin sous **Enregistrer les temps pour l’utilisateur**,
  dans la liste des utilisateurs.
