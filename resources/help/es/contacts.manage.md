---
title: "Clientes & proveedores"
topic: contacts.manage
version: 2
audience: []
modules:
    - module.vertrieb
schema: process
related:
    - projects.manage
    - invoices.manage
    - admin.import
    - communication.notes
---

## Objetivo y contexto

Clientes y proveedores son los datos maestros centrales de WorkDiary:
proyectos, órdenes, facturas, comunicación, viajes y análisis cuelgan
de ellos. Unos datos limpios deciden si los procesos posteriores —
del registro de tiempos a la entrega DATEV — funcionan sin retrabajo.

## Requisitos

- El derecho a gestionar clientes o proveedores (normalmente
  administración o ventas).
- Para importar en vez de crear a mano: el asistente de importación
  CSV.
- Identificadores externos (número de deudor, códigos de las
  integraciones de facturación) si se entregan documentos.

## Procedimiento recomendado

1. **Buscar antes de crear:** comprueba si el socio comercial ya
   existe — así no nacen duplicados. Los duplicados existentes se
   pueden fusionar; el historial acompaña.
2. Crea el contacto con nombre, dirección e interlocutores.
3. Completa datos de pago y facturación e identificadores externos —
   dirigen la facturación y la entrega contable.
4. Vincula proyectos, ubicaciones y acuerdos según vayan surgiendo.

![Lista de clientes con números, datos de contacto, tarifas horarias y número de proyectos](media/kunden/kundenliste.png)
*La lista de clientes: datos maestros, tarifa horaria y proyectos vinculados por socio.*

## Ejemplo práctico

Un proveedor de TI crea «Müller GmbH» con dirección de facturación,
plazo de pago y el número de deudor de la asesoría. Cuando más tarde
se crea el primer lote DATEV, ni un solo documento queda bloqueado
por datos maestros incompletos.

## Errores habituales

- **Crear duplicados** por no buscar antes — análisis e historial se
  fragmentan.
- **Borrar relaciones históricas:** mejor desactivar o archivar los
  contactos en desuso; documentos y tiempos siguen trazables.
- **Cambiar datos de facturación «de paso»:** los cambios valen hacia
  delante; los documentos ya creados conservan a propósito su estado
  documentado.

## Efectos y próximos pasos

Los cambios de datos maestros solo actúan hacia delante — las
entregas cerradas quedan intactas. Después: crear los proyectos del
cliente, revisar los datos de facturación y usar la importación CSV
para volúmenes grandes.
