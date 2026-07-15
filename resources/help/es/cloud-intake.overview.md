---
title: "Entrada de documentos en la nube"
topic: cloud-intake.overview
version: 1
audience: []
related:
    - documents.manage
    - admin.integrations
---

WorkDiary LEE documentos de carpetas supervisadas en Dropbox, OneDrive/SharePoint y Google Drive y los enruta a facturas entrantes o al DMS mediante reglas de carpetas.

**Conexiones:** por proveedor se conecta y confirma una cuenta vía OAuth; después se eligen contenedor (unidad/biblioteca) y carpeta raíz. La importación solo corre con al menos una regla válida.

**Reglas:** patrones de ruta con * y ** más variables como {customer_number} asignan archivos a clientes, proyectos, órdenes, activos o contratos existentes — nunca por creación automática. Los casos dudosos van a la bandeja de integración.

**Seguridad:** solo permisos de lectura, tokens cifrados, archivos de origen intactos; los webhooks son solo señales de aviso — la ejecución delta reanudable es la autoritativa.
