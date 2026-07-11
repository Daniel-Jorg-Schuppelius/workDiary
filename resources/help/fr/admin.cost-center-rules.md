---
title: "Règles de centres de coûts"
topic: admin.cost-center-rules
version: 1
audience:
    - admin
related:
    - exports.payroll
    - org.teams
---

Les règles de centres de coûts attribuent automatiquement un **centre de
coûts** aux saisies de temps lors de l'export des temps (p. ex. pour le
bureau de paie) – sans que personne n'ait à retoucher manuellement chaque
saisie.

**Structure d'une règle :** Chaque règle se compose d'exactement **une
source** – soit un utilisateur, **soit** une équipe ; si les deux restent
vides, la règle agit comme **valeur par défaut de l'organisation**. S'y
ajoutent le code du centre de coûts et une priorité. Les règles sont
gérées par les administrateurs ainsi que par la comptabilité ou le bureau
de paie disposant de l'autorisation correspondante.

**Ordre de résolution :** Lors de l'export, la résolution s'effectue pour
chaque personne de la règle la plus spécifique à la plus générale :

- **Règle utilisateur** – l'emporte toujours lorsqu'elle existe.
- **Règle d'équipe** – s'applique si la personne est membre de l'équipe.
- **Valeur par défaut de l'organisation** – la règle sans utilisateur ni
  équipe.
- Si aucune règle ne correspond, le centre de coûts reste **vide** dans
  l'export.

**La priorité comme critère de départage :** Si plusieurs règles entrent
en ligne de compte au même niveau (p. ex. parce qu'une personne est membre
de plusieurs équipes ayant chacune leur propre règle), c'est la règle
avec la **priorité la plus élevée** qui l'emporte ; à priorité égale, la
règle créée en premier. Attribuez donc des écarts de priorité parlants
(p. ex. par pas de 100) afin de pouvoir intercaler ultérieurement de
nouvelles règles.

**Interaction avec les données de base :** Vous gérez les centres de
coûts comme données de base, avec code et libellé par organisation. Les
règles enregistrent actuellement le code sous forme de texte – veillez
donc à ce que les codes des règles correspondent aux données de base et
adaptez les règles lorsque vous renommez ou désactivez des centres de
coûts.

**Recommandation :** Commencez par une valeur par défaut de
l'organisation, complétez par des règles d'équipe pour les services
disposant de leur propre centre de coûts et n'utilisez les règles
utilisateur que pour de véritables exceptions. Après toute modification,
vérifiez un export d'essai avant que les données ne partent au bureau de
paie.
