---
title: "Connecter DATEV Online"
topic: admin.datev-online
version: 1
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
---

L'intégration transfère les lots comptables clôturés et les images de
pièces directement vers DATEV Unternehmen online — sans télécharger ni
téléverser de fichiers à la main.

**Prérequis :** Un enregistrement d'application chez DATEV (ID client et
secret client du portail développeurs DATEV) et un utilisateur DATEV ayant
accès au mandant. Saisissez les identifiants dans les paramètres du plugin
et enregistrez dans l'application DATEV l'adresse de redirection qui y est
indiquée. Tant que DATEV n'a pas validé l'usage en production, laissez «
Utiliser le sandbox » activé.

**Se connecter et choisir le mandant :** « Se connecter avec DATEV » mène
à la connexion DATEV et revient. Choisissez ensuite le mandant (numéro de
conseiller-numéro de mandant) dans la liste des mandants qui vous sont
autorisés.

**Lots comptables :** Les lots clôturés de l'export DATEV peuvent être
remis comme import EXTF avec « Transférer vers DATEV ». Les numéros de
conseiller et de mandant du lot doivent correspondre au mandant connecté.
DATEV traite l'import en arrière-plan ; le traitement nocturne vérifie le
résultat, « Vérifier l'état de l'import » le fait immédiatement. Un import
échoué peut être retransféré après correction.

**Images de pièces :** Une fois activé, le traitement nocturne transfère
les factures émises comme « Rechnungsausgang » et les factures reçues
comme « Rechnungseingang » — chaque pièce une seule fois et uniquement à
partir de la date définie (par défaut : le jour de la connexion). «
Transférer maintenant » lance le traitement immédiatement.

**Déconnecter :** La connexion peut être coupée à tout moment ; les
données déjà transférées restent dans DATEV.
