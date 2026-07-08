---
title: "Gestionar documentos"
topic: documents.manage
version: 1
audience: []
related:
    - forms.fill
    - knowledge.articles
    - glossary.core
---

El módulo de documentos gestiona contratos, certificados, informes de
inspección, manuales y más como **archivos versionados** con metadatos,
vigencia y vínculo a cliente, proyecto, orden o activo. El flujo típico:
**subir el documento** (título, tipo, vigencia y objeto de referencia
opcionales; el archivo es la versión 1), **subir una nueva versión**
cuando cambie (la numeración aumenta y las versiones antiguas se
conservan sin cambios), **descargar** la versión actual o una anterior y
**archivar** cuando ya no se necesite. Los estados son «Borrador»,
«Activo» y «Archivado»; **«Caducado»** se calcula automáticamente a
partir de la fecha «válido hasta» y los documentos por caducar pueden
notificarse mediante reglas. **Eliminar borra el documento con todas sus
versiones** (borrado lógico, solo con permiso); las versiones son
inmutables y las correcciones se hacen siempre con una versión nueva.
