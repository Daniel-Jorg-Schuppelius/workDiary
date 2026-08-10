---
title: "Import Toggl"
topic: admin.toggl
version: 2
audience:
    - admin
related:
    - admin.plugins
    - admin.import
    - admin.openproject
---

L'import Toggl reprend les saisies de temps de Toggl Track dans
WorkDiary. Par défaut, l'import ne fait que lire ; en option, les
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
