---
title: "Equipos, jornadas y alineaciones"
topic: club.matches
version: 1
audience: []
modules:
    - module.club
related:
    - club.groups
    - club.events
    - club.attendance
---

Los deportes de equipo y de raqueta (fútbol, balonmano, baloncesto, voleibol,
hockey, tenis de mesa, tenis …) se apoyan en grupos, citas y asistencia. Un
deporte es un **perfil deportivo**, es decir configuración y no un caso
especial del sistema: familia deportiva, posiciones, tamaños de plantilla
(campo/banquillo), formato de resultado (goles, puntos por periodo, sets),
individual/dobles, fecha de corte de la categoría de edad, disciplinas y tipos
de recursos. La asociación adapta perfiles o crea otros; las reglas federativas
no están programadas de forma fija.

**Equipos:** Un equipo es un grupo marcado como «equipo» con un perfil
deportivo (propio o el de la sección). La categoría de edad es una etiqueta
libre (p. ej. U15); los criterios de edad de un equipo se comprueban en la
fecha de corte del perfil dentro de la temporada, no en el día natural.

**Temporadas y plantillas:** Las temporadas son periodos con nombre (p. ej.
2026/27). Por equipo y temporada hay una plantilla con vigencia por persona,
dorsal, posición y, en deportes de raqueta, el orden de fuerza mantenido
manualmente. Las temporadas anteriores se conservan sin cambios. Los
**jugadores invitados** de un club asociado figuran en la plantilla con su
club de origen; son personas de tipo «invitado» sin asignación de cuota, sin
acceso y sin pertenencia a grupo.

**Jornadas:** Una jornada es una cita de la asociación con datos deportivos:
equipo, rival (sin registro de cliente ni usuario), competición, casa/fuera,
lugar, hora de encuentro y responsable. El equipo es el grupo destinatario de la
cita; pueden añadirse otros grupos.

**Disponibilidad y alineación:** Los miembros responden en el portal disponible,
no disponible o «quizá»; una respuesta no es una convocatoria. La dirección
alinea (campo/banquillo con posición y dorsal; en deportes de raqueta parejas de
individual y dobles) y libera. Tamaños de plantilla y posiciones vienen del
perfil. Una persona en individual y dobles sigue siendo una persona. Antes de
liberar se muestran los conflictos: participación simultánea en otra alineación
o una negativa expresa. Liberar pese a conflictos requiere un motivo y queda
registrado. Los convocados pasan a ser participantes de la cita; la asistencia
real se registra aparte en la hoja de asistencia.

**Roles de la cita:** Árbitro, cronometrador/jurado, transporte, servicio de
pista/vestuario o supervisor de galería se asignan por cita, a un miembro, a
una persona empleada o como nombre externo. Un miembro con rol cuenta como
participación en la asociación, no como plaza en la plantilla.

**Resultado:** El resultado se registra manualmente en el formato del perfil
(goles, puntos por periodo con suma, sets con marcador). Goleadores y
observaciones van en la nota. Los cambios quedan registrados; no hay
clasificación automática a partir de resultados incompletos.

**Importación de calendario:** CSV o ICS generan una **lista de propuestas**
que no crea nada antes de la confirmación. La dirección comprueba rival, lugar y
hora y acepta o descarta cada propuesta; las filas conocidas se omiten al
reimportar y los posibles duplicados de jornadas existentes se marcan. Columnas
CSV (fila de cabecera, orden libre): fecha, hora, fin opcional, rival y
casa/fuera —o local y visitante como nombres de equipo— además de lugar y
competición. No se incluye sincronización con federaciones.

## Paquetes iniciales por deporte

En la página **Deportes**, un **paquete inicial** crea un deporte en un solo paso:
perfil deportivo, sección, grupos o equipos habituales e instalaciones y, según el
deporte, un sistema de grados (artes marciales), caballos de escuela (equitación) o
un requisito de asistencia (tiro). Se incluyen once deportes: artes marciales, tenis
de mesa, hockey, equitación, fútbol, balonmano, baloncesto, voleibol, tenis,
atletismo y tiro deportivo. Un paquete es configuración, no un caso especial: todo
lo creado puede modificarse o eliminarse después, las entradas existentes con el
mismo nombre no se tocan y no hay reglas federativas ni umbrales legales.

El sector de demostración **Asociación deportiva** instala los once paquetes y los
rellena con personas, citas, asistencias, cuotas, jornadas, competiciones, exámenes
y clases de equitación ficticios.
