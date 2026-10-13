---
title: "Transferencia a facturación"
topic: finance.transfers
version: 4
keywords:
    - traspaso a Lexoffice
    - traspaso a DATEV
    - borrador de factura
    - transferir servicios
    - facturar material
    - facturar horas
    - exportación de facturación
    - software de facturación
    - líneas de factura
    - documentos relacionados
    - cadena de documentos
audience: []
modules:
    - module.finance
related:
    - exports.payroll
    - admin.surcharge-rules
    - roles.buchhaltung
    - glossary.core
---

La entrega de facturación traspasa los **tiempos** y **materiales**
facturables al sistema de facturación principal. La encontrará en el
menú en **Entrega de facturación**; la página **Justificantes de
traspaso** enumera todos los traspasos.

Principio básico de la soberanía de facturación: **la factura se crea
en el programa externo principal** (p. ej. Lexoffice, orgaMAX,
sevDesk, easybill o DATEV): WorkDiary solo suministra posiciones
revisadas junto con un justificante de traspaso. Una factura local en
WorkDiary solo existe si no se utiliza ningún software de facturación
externo. Por organización o cliente rige exactamente un **Canal de
facturación**.

Procedimiento típico:

1. **Preparar el traspaso** (estado **Borrador**): elija el
   **Cliente**, el **Canal de traspaso** –**Servicios/tiempo** o
   **Productos/material**, por separado–, el **Destino del traspaso** y
   el **Período de prestación**. El destino se preselecciona según el
   canal de facturación del cliente: **Lexoffice** (borrador de
   factura), **orgaMAX (pedido)**, **sevDesk (borrador de factura)** o
   **easybill (borrador de factura)**; además, siempre está disponible
   la **Exportación de archivo**. Si dirige DATEV, la entrega se realiza
   como paquete de archivo (CSV) mediante la exportación de archivo.
2. Revise las posiciones y pulse **Confirmar el traspaso** (estado
   **Confirmado**). Solo entonces pueden editarse la denominación y el
   texto de servicio, así como unir o quitar posiciones.
3. **Traspasar ahora** → estado **Traspasado** (definitivo). En caso de
   **Fallido**, **Reintentar** devuelve el traspaso al estado
   **Confirmado**; después vuelve a traspasarlo.
4. Los traspasos en estado **Borrador** o **Confirmado** pueden
   anularse con **Anular el traspaso**: las posiciones incluidas
   quedan de nuevo liberadas.

Riesgos y acciones irreversibles:

- **«Traspasado» es definitivo**: las posiciones incluidas quedan
  bloqueadas frente a cambios.
- Las correcciones se realizan mediante operaciones trazables, nunca
  mediante un restablecimiento silencioso: **Cancelar el traspaso**
  libera de nuevo las fuentes y **Crear corrección** genera un traspaso
  de corrección con los mismos tiempos. Un borrador creado en el
  destino no se elimina en el proceso: elimínelo allí manualmente.

Permisos: los traspasos de tiempo y de material están protegidos por
separado (**Preparar y traspasar los tiempos facturables** o
**Preparar y traspasar el material facturable**). La lista la ven las
personas con **Consultar los justificantes de traspaso**; cancelar y
corregir requieren además **Gestionar la configuración financiera**.

## Documentos relacionados

Al final de la página, **Documentos relacionados** muestra todos los
documentos vinculados a este: la oferta y sus versiones, facturas, anticipos y
factura final, abonos y anulaciones, albaranes, órdenes de fabricación y
traspasos. Si un programa de contabilidad ha asumido un documento, su
contraparte aparece allí con el número del programa. También aparecen las
relaciones que comunica el propio programa, por ejemplo un anticipo creado en
Lexoffice. La columna **Relación** indica qué es el documento respecto a este;
«a través de …» nombra el documento por el que existe la relación.
