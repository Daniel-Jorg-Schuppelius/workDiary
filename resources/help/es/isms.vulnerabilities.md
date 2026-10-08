---
title: "Vulnerabilidades y avisos"
topic: isms.vulnerabilities
version: 2
keywords:
    - fallo de seguridad
    - agujero de seguridad
    - CVE
    - CVSS
    - aviso de seguridad
    - CSAF
    - VEX
    - parche
    - gestión de vulnerabilidades
    - explotabilidad
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

En el registro de **Vulnerabilidades** gestiona las vulnerabilidades
conocidas con su criticidad, responsable y plazos, y decide de forma
consciente sobre su explotabilidad.

Proceso habitual:

1. **Registrar la vulnerabilidad**: título, opcionalmente un
   identificador (p. ej., un número CVE), la puntuación CVSS y el
   componente afectado. La criticidad se deriva de la puntuación CVSS,
   pero puede sobrescribirse. Opcionalmente, vincula un producto del
   inventario de software y fija un plazo.
2. **Mantener el estado**: desde «Abierta», pasando por «En revisión» y
   «En mitigación», hasta «Resuelta»; alternativamente, «Aceptada»
   (riesgo residual asumido conscientemente) o «No afectado».
3. **Decidir la explotabilidad**: determine si la vulnerabilidad es
   explotable en la configuración concreta. «Explotable» y «No
   explotable» requieren una **justificación obligatoria**.

**Importar aviso** (CSAF/VEX): suba un aviso legible por máquina en
formato JSON. La importación compara los componentes afectados con el
inventario de software y con la última lista de materiales de la versión
(SBOM) y crea una entrada de vulnerabilidad por cada coincidencia.

Regla importante: una coincidencia importada **no se considera
automáticamente explotable**. Empieza en investigación; la afectación es
una decisión consciente y justificada. Si un documento VEX indica «no
afectado», se adopta su justificación.

Evidencia: cada aviso original importado se archiva con una suma de
verificación. Volver a importar el mismo archivo tiene el mismo efecto y
no genera duplicados.

Permisos: la consulta requiere permisos de lectura del SGSI; la gestión
y la importación requieren permisos de gestión del SGSI.

Próximos pasos: las vulnerabilidades vencidas se notifican y escalan.
