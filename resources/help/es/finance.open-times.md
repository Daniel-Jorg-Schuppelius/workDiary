---
title: "Tiempos abiertos"
topic: finance.open-times
version: 2
audience: []
modules:
    - module.finance
related:
    - finance.transfers
    - roles.buchhaltung
    - glossary.core
---

La lista de trabajo **Tiempos abiertos** muestra todos los registros
de tiempo de la organización que **aún no se han facturado** —
independientemente de quién los haya registrado. Es el instrumento de
control de contabilidad para que ningún tiempo se pierda antes de un
ciclo de facturación.

¿Qué cuenta como «abierto»? Un registro que aún no ha sido consumido
por ningún canal de facturación — ni por una factura local, ni por el
cierre de la cuenta del cliente, ni por una transferencia de
facturación.

Los clientes con saldo corriente (condiciones especiales en modo «cuenta
de cliente» o «cuota fija») **no** aparecen en la lista: sus tiempos no
se facturan, sino que se liquidan mediante el bloque mensual de la ficha
del cliente — aquí serían huéspedes permanentes. Una nota sobre la lista
indica cuántos registros quedan ocultos así. Los clientes en modo
«factura mensual» siguen visibles, pasan por la facturación normal.

Funciones:

1. **Indicadores** arriba: número de registros abiertos, tiempo
   abierto (formato reloj y decimal), ingreso neto previsto. Las
   fichas de aviso «Rezagados» y «Más de 45 días» cuentan siempre
   sobre todo el pendiente — independientemente del período
   seleccionado.
2. **Período**: la lista sigue la selección global de período en la
   cabecera de la página. Los parámetros de/hasta en la barra de
   direcciones (marcadores) la sustituyen.
3. **Filtros**: cliente, proyecto, empleado/a y el conmutador
   «facturable». Con «Solo no facturables» se pueden revisar los
   tiempos marcados como no facturables de forma deliberada o por
   error.
4. **Totales por cliente & proyecto** en un bloque desplegable sobre
   la lista detallada.
5. **Exportación CSV** con la duración en ambos formatos (H:MM y
   decimal).
6. **Marcar como facturado**: para la puesta en marcha cierra todos
   los tiempos abiertos hasta una fecha de corte que ya se facturaron
   fuera del sistema — opcionalmente para un solo cliente y, si se
   desea, incluyendo los registros no facturables. La acción está
   reservada a administración y contabilidad y no se puede deshacer
   con un clic.

La página es visible para los roles con el permiso «ver todos los
registros de tiempo» (por defecto contabilidad, dirección y
administración).
