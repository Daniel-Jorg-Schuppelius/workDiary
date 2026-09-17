---
title: "Recherche et recherche d'activités"
topic: search.overview
version: 3
audience: []
related: []
---

La recherche répond avant tout à une question : **qu'a-t-on fait, quand et pour
quel client ?** Elle parcourt les saisies de temps (y compris les notes de
télémaintenance et les lignes de feuilles de temps), les missions avec
commentaires, les notes de feuilles de temps, les tickets, procès-verbaux,
points ouverts, notes de communication et articles de connaissances — chacun
avec projet, client final et client comme contexte. Les données de base comme
clients, projets, objets, frais et documents figurent en dessous.

## Comment la recherche fonctionne

- Tous les mots doivent apparaître, peu importe où : « smtp exchange » trouve la
  saisie « Sendeconnector basculé sur SMTP » dans le projet « Migration Exchange ».
- La recherche porte sur le début des mots : « exch » trouve « Exchange » et
  « Exchangeserver ».
- Des mots entre guillemets (« smtp relay ») doivent se suivre directement.
- Un signe moins exclut : « imprimante -toner ».
- Les mots de remplissage comme « quand avons-nous … fait » sont ignorés.
- Si un mot n'apparaît nulle part, la recherche inclut des mots d'orthographe
  proche et l'indique. « Orthographes similaires » inclut aussi des variantes
  pour les mots connus — utile en cas de fautes de frappe dans les notes.
- L'administration gère les synonymes sous Système › Organisation › Synonymes
  de recherche.

## Vue d'ensemble et filtres

« Clients & clients finaux » montre où il y a des résultats et sur quelle
période ; un clic filtre dessus. La barre de filtres propose source, période,
personne, client (y compris ses clients finaux), client final et tri. Sans terme
de recherche mais avec un client, un client final ou un projet, les activités les
plus récentes s'affichent.

« Mots-clés dans les résultats » compte les mots-clés des entrées trouvées ; un
clic restreint à l'un d'eux, la croix du filtre le retire. Si vous pouvez voir les
collections, la barre de filtres propose aussi **Collection**, sous-collections
comprises. Les deux filtres fonctionnent aussi sans terme de recherche. Seules
les entrées que vous pouvez ouvrir sont comptées : les mots-clés de contenus
confidentiels d'autres personnes n'apparaissent donc pas.

## Points d'entrée

La page client, la page client final et le projet ont leur propre champ de
recherche ou bouton. Un appelant reconnu ouvre directement la recherche avec son
filtre client. Avec le module IA actif, « Réponse IA » résume les résultats.

La liste de résultats respecte les limites de modules et de droits — seuls les
éléments que votre rôle peut ouvrir sont affichés.
