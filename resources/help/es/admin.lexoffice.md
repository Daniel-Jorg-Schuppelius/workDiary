---
title: "Conflictos de Lexoffice"
topic: admin.lexoffice
version: 4
keywords:
    - Lexware Office
    - conflicto de sincronización
    - datos divergentes
    - resolver conflicto
    - conservar valores locales
    - aceptar valores externos
    - conciliación de datos
audience:
    - admin
    - buchhaltung
related:
    - admin.plugins
    - articles.lexoffice
    - invoices.manage
    - admin.integration-inbox
    - inventory.conflicts
---

Aquí resuelve los conflictos de sincronización con Lexoffice. Un
conflicto surge cuando un registro local (WorkDiary) y el contacto de
Lexoffice correspondiente difieren en uno o más campos y en la
configuración de Lexoffice la **Estrategia de conflictos** está en
**Comprobación manual** (valor predeterminado). Los conflictos se
gestionan en la **Bandeja de conciliación**: al abrir esta página se
accede a ella filtrada por la fuente **Lexoffice** y el caso
**Conflicto de campo**.

En la Bandeja de conciliación:

- Para cada conflicto, los campos divergentes aparecen uno junto a otro
  como **Local** y **Remoto**.
- Afecta a clientes y proveedores, es decir, a los contactos procedentes
  de Lexoffice.
- Con el filtro de estado puede volver a consultar también los
  conflictos ya resueltos.

Soluciones para cada conflicto:

- **Adoptar lo remoto**: actualiza el registro local con los valores de
  Lexoffice de los campos divergentes.
- **Mantener local**: conserva los valores locales; los valores
  divergentes de Lexoffice no se aplican.
- **Descartar**: cierra el conflicto sin cambios (p. ej. en caso de
  datos intencionadamente distintos); recibe el estado **Descartado**.

Riesgos: **Adoptar lo remoto** sobrescribe valores locales. Revise con
atención los datos comparados antes de decidir. Tenga en cuenta que, en
las facturas, la soberanía de facturación reside en el programa
externo: WorkDiary solo le suministra los datos.

La estrategia de conflicto se aplica a contactos y artículos. Los
conflictos de artículos no aparecen en la Bandeja de conciliación, sino
en la pestaña **Conflictos** del inventario (**Inventario** →
**Conflictos**): allí elige **Mantener local**, **Adoptar el estado de
Lexoffice** o **Descartar**, con el permiso **Gestionar artículos**.

Permiso: la Bandeja de conciliación está abierta a los administradores
y al rol **Contabilidad**.
