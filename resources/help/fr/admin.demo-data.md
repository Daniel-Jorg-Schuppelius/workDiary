---
title: "Données de démonstration"
topic: admin.demo-data
version: 1
audience:
    - admin
related:
    - admin.tenants
    - admin.handbook
    - admin.data-transfer
---

Les données de démonstration servent à remplir une organisation avec des
exemples pour les tests et la formation, selon un secteur d'activité au
choix. **Générer (seed)** crée des exemples (clients, entrées de journal…)
pour le secteur choisi ; **Réinitialiser (reset)** remet à zéro une
organisation de démonstration. La réinitialisation n'est autorisée que
pour les organisations marquées démo (`is_demo`) afin de protéger les
données réelles ; sur une organisation de démonstration, elle écrase ou
supprime les données existantes. Les deux actions exigent des permissions
propres et sont consignées dans le journal d'audit.
