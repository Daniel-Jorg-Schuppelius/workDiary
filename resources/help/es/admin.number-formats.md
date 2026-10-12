---
title: "Formatos de numeración"
topic: admin.number-formats
version: 2
keywords:
    - serie de numeración
    - número de factura
    - número de cliente
    - número de artículo
    - numeración correlativa
    - prefijo
    - número inicial
    - contador anual
    - reiniciar contador
    - numeración desde contabilidad
    - número de cliente de Lexoffice
    - número provisional
audience:
    - admin
    - teamleitung
related:
    - invoices.manage
    - admin.classifications
    - glossary.core
---

Los formatos de numeración definen las series correlativas por
organización, por ejemplo para tickets, activos, artículos, órdenes de
fabricación, pedidos, clientes, proveedores y facturas. Por cada
formato configura prefijo y separador, opcionalmente el año, el
número de dígitos de relleno, el valor inicial y si el contador se
reinicia anualmente; una vista previa muestra el próximo número.
Modifique el formato o el valor inicial con cuidado, ya que los números
ya asignados se conservan.

## ¿Quién asigna los números de cliente y de proveedor?

En la columna **Asignado por** elige para **Cliente** y **Proveedor** si
WorkDiary asigna el número o un programa de contabilidad conectado. Se
ofrecen los programas activos que pueden asignar números: Lexoffice
(cliente y proveedor), sevDesk y easybill (cliente). orgaMAX no asigna
números para WorkDiary.

Si un programa asigna los números:

- Un cliente nuevo recibe primero un número provisional (`ENTWURF-…`). Al
  crearlo en el formulario se transfiere enseguida al programa y recibe su
  número. Un proceso cada hora recupera los clientes de importaciones y las
  transferencias fallidas.
- El campo **Número de cliente** queda bloqueado para estos clientes.
- Si el número del programa ya pertenece a otro cliente, se mantiene el
  número anterior y la **bandeja de asignación** muestra el caso. Cuando
  renumere al otro cliente, la siguiente sincronización adopta el número.
- Los clientes existentes conservan su número.
- Si la contabilidad dirige los datos maestros, WorkDiary no transfiere
  nada; el número llega en cuanto la sincronización de contactos vincula al
  cliente.

Si WorkDiary asigna los números, cada transferencia comprueba si el número
ya pertenece a otro contacto en el programa y, en ese caso, se detiene con
un aviso en lugar de sobrescribir ese contacto.

Los números de factura, abono y anulación se configuran en los ajustes del
plugin de Lexoffice.
