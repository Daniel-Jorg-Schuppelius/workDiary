---
title: "Vulnérabilités & advisories"
topic: isms.vulnerabilities
version: 2
keywords:
    - faille de sécurité
    - CVE
    - CVSS
    - avis de sécurité
    - bulletin de sécurité
    - CSAF
    - VEX
    - correctif
    - gestion des vulnérabilités
    - exploitabilité
    - SBOM
audience: []
modules:
    - module.isms
related:
    - isms.incidents
    - isms.software
    - isms.risks
    - glossary.core
---

Dans le registre des **vulnérabilités**, vous suivez les vulnérabilités
connues avec leur criticité, leur responsable et leurs échéances, et vous
décidez de manière délibérée de leur exploitabilité.

Déroulement type :

1. **Saisir la vulnérabilité** : titre, facultativement un identifiant
   (p. ex. un numéro CVE), le score CVSS et le composant affecté. La
   criticité est déduite du score CVSS, mais peut être forcée. En option,
   vous reliez un produit de l’inventaire logiciel et fixez une échéance.
2. **Gérer le statut** : de « Ouverte » en passant par « En cours
   d’examen » et « En cours d’atténuation » jusqu’à « Résolue » ; sinon
   « Acceptée » (risque résiduel assumé) ou « Non affecté ».
3. **Décider de l’exploitabilité** : déterminez si la vulnérabilité est
   exploitable dans la configuration concrète. « Exploitable » et « Non
   exploitable » exigent une **justification obligatoire**.

**Importer un avis** (CSAF/VEX) : téléversez un avis lisible par machine
au format JSON. L’import compare les composants concernés à l’inventaire
logiciel et à la dernière nomenclature de version (SBOM) et crée une
entrée de vulnérabilité par correspondance.

Règle importante : une correspondance importée n’est **pas
automatiquement considérée comme exploitable**. Elle démarre en
investigation ; le fait d’être concerné est une décision délibérée et
justifiée. Si un document VEX indique « non affecté », la justification
est reprise.

Preuve : chaque avis original importé est archivé avec une somme de
contrôle. Une nouvelle importation du même fichier produit le même effet
et ne crée pas de doublons.

Autorisations : la consultation requiert des droits de lecture SMSI ; la
gestion et l’import requièrent des droits de gestion SMSI.

Étapes suivantes : les vulnérabilités en retard sont signalées et
escaladées.
