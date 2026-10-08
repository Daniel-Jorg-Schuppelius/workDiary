---
title: "Conciliación de pagos"
topic: finance.reconciliation
version: 3
keywords:
    - conciliación bancaria
    - importar extracto bancario
    - casar pagos
    - cobros recibidos
    - factura pagada
    - movimientos bancarios
    - CAMT
    - MT940
    - descuento por pronto pago
    - pago parcial
    - referencia RF
    - punteo
audience: []
modules:
    - module.finance
related:
    - finance.transfers
    - roles.buchhaltung
    - glossary.core
---

La **Conciliación de pagos** importa extractos bancarios en formato
**CAMT.053** (preferido) o **MT940** (alternativa), normaliza los
movimientos bancarios en un **área de revisión** y propone facturas
abiertas o gastos aprobados para su asignación. **La importación por sí
sola no modifica ningún documento**: solo la **confirmación** establece
`Factura → pagada` (con fecha de pago) o marca un gasto como reembolsado.

## Procedimiento

1. **Importar:** subir el archivo bancario (opcionalmente, elegir una
   cuenta bancaria propia; de lo contrario, se asigna automáticamente a
   través del IBAN). Los archivos idénticos se rechazan como duplicados
   mediante el hash del archivo; los movimientos ya conocidos se omiten en
   una nueva importación.
2. **Revisar:** en el detalle del extracto, cada movimiento muestra un
   estado (Abierto/Asignado/Apartado/No asignable) y, si está abierto,
   **Sugerencias de asignación** con puntuación de coincidencia y
   justificación (número de factura, importe, descuento por pronto pago,
   coincidencia de IBAN, proximidad de fechas).
3. **Confirmar:** con *Confirmar* se crea la asignación y se aplica su
   efecto sobre el documento. Como alternativa, *Apartar* (p. ej.
   comisión bancaria) o *No asignable*.
4. **Deshacer:** una asignación confirmada es **reversible**: se anula y
   el efecto sobre el documento (pagada/reembolsado) solo se revierte si
   este movimiento era el pago. **El movimiento bancario en sí nunca se
   modifica.**

## Casos prácticos

- **Descuento por pronto pago:** un pago inferior dentro de la tolerancia
  de descuento (3 % por defecto) se considera pago completo.
- **Tolerancia de céntimos:** las diferencias de redondeo de hasta 2
  céntimos no impiden una sugerencia.
- **Pago parcial/pago en exceso:** se gestionan como un tipo de asignación
  propio; en caso de pago parcial, la factura sigue abierta.
- **Cadena de saldos:** se comprueba el saldo inicial + la suma de los
  movimientos frente al saldo final; las diferencias se indican como
  advertencia.
- **Moneda extranjera:** los movimientos en otra moneda solo se detectan y
  se marcan para su aclaración manual.

## Protección de datos

Los datos bancarios de carácter personal (nombre, IBAN, concepto de la
contraparte) se almacenan **cifrados**. La conciliación se realiza
exclusivamente a partir de derivados no cifrados (hash del IBAN, números
de factura extraídos, importes, fechas). Cada acción de asignación se
registra a prueba de auditoría en una cadena de hash.

## Permisos

- **Importar archivo bancario** y **confirmar/deshacer asignaciones:**
  rol *Contabilidad* (además de la administración).
- **Gestionar cuentas bancarias propias:** solo la administración.

## Referencia de pago RF

Si la referencia de pago RF está activada en la configuración de la
organización, cada factura lleva una referencia de acreedor RF
(ISO 11649) derivada de su número, en las indicaciones de pago y en el
GiroCode como referencia estructurada. Si el cliente realiza la
transferencia con esta referencia, la conciliación de pagos reconoce la
factura gracias a ella, incluso escrita en grupos de cuatro.
