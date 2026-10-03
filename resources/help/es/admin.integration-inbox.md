---
title: "Bandeja de conciliación"
topic: admin.integration-inbox
version: 1
audience: []
related:
    - admin.integrations
    - admin.import
    - contacts.manage
    - finance.open-times
---

La bandeja de conciliación reúne **las importaciones recibidas que no se
pudieron asignar automáticamente** – de sistemas conectados, de la
importación CSV y de la entrada de correo. Nada se crea a ciegas: usted
decide en cada entrada.

**Tres casos:**

- **Sin asignar** – no existe un registro que corresponda al recibido.
- **Ambiguo** – hay varios registros posibles.
- **Conflicto de campo** – el registro es conocido, pero el estado local y el
  remoto se contradicen. Ambos estados se muestran uno junto al otro.

**Decidir:** asigna una entrada a un registro existente, la crea como nueva o
la descarta. En un conflicto de campo elige si se mantiene el estado local o
se aplica el remoto. La decisión queda visible en la entrada (Asignado,
Creado, Mantener local, Remoto aplicado, Descartado); con el filtro de estado
recupera las entradas ya resueltas.

**Grupos:** las entradas relacionadas aparecen arriba como grupo y se deciden
en un solo paso:

- **Tiempos importados** de un proyecto desconocido: elegir o indicar el
  cliente, opcionalmente el cliente final, y el proyecto, y contabilizar el
  grupo.
- **Dispositivos desconocidos** de la asistencia remota: vincularlos a un
  dispositivo y contabilizar.
- **Números de teléfono desconocidos:** asignarlos a un cliente; «Recordar el
  número de forma permanente» vale para llamadas futuras, un número
  compartido solo para esta.
- **Usuarios desconocidos** de una importación de tiempos: asignarlos a un
  usuario.
- **Citas periódicas** de calendarios: crear todo como citas.
- **Pedidos** del catálogo B2B: registrar como pedido.

«Mostrar entradas» despliega el contenido de un grupo. Un grupo también se
puede descartar por completo.

**Filtros:** las pestañas separan por fuente; el número indica las entradas
abiertas. Además filtra por estado, caso y entidad. Las listas de selección
muy largas se limitan a 1000 entradas – el campo de búsqueda de arriba a la
derecha acota la selección.

**Gestionar asignaciones:** WorkDiary recuerda una asignación realizada; las
importaciones posteriores del mismo registro pasan entonces sin preguntar. En
«Gestionar asignaciones» consulta y elimina esos vínculos.

La página está abierta a quienes pueden gestionar la facturación.
