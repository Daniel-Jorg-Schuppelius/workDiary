---
title: "Organisations et tenants"
topic: admin.tenants
version: 4
keywords:
    - gestion des tenants
    - multi-tenant
    - multi-sociétés
    - créer une organisation
    - ajouter une société
    - supprimer une organisation
    - désactiver une organisation
    - basculer entre organisations
    - export de données
    - purge
    - changement de plan
    - liste des organisations
audience:
    - admin
related:
    - admin.handbook
    - admin.license
    - admin.roles
    - admin.organization-settings
---

Cette page gère les organisations (tenants), chacune étant une unité
cloisonnée dont toutes les données appartiennent à un seul tenant.
Actions typiques : **créer/modifier**, **désactiver/réactiver**
(réversible, les données sont conservées), **exporter** (portabilité,
art. 20 RGPD), **supprimer définitivement** (purge, art. 17 RGPD) et
**changer** de contexte d'organisation pour les admins globaux. Le
plan ou la licence liée à l'organisation détermine les modules
activés — voir le chapitre **Licence**. Attention : la purge est
irréversible ; proposez d'abord un export et vérifiez les obligations
de conservation — la désactivation est l'alternative sûre.

Approbations : dans la section du même nom, vous définissez quel rôle voit
les étapes d'approbation d'une négociation contractuelle sous
« Approbations », par type d'étape (commercial, technique, RH). Vide, la
valeur par défaut s'applique : Comptabilité, Chef d'équipe, Gestion du
personnel. L'approbation depuis le dossier n'est pas concernée.

## Liste des organisations et votre propre organisation

La liste **Organisations** de tous les tenants est réservée à l'exploitation de
la plateforme : dans le menu système (icône d'engrenage **Système** dans
l'en-tête) sous **Organisation** → **Organisations**. Les opérateurs de la
plateforme sans organisation propre trouvent en outre, dans le menu
d'administration (icône **Administration** dans l'en-tête) sous **Personnel**,
l'entrée **Employés** ; elle mène elle aussi à la liste des organisations.
Lorsqu'un tel administrateur ouvre la liste, WorkDiary rattache son compte à la
première organisation créée – ensuite, **Employés** mène à la gestion des
membres de cette organisation.

Les administrateurs d'une organisation modifient leur propre organisation dans
le menu système sous **Organisation** → **Organisation** (boîte de dialogue
**Modifier l’organisation** ; détails dans le thème « Organisation et
paramètres »). Le **Plan** et le statut actif ne sont définis que par
l'exploitation de la plateforme : les administrateurs d'organisation voient le
plan dans la section **Plan et statut** à titre d'information seulement (« Le
plan suit la licence et est géré par l'exploitant. ») et n'ont pas
d'interrupteur pour le statut actif.
