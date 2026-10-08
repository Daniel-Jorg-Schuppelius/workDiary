---
title: "Gestion des licences"
topic: admin.license
version: 3
keywords:
    - clé de licence
    - forfait
    - abonnement
    - changer de forfait
    - mise à niveau
    - modules complémentaires
    - limite utilisateurs
    - période test
    - licence expirée
    - compte bloqué
    - feature flags
    - données de facturation
audience:
    - admin
    - geschaeftsfuehrung
related:
    - admin.handbook
    - admin.tenants
---

La page de licence indique ce que votre installation est autorisée à
faire : **Plan** (free/pro/enterprise), **limites d'utilisateurs et
d'organisations**, **Modules** activés et **date d'expiration**.

Voici comment tout s'articule :

- La **licence est la source** du plan et des modules complémentaires
  (add-ons) ; l'association plan → modules se trouve dans la
  configuration. Les nouveaux modules d'un plan sont ainsi disponibles
  sans qu'il soit nécessaire de réémettre la licence.
- Les **licences liées à l'organisation** peuvent être installées et
  retirées pour chaque organisation ; en l'absence de licence
  d'organisation, la licence globale s'applique en solution de repli.
- **Sans licence valide**, l'installation fonctionne strictement en
  plan Free.

Actions typiques :

1. Vérifier le statut de la licence et les modules.
2. Surcharger de manière ciblée les **Feature flags** (interrupteurs de
   surcharge).
3. **Installer/supprimer** une licence d'organisation ou – si votre
   installation y est autorisée – **émettre** de nouvelles licences
   (titulaire de la licence, e-mail, plan, add-ons, expiration, limites,
   organisation, domaine).

Statut du locataire (SaaS) :

- Le **Statut du locataire** indique si l'organisation est en
  **Période d'essai**, **Actif** ou **Suspendu**. Si aucun statut n'est
  fixé, il est déduit de la période d'essai et de l'expiration de la
  licence (valide / en période de grâce / expirée).
- Un administrateur de la plateforme peut définir manuellement le
  statut sur **Actif**, **Période d'essai** ou **Suspendu**, ou le
  libérer à nouveau via *Automatique (déduire)*.
- En cas de statut **Suspendu** (ou de licence définitivement expirée),
  les **actions en écriture sont désactivées** ; la lecture reste
  possible. Les pages de licence et de déconnexion restent accessibles
  afin que le blocage puisse être levé.
- La **limite d'utilisateurs** de la licence est appliquée lors de la
  création de nouveaux membres : si la limite est atteinte, la création
  est bloquée avec un message.

Bon à savoir :

- Les rétrogradations de plan bloquent des modules via le contrôle
  d'accès par plan (plan gating) ; les contenus des modules soumis à
  une obligation de conservation sont conservés.
- Aucun fichier n'est téléversé – les licences sont saisies sous forme
  de clés signées.

## Données de facturation et changement d'offre

Sous « Données de facturation », vous gérez le destinataire de la facture,
l'e-mail, l'adresse, le numéro de TVA intracommunautaire et la référence de
commande pour la facturation par l'exploitant. Vous y demandez aussi une
autre offre ou des modules complémentaires ; il existe une demande ouverte
par organisation, que vous pouvez retirer. L'exploitant la traite en
émettant une nouvelle licence.
