---
title: "Registre des immobilisations et amortissement"
topic: accounting.fixed-assets
version: 1
audience:
    - admin
    - geschaeftsfuehrung
    - buchhaltung
modules:
    - module.finance
related:
    - accounting.closing
    - accounting.posting
    - accounting.overview
---

Le **registre des immobilisations** est la vue comptable des biens durables :
coût d’acquisition ou de production, durée d’utilisation, valeur résiduelle
et comptes concernés. Il répond à la question « que vaut encore cette machine
à la date de clôture » — et non « où se trouve-t-elle et quand a-t-elle été
entretenue ». Cela relève de la fiche d’équipement.

**L’équipement et l’immobilisation sont deux choses différentes.** Le lien
est possible mais pas obligatoire : un agencement peut être immobilisé sans
fiche d’équipement, et un équipement de faible valeur peut être amorti
immédiatement. Les confondre produit soit des immobilisations sans valeur
comptable, soit des équipements qui n’existent pas dans les comptes.

## Ce qui est saisi ici

1. **Acquisition** : date, coût, devise. Le numéro est attribué par le
   système.
2. **Durée d’utilisation en mois** et **méthode d’amortissement**. Ensemble
   elles déterminent la répartition de la valeur sur les années.
3. **Valeur résiduelle**, s’il subsiste une valeur symbolique ou un produit
   de cession attendu à la fin de la durée d’utilisation.
4. **Comptes** d’immobilisation et d’amortissement — ils déterminent où
   s’impute l’écriture d’amortissement.

## Comment naît l’amortissement

Les lignes d’amortissement sont **calculées, non saisies**. La clôture les
propose par immobilisation et exercice ; la comptabilisation passe
exclusivement par la boîte de réception des écritures.

**Le registre ne comptabilise rien de lui-même.** C’est voulu : un
amortissement est une décision de clôture, pas un effet secondaire de la
tenue des données de base. Créer une immobilisation ne modifie aucun solde.

## Amortissement dégressif et amortissement exceptionnel

L'**amortissement dégressif** déduit chaque année un pourcentage fixe de la
valeur comptable. Il n'est autorisé que pour les acquisitions réalisées dans
les périodes prévues par la loi ; la boîte de dialogue indique le taux maximal
pour votre date d'acquisition et la durée d'utilisation. Dès que la répartition
linéaire de la valeur résiduelle est plus élevée, le plan passe de lui-même à
l'amortissement linéaire.

L'**amortissement exceptionnel selon le § 7g** se saisit sur l'immobilisation
sous forme de montant par exercice — l'année d'acquisition et les quatre
suivantes, au total 40 % du coût d'acquisition au maximum. Ensuite, la valeur
résiduelle est répartie sur la durée d'utilisation restante. L'application ne
vérifie pas si votre entreprise remplit les conditions (plafond de bénéfice) ;
veuillez le clarifier avec votre conseiller fiscal. Une année dont
l'amortissement est déjà comptabilisé ne peut plus être modifiée.

## Sortie

Une sortie (vente, mise au rebut, vol) est enregistrée avec sa date.
L’immobilisation ne **disparaît pas** du registre — l’historique reste
lisible, sans quoi un rapprochement ultérieur avec le bilan serait
impossible.

## Catégories et immobilisation à partir d’un justificatif

Sous « Catégories d’immobilisations », vous créez des valeurs par défaut,
par exemple « Véhicules » ou « Logiciels » : durée d’utilisation, méthode et
comptes. Si vous choisissez une catégorie à la création d’une immobilisation,
WorkDiary remplit à partir d’elle les champs laissés vides. Les
immobilisations existantes gardent leurs valeurs si vous modifiez une
catégorie ensuite.

Sur une facture fournisseur et sur une dépense approuvée, « Enregistrer comme
immobilisation » crée directement une immobilisation. Désignation, date et
montant hors taxes sont préremplis, et l’immobilisation renvoie au
justificatif. Un justificatif donne au plus une immobilisation.
