---
title: "Registre des immobilisations et amortissement"
topic: accounting.fixed-assets
version: 1
audience:
    - admin
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

## Sortie

Une sortie (vente, mise au rebut, vol) est enregistrée avec sa date.
L’immobilisation ne **disparaît pas** du registre — l’historique reste
lisible, sans quoi un rapprochement ultérieur avec le bilan serait
impossible.
