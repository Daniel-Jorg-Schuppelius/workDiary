---
title: "Vulnérabilités & advisories"
topic: isms.vulnerabilities
version: 1
audience: []
related:
    - isms.incidents
    - isms.software
    - isms.risks
    - glossary.core
---

Le **registre des vulnérabilités** recense les vulnérabilités connues avec
criticité, responsabilité et échéances : saisissez titre, identifiant
(p. ex. numéro CVE), score CVSS et composant concerné — la criticité est
déduite du CVSS mais peut être ajustée. Le statut évolue d'« ouvert » à
« corrigé » en passant par « en analyse » et « en correction », ou bien
« accepté »/« non concerné » ; décider qu'une vulnérabilité est
« exploitable » ou « non exploitable » exige une **justification
obligatoire**. Vous pouvez **importer des advisories** (CSAF/VEX, JSON) :
l'import compare les composants concernés à l'inventaire logiciel et à la
dernière SBOM et crée une entrée par correspondance — un import ne vaut
**jamais** exploitabilité automatique, et chaque advisory original est
archivé avec somme de contrôle (réimport idempotent, sans doublons).
Les vulnérabilités en retard sont signalées et escaladées.
