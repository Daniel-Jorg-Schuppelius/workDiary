---
title: "Services d'IA"
topic: ai.services
version: 3
keywords:
    - IA
    - intelligence artificielle
    - assistant IA
    - modèle de langage
    - LLM
    - Ollama
    - suggestions de texte
    - traduire les positions
    - reformuler
    - glossaire
    - activer les fonctions IA
audience: []
modules:
    - module.ai
related:
    - invoices.manage
    - quotes.overview
---

L'assistance IA est optionnelle et désactivée par défaut. Sous
**Administration → Services d'IA**, vous connectez des fournisseurs
(cloud ou local, p. ex. Ollama), activez des capacités individuelles et
définissez par capacité quelles connexions sont autorisées et laquelle est
celle par défaut.

**Qui peut utiliser l'IA :** Les suggestions, traductions et actions groupées
ne sont disponibles que pour les personnes disposant du droit « Demander des
suggestions d'IA ». Il fait partie de tous les rôles standard des
collaborateurs — de la direction, la comptabilité et les ressources humaines
au service interne, au service externe et au centre d'appels —, mais pas du
portail client ni du rôle de support en lecture seule. Pour vos propres
rôles, vous l'attribuez sous **Rôles & droits**. Seules les personnes
disposant de « Gérer les connexions aux fournisseurs d'IA et le routage des
capacités » gèrent les connexions, les autorisations et le
budget.

**Confidentialité :** L'aperçu du flux de données montre par capacité
quelles classes de données vont vers quel fournisseur. Les connexions
cloud sont bloquées en sensibilité élevée et dans le profil de soins
ambulatoires ; les offres utilisant les entrées pour l'entraînement ne
peuvent pas être connectées. Les clés API sont stockées chiffrées et
jamais affichées.

**Remplacer une clé :** Si une clé API expire ou est compromise, ouvrez
« Modifier la connexion » et saisissez-la sous « Nouvelle clé API ».
Laissé vide, le champ conserve la clé actuelle. La nouvelle clé est
vérifiée immédiatement ; la connexion est conservée et son affectation par
défaut par capacité n’est pas perdue.

**Mémoire IA :** Termes de glossaire, règles de style et paires
d'exemples par organisation, client ou capacité améliorent les
suggestions — sans entraîner de modèles tiers. L'apprentissage n'a lieu
qu'après votre confirmation (dialogue « Mémoriser ? »).

**Textes de position :** Dans les brouillons de factures et de devis,
l'IA crée des suggestions de texte par position (traductions incluses).
Rien n'est appliqué avant le clic — quantités, prix et taxes restent
intacts.

**Actions groupées :** Sur une facture en brouillon, « Tout traduire » traduit
chaque ligne, et dans un procès-verbal « Affiner tous les points » reformule
chaque point avec texte. Les deux s’exécutent en arrière-plan. Chaque ligne
et chaque point reçoit sa propre proposition, que vous acceptez ou rejetez
individuellement ; rien n’est appliqué automatiquement.
