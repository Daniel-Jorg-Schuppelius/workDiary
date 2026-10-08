---
title: "Requisitos y SoA"
topic: isms.requirements-soa
version: 3
keywords:
    - declaración de aplicabilidad
    - aplicabilidad
    - catálogo de requisitos
    - requisitos normativos
    - Anexo A
    - ISO 27001
    - ISO 9001
    - ISO 27701
    - importar catálogo
    - justificación de exclusión
audience: []
modules:
    - module.isms
related:
    - isms.overview
    - isms.controls
    - isms.conformity
    - glossary.core
---

Aquí gestiona el catálogo de requisitos y la **declaración de
aplicabilidad (DdA)** por alcance. Encontrará la página en **SGSI** →
**Gobernanza** → **Requisitos & DdA**.

Proceso habitual:

1. **Cargar catálogo normativo**: elegir y cargar un **Perfil
   normativo**: ISO/IEC 27001:2022 con el anexo A completo, además de
   ISO/IEC 27701, ISO 9001, ISO 22301, ISO 45001, ISO 37301 e ISO/IEC
   42001 con sus capítulos principales 4 a 10, así como el NIST
   Cybersecurity Framework 2.0. Solo se cargan el número y el título
   breve, sin textos normativos. Una nueva carga nunca sobrescribe los
   requisitos existentes ni las declaraciones DdA mantenidas. Como
   alternativa, **Importar OSCAL** incorpora un catálogo desde un archivo
   JSON.
2. Opcionalmente, añadir requisitos propios con **Añadir requisito**; su
   **Origen** es entonces «Requisito propio» en lugar de «Catálogo de
   referencia».
3. **Crear declaraciones DdA**: crea las declaraciones que faltan para
   todos los requisitos del alcance elegido; las existentes no cambian.
   Después, mantenga cada declaración con **Editar declaración DdA**.
4. Utilizar la vista imprimible **DdA** (**Imprimir / guardar PDF**) para
   evidencias y auditorías; **Exportar (CSV)** y **Exportar (JSON)**
   proporcionan los datos como archivo.

Campos importantes por requisito: **Norma**, **Edición**, **N.º de
ref.** (p. ej. «A.5.1») y un **Título** propio; deliberadamente sin texto
normativo.

Por declaración DdA:

- **Aplicable** sí/no: en caso de «no», la **Justificación** es
  obligatoria y el **Estado de implantación** pasa automáticamente a
  **«No aplicable»**.
- **Estado de implantación**: «Abierto», «Parcialmente implantado»,
  «Implantado», «No aplicable».
- **Nota de evidencia**: referencia a una evidencia o un documento.

Permisos: el permiso **Ver registros del SGSI (riesgos, medidas, DdA)**
permite la consulta. La importación del catálogo y el mantenimiento
requieren **Gestionar el SGSI (riesgos, medidas, importación del
catálogo)**.

Próximos pasos: vincule los requisitos con **Medidas** neutrales
respecto a las normas (columna **Medidas vinculadas**); así se crea el
puente entre el «qué» de la norma y el «cómo» de su implantación.
