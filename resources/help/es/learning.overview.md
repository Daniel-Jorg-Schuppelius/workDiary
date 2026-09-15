---
title: "Plataforma de aprendizaje"
topic: learning.overview
version: 1
audience: []
related:
    - training.overview
    - safety.overview
---

La plataforma responde a **cómo se aprende y cómo se evalúa**. *Qué* debe
cursar cada persona y hasta cuándo permanece en la gestión de formación:
ambos encajan sin duplicarse.

## Construir cursos

Un curso se compone de secciones y unidades. Una unidad es contenido, un
examen, una tarea, una sesión presencial o material externo. El contenido se
construye con bloques (texto, aviso, lista de control, vídeo, inserción); el
HTML libre no está disponible a propósito.

**Las inserciones requieren un host permitido.** De lo contrario la política
de seguridad bloquearía la página en silencio dentro del curso; por eso el
editor rechaza de inmediato y de forma visible un host no permitido. Los hosts
permitidos se gestionan en los ajustes.

Un curso puede tener **requisitos previos** (todos, o basta con uno): bloquean
el inicio, no la asignación — las inscripciones obligatorias están exentas. Un
**examen sin curso** es un curso de tipo «examen» con exactamente una unidad de
prueba; quien aprueba obtiene la convalidación del curso de destino configurado —
con el mismo retorno a certificado, constancia de instrucción y cualificación.

Quien viene de LearnDash adopta el **ZIP de exportación** (catálogo → «Importación de LearnDash»): cursos, lecciones, temas y exámenes se crean como borradores, las preguntas van al catálogo con su categoría. Imágenes y medios no se copian (marcadores para completar), los vídeos de lección solo de hosts permitidos. Los cursos finalizados se anotan como inscripciones «importadas» para personas con correo coincidente — sin certificado ni registro de instrucción, porque una finalización importada no es prueba propia. La prueba muestra de antemano lo que se crearía.

## Publicar congela el contenido

Al publicar se crea una versión del curso con una imagen completa del
contenido. Las participaciones en marcha permanecen en su versión: la materia
no cambia bajo quien ya está a mitad de camino. Tras la publicación el
contenido queda bloqueado; las correcciones pasan por una versión posterior.

Si el curso está vinculado a una formación obligatoria, la publicación
registra allí también la versión. La prueba posterior lleva entonces el mismo
número.

Las opciones del curso gobiernan el recorrido: un **plan de desbloqueo** (días
desde la inscripción y/o fecha fija — se aplica la posterior) bloquea una
unidad hasta ese día en cada punto de finalización — reproductor, portal,
acceso externo y sincronización sin conexión —, no solo en la vista. Una
**permanencia mínima** cuenta desde la primera apertura de la unidad o por el
tiempo de aprendizaje. Las **unidades de vista previa** se leen en el portal
sin inscripción (solo texto). Las **categorías** de los ajustes ordenan el
catálogo; la **ventana de disponibilidad** y el **límite de participantes**
rigen la autoinscripción — la administración puede seguir asignando y las
inscripciones obligatorias omiten el límite. Las tareas llevan **reglas de
archivos** (extensiones, número, tamaño — nunca más laxas que el sistema) y,
opcionalmente, una **aprobación automática** con puntos completos, incompatible
con el principio de cuatro ojos.

## El tiempo de aprendizaje es jornada laboral

La formación obligatoria debe realizarse **dentro de la jornada laboral**
(§ 12 ap. 1 ArbSchG). Por eso cada curso lleva una política de tiempo:

- **Solo durante la jornada** (predeterminado en cursos obligatorios): se
  rechaza iniciar fuera de ella.
- **Cuenta siempre como jornada**: para formación ordenada.
- **Fuera de jornada solo con aprobación**.
- **Voluntario, no remunerado**: solo para ofertas realmente adicionales;
  bloqueado en cursos con vínculo obligatorio.

El tiempo **dentro** de la jornada no se cuenta dos veces: ya está registrado
por la presencia. El tiempo **fuera** crea un tramo de presencia para que se
comprueben descanso, jornada máxima y trabajo nocturno.

## Exámenes

Un intento congela las preguntas planteadas. Si una pregunta cambia después,
un resultado antiguo sigue siendo explicable, que es justo lo que pregunta un
inspector tras un incidente. Los intentos nunca se eliminan; una corrección se
añade junto al valor original en lugar de sustituirlo.

Los ensayos los califica una persona. La IA propone cursos y preguntas y
responde dudas en el contexto del curso: **no puede calificar ni decidir**.

Las preguntas viven en el **banco de preguntas** de la organización (menú
«Formación» → «Banco de preguntas») con categoría y nombre corto; una prueba
apunta a preguntas del banco y la misma pregunta puede aparecer en varias
pruebas. Además de la lista fija, las **reglas de extracción** eligen en cada
intento un número de preguntas aleatorias de una categoría («5 de protección
contra incendios»). Quitar una pregunta de una prueba la deja en el banco; solo
se elimina lo que no se usa — y un intento realizado conserva siempre su propia
copia de las preguntas.

El desarrollo se configura por prueba: todas las preguntas en una página o una
pregunta por página, permitir volver y saltar, respuestas obligatorias, textos de
resultado por rango porcentual y una pista por pregunta. Las respuestas se guardan
al cambiarlas — nada se pierde tras un corte de conexión; vencido el tiempo solo
cuenta lo guardado a tiempo. El resumen muestra las preguntas respondidas y
marcadas.

Los evaluadores consultan el **expediente del intento** (preguntas de la copia
congelada, respuestas dadas, puntos, correcciones) — cada consulta queda
registrada. Cada prueba tiene **estadísticas** (intentos, tasa de aprobados,
tiempo, tasa de error por pregunta — tasas solo a partir del grupo mínimo).
Desde la lista de participantes se puede conceder un **intento adicional** pese
al límite o la espera, una sola vez y con motivo.

El **libro de calificaciones** por curso muestra alumnado × componentes. Sin componentes suma los puntos de exámenes y tareas; si la formación define **componentes** (examen, tarea, nota manual) puede asignar pesos — todos suman 100 o ninguno. Las notas manuales son aditivas: una corrección es una entrada nueva, cuenta la más reciente. El **boletín** (PDF) y la exportación CSV salen del mismo cálculo; mientras algo siga abierto, el boletín lleva la marca «provisional».

Matices por pregunta: las opciones de respuesta pueden llevar **puntos propios** («Etiqueta {3}», también negativos) — cuenta entonces la opción elegida en vez de todo o nada; una **autoevaluación** es una escala sin respuesta correcta, el nivel elegido es el valor; una **redacción** admite texto, archivo o ambos — el archivo está disponible en la evaluación. Por examen puede exigirse además el aprobado **en puntos** y fijarse el subconjunto por intento **en porcentaje** de las preguntas disponibles.

## Pruebas

Un curso superado surte efecto en un único punto: certificado con código de
verificación, prueba de formación en el registro de seguridad, obligación
cumplida y cualificación prolongada. No se crea un segundo mundo de pruebas.

Los certificados se verifican mediante un enlace. La página muestra curso,
fecha, vigencia y emisor; el nombre solo abreviado.

Los datos de aprendizaje pertenecen a la persona: el **informe al
interesado** (módulo de protección de datos) enumera inscripciones, intentos,
certificados, tiempo de aprendizaje y reservas como contadores con periodo —
nunca los textos de las preguntas ni las respuestas. El **plan de
conservación** propone borrar las inscripciones finalizadas sin certificado una
vez vencido el plazo regional (intentos y tiempo de aprendizaje van con ellas);
los certificados permanecen más tiempo como prueba y luego se reducen a
iniciales — el enlace de verificación sigue respondiendo.

## Quién aprende

Además de la plantilla, los clientes pueden formarse por el portal y los
participantes externos sin cuenta de usuario. Estos reciben un enlace de un
solo uso con caducidad; su prueba es la misma que la interna.

Los participantes de un curso se gestionan desde la ficha del curso en
«Participantes»: inscribir personas de la organización o externas, cambiar
vencimiento y acceso con un motivo, cancelar (nunca las inscripciones
obligatorias) y crear el enlace de acceso para externos — un enlace nuevo
invalida el anterior. Una reserva confirmada envía el enlace automáticamente.

La **configuración de la plataforma** (catálogo, derecho de gestión) contiene
el interruptor de puntos y clasificación, los hosts de inserción permitidos y la
**vista de formador**: si está activa, las personas con derecho de autoría o
evaluación solo ven los cursos propios o a los que están asignadas — catálogo,
panel de evaluación, estadísticas y analítica siguen la misma regla. La gestión
sigue viéndolo todo.

En el reproductor cada persona guarda **notas privadas** sobre la unidad o el
curso — visibles solo para ella, ni siquiera para la administración, y ausentes
de la búsqueda de actividad; «Mis cursos» las reúne. Una **pregunta al
formador** llega a la persona responsable y a los formadores del curso: como
ticket con el módulo helpdesk, si no por correo — ambos reciben además una
notificación. Dos mosaicos del panel (ocultos por defecto) muestran los cursos
abiertos propios y las evaluaciones pendientes; la búsqueda de actividad
encuentra los cursos publicados — el alumnado los suyos, la autoría todos.

El **catálogo** puede verse como lista o mosaicos (la elección se guarda por persona) y lleva una **valoración con estrellas** de la encuesta del curso — solo a partir de cinco respuestas, para que nada sea atribuible a personas. El portal muestra además el **precio** del artículo vinculado. En el reproductor, el **modo concentración** oculta la barra lateral; «Duplicar» crea un nuevo borrador a partir de un curso — material sí, inscripciones y registros no.

## Analítica y cogestión

La analítica muestra tasas y anomalías, no perfiles personales. Las tasas
aparecen a partir de cinco inscripciones para que no se pueda inferir a
personas. Puntos, insignias y clasificación están desactivados de fábrica; la
clasificación solo muestra a quien lo consiente expresamente.

Las notificaciones siguen las reglas de la organización: asignación,
vencimiento próximo, retraso (con escalado), entrega recibida, evaluación
disponible, certificado, ascenso desde la lista de espera, decisión de
reserva y aprobación del tiempo de aprendizaje. La IA tiene tres entradas —
borrador de esquema y de preguntas en el editor, tutor en el reproductor — y
solo propone; la adopción y la evaluación son manuales. Puntos e insignias
aparecen en «Mis formaciones»; la clasificación solo muestra a quien lo
consintió.
