---
title: "Conflictos de Lexoffice"
topic: admin.lexoffice
version: 10
keywords:
    - Lexware Office
    - conflicto de sincronización
    - datos divergentes
    - resolver conflicto
    - conservar valores locales
    - aceptar valores externos
    - conciliación de datos
    - espejo de comprobantes
    - sincronización de comprobantes
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

La estrategia de conflicto se aplica a los contactos. Lexoffice manda en
los artículos: la sincronización adopta cada versión más reciente de
Lexoffice, por lo que no hay conflictos de artículos.

**Direcciones con «Gana Lexoffice»:** Lexoffice gestiona las direcciones sin
identificador propio; WorkDiary las asigna por tipo y orden. Una dirección de
facturación o de entrega creada en WorkDiary adopta en la primera
sincronización la dirección de Lexoffice correspondiente en lugar de crearse
por segunda vez: la dirección de facturación principal adopta la primera de
Lexoffice. Después solo esa es la principal; las demás direcciones de WorkDiary
se conservan como adicionales.

**Tipo de impuesto al traspasar:** las facturas y borradores de factura de la
entrega de facturación llevan el tipo de impuesto de su caso fiscal: servicio
a una empresa de la UE como «servicios externos § 13b UE», § 13b nacional
como «obras de construcción § 13b», entrega intracomunitaria, entrega de
exportación, servicio no sujeto en Alemania como «servicio a tercer país»,
pequeña empresa como «exento», con el 0 % por posición y la mención como
nota fiscal. Salvo «exento», Lexoffice solo acepta estos tipos de impuesto
con un contacto creado allí. Si un servicio no sujeto se sitúa en la UE (por
ejemplo, un inmueble en otro país de la UE), Lexoffice no tiene un tipo de
impuesto adecuado: el traspaso se bloquea con el motivo. El comprobante a
partir de tiempos en el cliente es un comprobante contable: por tipo
impositivo, el importe neto, el impuesto y la categoría contable del caso
fiscal («Dienstleistungen», «Fremdleistungen § 13b», «Dienstleistungen an
Drittländer», «Einnahmen als Kleinunternehmer»), con el número de comprobante
`ZE-<número de cliente>-<fin de la prestación>-<número correlativo>`. Para el
§ 13b nacional, las operaciones exentas según el § 4, varios casos fiscales o
una moneda extranjera se bloquea con el motivo; esos tiempos van por la
entrega de facturación.

Espejo de comprobantes: la sincronización de comprobantes refleja los
comprobantes de Lexoffice en WorkDiary. También se reflejan los
comprobantes de contactos de Lexoffice que aún no están vinculados a ningún
cliente o proveedor, al principio sin cliente ni proveedor; en cuanto el
contacto se vincula, la siguiente sincronización los asigna. Un cliente solo
se asigna a comprobantes de venta y un proveedor solo a comprobantes de
compra, aunque un contacto esté vinculado a ambos. La sincronización de
líneas guarda además el importe neto y, en un abono, su referencia a la
factura. La ejecución se inicia manualmente en la **configuración de
Lexoffice** (Plugins → Lexoffice) con **Sincronizar comprobantes ahora**:
obtiene en segundo plano los comprobantes nuevos y modificados, la
sincronización completa diaria se mantiene; el botón requiere el permiso
**Sincronizar los justificantes con Lexoffice** y credenciales guardadas.

Permiso: la Bandeja de conciliación está abierta a los administradores
y al rol **Contabilidad**.

**Soberanía de datos:** Si, según la soberanía de datos, otro programa lidera clientes y proveedores, la sincronización de contactos no aplica campos ni crea contactos; la vinculación y el número de contacto de Lexoffice se mantienen. El estado retenido espera en la bandeja de integraciones a **Asumir una vez**.
