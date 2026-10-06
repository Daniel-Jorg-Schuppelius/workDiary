---
title: "Produits & prestations Lexoffice"
topic: articles.lexoffice
version: 2
audience: []
modules:
    - module.vertrieb
related:
    - articles.master
    - invoices.manage
    - glossary.core
---

Cette page affiche le répertoire des produits et prestations synchronisé
depuis Lexoffice, en lecture seule : la gestion s'effectue dans Lexoffice,
une synchronisation « pull » tient le cache local à jour. Chaque entrée
montre la désignation, le numéro d'article, le type, l'unité, le prix
unitaire net et le taux de TVA ; vous pouvez rechercher, filtrer par type
et statut, et ouvrir les détails dans une boîte de dialogue. Avec les
droits suffisants, la synchronisation peut être lancée manuellement et
indique le nombre d'entrées créées, mises à jour ou archivées, à condition
que Lexoffice soit configuré pour l'organisation.

La stratégie de conflit des paramètres Lexoffice (Lexoffice gagne, local
gagne, vérification manuelle) s'applique aussi à la synchronisation des
articles : avec « vérification manuelle », les articles modifiés localement
dont l'état diffère dans Lexoffice apparaissent comme conflits dans la liste
des conflits du stock (Stock → Conflits). Vous y décidez, article par
article, si l'état local est conservé, si l'état Lexoffice est repris ou si
le conflit est rejeté. La synchronisation manuelle indique le nombre de
nouveaux conflits.
