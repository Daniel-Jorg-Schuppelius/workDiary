---
title: "Instalaciones deportivas y recursos"
topic: club.resources
version: 1
audience: []
modules:
    - module.club
related:
    - club.events
    - club.matches
---

Las instalaciones y recursos representan pabellones, zonas parciales (mitad,
tercio), mesas, pistas, calles y puestos, embarcaciones y aparatos como un
**árbol**: una zona parcial cuelga de su pabellón, una mesa del pabellón o de
una mitad. La comprobación de conflictos es común: reservar todo el pabellón
bloquea todas las zonas y mesas debajo; distintas zonas libres pueden usarse
en paralelo. No hay calendarios aislados por deporte.

**Salas y activos:** Un recurso puede vincularse a una sala existente; entonces
las citas con esa sala y las reservas del recurso (incluidas las zonas)
comparten el mismo calendario. Embarcaciones y aparatos pueden vincularse a un
activo: los bloqueos del activo (mantenimiento, defecto, inspección) impiden
la reserva; no hay una segunda disponibilidad para el mismo objeto ni obligación
de crear un contrato de alquiler.

**Unidades y márgenes:** Un recurso con varias unidades (p. ej. cuatro calles)
puede reservarse parcialmente; la cantidad por cita se comprueba contra las
unidades. Los márgenes de montaje y desmontaje amplían la ventana reservada.

**Reservar:** Los recursos se reservan en la cita o jornada, con cantidad,
opcionalmente ventana propia, márgenes y persona usuaria. La reserva comprueba
capacidad, pabellón/zonas, calendario de salas, cierres y bloqueos de activos
en una transacción; dos reservas simultáneas nunca se confirman ambas. Los
lugares de partidos fuera son textos y no reservan nada.

**Desplazar y cancelar:** Si una cita se desplaza, sus reservas se desplazan
con ella; en caso de conflicto todo queda como estaba (hora antigua, reserva
antigua). Una cancelación libera todas las reservas.

**Cierres:** Clima, mantenimiento o uso externo se registran como cierre con
motivo. Las reservas existentes se **marcan** para replanificar, no se
eliminan; las nuevas reservas en el periodo quedan bloqueadas. Levantar el
cierre libera las reservas marcadas.

**Habilitaciones:** Embarcaciones, aparatos y recursos similares pueden exigir
una habilitación de instrucción o aptitud por miembro, con caducidad opcional y
concedida por la dirección. Sin habilitación válida la persona no puede
figurar como usuaria; en la cita se muestran los participantes inscritos sin
habilitación.
