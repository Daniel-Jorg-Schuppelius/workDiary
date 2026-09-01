---
title: "Vulnerabilidades y avisos"
topic: isms.vulnerabilities
version: 1
audience: []
modules:
    - module.isms
related:
    - isms.incidents
    - isms.software
    - isms.risks
    - glossary.core
---

En el **registro de vulnerabilidades** gestionas vulnerabilidades
conocidas con criticidad, responsabilidad y plazos, y decides de forma
consciente sobre su explotabilidad. Registra la vulnerabilidad con
título, identificador opcional (p. ej. CVE), valor CVSS y componente
afectado; la criticidad se deriva del CVSS pero puede ajustarse. El
estado va de «abierta» a «resuelta», o bien «aceptada» o «no afectada»;
las decisiones «explotable» y «no explotable» exigen una
**justificación obligatoria**. Puedes **importar avisos** (CSAF/VEX) en
JSON: la importación coteja los componentes contra el inventario de
software y la SBOM, y crea una entrada por coincidencia — sin marcarla
automáticamente como explotable. Cada aviso original se guarda con suma
de verificación y las reimportaciones no crean duplicados.
