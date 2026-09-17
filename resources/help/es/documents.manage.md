---
title: "Gestionar documentos"
topic: documents.manage
version: 1
audience: []
modules:
    - module.documents
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

## Enviar documentos

Los documentos —facturas, presupuestos, albaranes— pueden enviarse directamente
desde el expediente. Cada envío se registra con destinatario, momento y canal,
de modo que después se puede reconstruir **qué se envió, a quién y cuándo**.

El **historial de envíos** pertenece al documento, no al buzón: incluso quien no
tiene acceso a la cuenta de correo ve si se envió y cuándo. Un reenvío genera
una entrada adicional y no sobrescribe la anterior.
