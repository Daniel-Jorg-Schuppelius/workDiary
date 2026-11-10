---
title: "Données de démonstration"
topic: admin.demo-data
version: 3
keywords:
    - données exemple
    - données de test
    - jeu de données
    - organisation de démo
    - réinitialiser la démo
    - formation
    - présentation
    - environnement de test
audience:
    - admin
related:
    - admin.tenants
    - admin.handbook
    - admin.data-transfer
---

Les données de démonstration servent à remplir une organisation avec des
exemples pour les tests, la formation et les présentations, selon un
**secteur type** au choix : chaque profil de branche en possède exactement
un, avec ses clients, projets, une intervention principale complète, du
matériel, un actif, un protocole signé et une exécution de procédure.
**Créer une organisation de démonstration** (administrateur de plateforme)
crée une organisation nouvelle et isolée ; **Générer (seed)** remplit
l'organisation courante encore vide ; **Réinitialiser (reset)** supprime
et recrée les données d'une organisation de démonstration en conservant
secteur et étendue. Sans la case « Présenter l'étendue complète », la
démonstration suit la recommandation de modules du profil et ne crée des
données que pour les modules actifs. Les modules que de nombreux profils
recommandent contiennent eux aussi des données : plannings avec échange
d'équipe, formulaires avec soumissions, articles de la base de
connaissances, stock avec seuils de réapprovisionnement, notes de frais
avec carnet de bord, temps de travail de plusieurs personnes pour les
analyses d'équipe, lieux d'intervention de la géolocalisation, contrats
avec échéances, sites immobiliers et un devis quantitatif (GAEB) ; clients,
lieux et matériel proviennent du secteur modèle, les coordonnées de la
géolocalisation sont fictives. Le dialogue indique à l'avance sous
quelle licence la démonstration fonctionnera : si l'instance peut délivrer
des licences, l'organisation reçoit une licence temporaire ; sinon la
licence de l'installation s'applique, et sans les deux la démonstration
fonctionne au niveau Free avec la plupart des modules verrouillés. La
réinitialisation n'est autorisée que pour les organisations marquées démo
(`is_demo`) et y écrase les données existantes ; avec un délai de
conservation configuré, le planificateur supprime définitivement les
organisations de démonstration expirées. Toutes les actions exigent des
permissions propres et sont consignées dans le journal d'audit.
