---
title: "Jobs planifiés"
topic: admin.scheduler
version: 1
audience:
    - admin
related:
    - admin.diagnostics
    - admin.operations
---

Cette page affiche tous les jobs d'arrière-plan récurrents de la
plateforme – du housekeeping à l'escalade des échéances, en passant par
la synchronisation des intégrations.

**Un registre plutôt que la prolifération :** Tous les jobs planifiables
proviennent d'un registre central avec un **plan par défaut** défini en
dur. Seuls les jobs qui y sont enregistrés apparaissent ici et peuvent
être pilotés – vous ne pouvez volontairement pas planifier de commandes
arbitraires via cette page.

**Vue d'ensemble :** Pour chaque job, vous voyez le plan effectif avec
son **origine** (par défaut, paramètre ou replanification manuelle), la
dernière exécution avec son résultat, un compteur d'erreurs et la
prochaine échéance. Vous repérez ainsi d'un coup d'œil si un job est
bloqué ou échoue durablement.

**Filtrer et trier :** Au-dessus du tableau, vous restreignez la liste
par nom, clé ou commande, par criticité, dernier résultat et origine du
plan – par exemple aux jobs en échec, jamais exécutés ou décalés par la
plage de fonctionnement. L'interrupteur **Suspendues uniquement** montre
ce qui est actuellement à l'arrêt. Les colonnes Tâche, Dernière
exécution, Prochaine échéance et Échecs consécutifs se trient d'un clic ;
les jobs sans valeur (jamais exécutés, suspendus) restent toujours à la
fin.

**Replanifier avec des garde-fous :** Chaque job définit les cadences qui
lui sont autorisées (p. ex. toutes les heures ou quotidiennement à une
heure donnée). La replanification n'est possible qu'à l'intérieur de ces
cadences autorisées – un job critique ne peut ainsi pas être placé par
inadvertance sur un rythme inadapté. Les expressions cron libres restent
réservées à l'exploitant. Via **Réinitialiser**, un job revient à tout
moment à son plan par défaut.

**Plage de fonctionnement :** si le serveur ne tourne pas 24 h/24 –
par exemple parce qu'il s'arrête la nuit –, renseignez le début et la
fin de la plage de fonctionnement dans les **Paramètres** (une fin à
00:00 correspond à minuit). Les tâches à heure fixe situées en dehors
de la plage s'exécutent alors regroupées dans les deux premières
heures de fonctionnement, dans leur ordre d'origine et jamais plus tôt
que prévu. La vue d'ensemble affiche l'heure effective et indique
l'heure d'origine d'une tâche décalée ; le watchdog se base sur cette
heure. Les rythmes horaires et plus courts restent inchangés.

**Mettre en pause et exécution de test :** Les jobs peuvent être mis en
pause puis repris – un job en pause n'arrive plus à échéance, mais reste
visible dans la vue d'ensemble. Une **exécution de test** lance le job
immédiatement hors calendrier ; entre deux exécutions de test s'applique
un bref délai de blocage afin que les exécutions ne se chevauchent pas.

**Journal des exécutions :** Chaque exécution est consignée avec son
début, sa durée et son résultat. Les justificatifs sont conservés pendant
une période réglable (30 jours par défaut) puis purgés automatiquement.

**Watchdog :** Un job de surveillance dédié contrôle le planificateur
lui-même : si des exécutions dues manquent ou si les erreurs
s'accumulent, cela génère des tâches d'exploitation ou des
avertissements. Ainsi, même un planificateur complètement à l'arrêt est
détecté – et pas seulement lorsque des analyses font défaut.

**Recommandation :** Modifiez les plans avec retenue et observez les
prochaines exécutions après chaque replanification. Un compteur
d'erreurs durablement élevé relève du diagnostic, pas de la mise en
pause.
