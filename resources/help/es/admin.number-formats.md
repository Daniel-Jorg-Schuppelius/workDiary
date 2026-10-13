---
title: "Formatos de numeración"
topic: admin.number-formats
version: 5
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
    - separador
    - vía de facturación
    - número de oferta del programa
    - número de factura del programa
    - factura a Lexoffice
    - factura a sevDesk
    - factura a easybill
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

## Guardar y separadores para todas las series

Guarde todos los cambios de la página a la vez con **Guardar** en la
cabecera. Las series que no modifique se quedan en el valor estándar
(«Default»). Si una entrada no es válida, el aviso indica la serie y sus
entradas se conservan.

Encima de la tabla están los campos **Separador tras el prefijo** y
**Separador tras el año** para todas las series. **Aplicar a todas las
series** fija ambos separadores en cada serie que asigna WorkDiary y guarda
la página. Un campo vacío significa sin separador. Los números ya asignados
no cambian.

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
- La columna **Próximo número** muestra el último número adoptado, cuántos
  contactos esperan todavía su número y, en sevDesk, el próximo número del
  programa.
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

## Factura, abono y anulación

En la columna **Asignado por** también elige para **Factura**, **Abono** y
**Anulación** quién asigna el número: WorkDiary o un programa conectado –
Lexoffice y easybill para los tres, sevDesk para factura y anulación. Si
asigna un programa, el borrador lleva un número provisional; **Emitir**
transfiere el documento y adopta el número del programa, el abono y la
anulación con referencia a la factura transferida. Las facturas de anticipo y
finales se crean entonces en el programa.

Si la **vía de facturación** de los ajustes de la organización está en un
programa de contabilidad, este lleva las facturas por completo; la columna
**Asignado por** lo indica. Si algunos clientes facturan por otra vía, debajo se
indica para cuántos.

## Series bloqueadas

Si un programa asigna los números de una serie, también fija el formato.
Prefijo, separadores, año, dígitos, reinicio anual e inicio quedan entonces
bloqueados; los valores guardados vuelven a aplicarse en cuanto WorkDiary
asigna. Si un cliente se factura en WorkDiary pese a una vía de facturación
externa, factura, abono y anulación siguen abiertos, porque sus facturas
reciben números locales. La selección **Asignado por** sigue siendo editable; los campos quedan libres tras guardar.

## Oferta, confirmación de pedido y albarán

Para **Oferta**, **Confirmación de pedido** y **Albarán** elija Lexoffice
o easybill en la columna **Asignado por**. El número se crea al enviar la oferta, al
confirmar la confirmación de pedido y al entregar el albarán, cuando WorkDiary
transfiere el documento al programa.
