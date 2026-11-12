---
title: "Contabilidad local"
topic: accounting.overview
version: 6
keywords:
    - libro mayor
    - contabilidad general
    - teneduría de libros
    - configurar contabilidad
    - partida doble
    - contabilidad de caja
    - fecha de inicio contable
    - sustituir software contable
    - contabilidad integrada
    - plan contable
    - SKR03
    - SKR04
audience:
    - admin
    - geschaeftsfuehrung
    - buchhaltung
modules:
    - module.finance
schema: process
related:
    - accounting.posting
    - accounting.closing
    - finance.datev-bookings
---

## Objetivo y contexto

La contabilidad local lleva un libro mayor propio dentro de WorkDiary
— para organizaciones sin software contable separado. No sustituye ni
a los plugins contables ni a su soberanía de datos. Tres preguntas se
mantienen estrictamente separadas: **soberanía de facturación**
(¿quién emite facturas?), **soberanía de datos maestros** (¿quién
lleva clientes y proveedores?) y **soberanía de asiento** (¿quién
lleva el mayor?) — por periodo manda WorkDiary o exactamente un
sistema externo.

## Requisitos

- Rol de **contabilidad** o administración.
- La decisión por un perfil: contabilidad de caja (EÜR) o partida
  doble.
- Moneda base, ejercicio e inicio de asientos (fecha de corte).
- Ningún sistema externo con soberanía de asiento en el mismo
  periodo.

## Procedimiento recomendado

1. Abrir **Ventas y facturación** → **Contabilidad** → **Configuración** y
   elegir el perfil.
2. Fijar moneda base, ejercicio e inicio de asientos.
3. Recorrer el **preflight**: comprueba que la organización pueda
   asentar sin lagunas desde la fecha de corte.
4. **Activar** la contabilidad local solo cuando ningún punto siga en
   rojo.
5. Desde ahí los asientos van por el diario (ver «Asentar»), el
   cierre por la página de cierre.

![Configuración de la contabilidad local con elección de perfil y preflight](media/buchhaltung/buchhaltung-einrichtung.png)
*La configuración: perfil contable a la izquierda, preflight a la derecha — solo se activa sin puntos rojos.*

## Ejemplo práctico

Un pequeño taller rescinde su software contable a fin de año: en
diciembre configura el perfil EÜR, completa el preflight y fija el
inicio de asientos al 1 de enero. Los documentos de diciembre quedan
en el sistema antiguo — desde enero asienta WorkDiary.

## Errores habituales

- **Querer asentar con efecto retroactivo:** los documentos previos a
  la fecha de corte son historia y no se reasientan.
- **Doble soberanía de asiento:** asentar en paralelo en el sistema
  antiguo y en WorkDiary crea dos verdades — el preflight lo impide a
  propósito.
- **Forzar la activación con puntos en rojo** — las lagunas le
  alcanzan en el primer cierre.

## Efectos y próximos pasos

Con la activación WorkDiary pasa a ser el mayor rector desde la fecha
de corte: diario, partidas abiertas y cierre se apoyan en él.
Después: conocer la lógica de asientos y la entrada de documentos
(«Asentar») y planificar el primer cierre mensual.

## Plan contable

Las cuentas de la contabilidad local se gestionan en **Ventas y facturación** →
**Contabilidad** → **Plan contable**. La entrada aparece en cuanto su
organización lleva o ha llevado la contabilidad local.

- **Plan contable desde plantilla:** elija en **Plantilla** un extracto del
  SKR03 o del SKR04 y pulse **Aplicar plantilla**. Se crean cuentas, códigos de
  impuesto y las reglas contables correspondientes, de modo que la bandeja
  contable funciona de inmediato; las cuentas y reglas existentes no cambian.
  La plantilla es un punto de partida para Alemania – la elección de cuentas y
  la asignación fiscal deben revisarse profesionalmente antes del primer
  asiento.
- **Crear cuenta** y **Editar cuenta:** **Cuenta** (el número de cuenta, único
  por organización), **Denominación**, **Tipo de cuenta**, **Sentido del
  saldo** (prerrellenado según el tipo de cuenta), **Cuenta DATEV** (solo para
  la exportación), las características **Partidas abiertas**, **Banco**,
  **Caja**, **Regularización** y **Centro de coste obligatorio**, para la
  contabilidad de caja **Línea ingresos-gastos** y **Parte deducible (%)**, y
  una **Descripción**. Los asientos en cuentas con la característica
  **Partidas abiertas** aparecen en la lista de partidas abiertas.
- **Desactivar** en lugar de eliminar: una cuenta desactivada conserva sus
  asientos, pero ya no se puede elegir para otros nuevos. La lista muestra por
  defecto **solo activas**; la búsqueda (número, denominación) y el filtro por
  tipo de cuenta acotan más.
- **Importar plan de cuentas:** un archivo CSV con fila de cabecera y las
  columnas `number`, `name` y `type`, opcionales `normal_balance`,
  `is_open_item`, `datev_account`, `euer_category` y `deductible_percent`. Los
  números existentes se actualizan, las cuentas nuevas se crean y no se elimina
  nada; las líneas erróneas se omiten y se cuentan.
- **Códigos de impuesto:** la página lista los códigos de impuesto con tipo,
  cuenta de impuesto, vigencia y sus casillas de la declaración de IVA
  alemana. **Crear código de impuesto** registra **Código**, **Denominación**,
  **Sentido**, **Tipo impositivo (%)**, **Cuenta de impuesto**, **Válido
  desde** y las casillas de **Base imponible** e **Importe del impuesto** –
  una ayuda para el cuadre, no el formulario. Con **Editar** se cambian en
  cualquier momento la denominación y las casillas. El tipo, el sentido y la
  cuenta de impuesto de un código ya utilizado en asientos solo cambian a
  partir de una nueva fecha de efecto: la versión anterior termina el día
  anterior, los asientos existentes no cambian, y las reglas de
  contabilización y los valores por defecto de las cuentas siguen a la nueva
  versión. **Desactivar** retira un código de la selección; los asientos
  existentes lo conservan.
  Los asientos de la bandeja contable llevan el código y el importe del
  impuesto en la línea de ingreso o de gasto. Para las ventas sin impuesto, la
  bandeja elige el código según la categoría fiscal del justificante: entrega
  intracomunitaria (casilla 41), servicio con inversión del sujeto pasivo
  (21), exportación (43), no sujeto (45); «exento» sin casilla aparece como
  caso por aclarar. Los pequeños empresarios no reciben código.
- **Inversión del sujeto pasivo:** para la dirección **IVA repercutido y
  soportado (inversión del sujeto pasivo)** el código de impuesto registra
  además **Cuenta de IVA soportado**, **Caso fiscal en facturas recibidas** y
  **Casilla del IVA soportado**. Las plantillas traen el § 13b, apdos. 1 y 2
  (19 %) y la adquisición intracomunitaria (19 % y 7 %) con sus cuentas, además
  del código de la casilla 60 para ventas en las que el cliente nacional debe
  el impuesto. Los planes de cuentas existentes de la plantilla los reciben con
  la actualización.
- **DATEV:** la **Clave fiscal DATEV** del código indica la clave fiscal del
  lote DATEV (p. ej. 9 para IVA soportado 19 %, 94 para el § 13b). El
  **Automatismo DATEV** de la cuenta indica el código cuyo impuesto calcula
  DATEV por sí mismo en esta cuenta (cuenta automática como 8400 en el SKR03);
  ahí el lote no pone ninguna clave.

**Permiso:** ver con **Consultar la contabilidad**; plantilla, importación y
todos los cambios en cuentas y códigos de IVA con **Configurar la
contabilidad**.
