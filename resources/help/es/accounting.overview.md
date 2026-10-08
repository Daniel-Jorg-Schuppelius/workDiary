---
title: "Contabilidad local"
topic: accounting.overview
version: 3
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
- **Códigos de IVA:** si existen códigos de IVA, la página los lista con sus
  casillas de la declaración de IVA alemana. Con **Editar** usted asigna una
  casilla a **Base imponible** y otra a **Cuota** – una ayuda de conciliación,
  no el formulario.

**Permiso:** ver con **Consultar la contabilidad**; plantilla, importación y
todos los cambios en cuentas y códigos de IVA con **Configurar la
contabilidad**.
