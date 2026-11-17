---
title: "Servicios de IA"
topic: ai.services
version: 6
keywords:
    - enmascaramiento
    - contrato de encargo
    - aprobación de protección de datos
    - IA
    - inteligencia artificial
    - asistente IA
    - modelo de lenguaje
    - LLM
    - Ollama
    - sugerencias de texto
    - traducir posiciones
    - reformular textos
    - glosario
    - activar IA
audience: []
modules:
    - module.ai
related:
    - invoices.manage
    - quotes.overview
---

La asistencia de IA es opcional y está desactivada por defecto. En
**Administración → Servicios de IA** conecta proveedores (nube o
local, p. ej. Ollama), activa capacidades individuales y define por
capacidad qué conexiones están permitidas y cuál es la predeterminada.

**Quién puede usar la IA:** Las sugerencias, traducciones y acciones en bloque
solo están disponibles para personas con el permiso «Solicitar sugerencias de
IA». Forma parte de todos los roles estándar del personal — desde
dirección, contabilidad y recursos humanos hasta servicio interno, servicio
externo y centro de llamadas —, pero no del portal de clientes ni del rol de
soporte de solo lectura. En roles propios lo asigna en **Roles & permisos**.
Solo quien tiene «Gestionar conexiones de proveedores de IA y el enrutamiento
de capacidades» mantiene conexiones, autorizaciones y
presupuesto.

**Privacidad:** La vista previa del flujo de datos muestra por
capacidad qué clases de datos van a qué proveedor. Las conexiones en la
nube están bloqueadas con sensibilidad alta y en el perfil de cuidados
ambulatorios; los planes que usan entradas para entrenamiento no se
pueden conectar. Las claves API se guardan cifradas y nunca se
muestran.

**Sustituir una clave:** Si una clave API caduca o se ve comprometida,
abra «Editar conexión» e introdúzcala en «Nueva clave API». Si lo
deja vacío, se mantiene la clave actual. La clave nueva se comprueba de
inmediato; la conexión se mantiene y su asignación como predeterminada por
capacidad no se pierde.

**Memoria de IA:** Términos de glosario, reglas de estilo y pares de
ejemplo por organización, cliente o capacidad mejoran las sugerencias —
sin entrenar modelos de terceros. Solo se aprende tras su confirmación
(diálogo «¿Recordar?»).

**Textos de posición:** En borradores de facturas y presupuestos la IA
crea sugerencias de texto por posición (incluidas traducciones). Nada
se aplica hasta que hace clic — cantidades, precios e impuestos
permanecen intactos.

**Acciones en bloque:** En una factura en borrador, «Traducir todo» traduce
cada posición, y en un protocolo «Mejorar todos los puntos» reformula cada
punto con texto. Ambas se ejecutan en segundo plano. Cada posición y cada
punto recibe su propia sugerencia, que usted acepta o rechaza de forma
individual; nada se aplica automáticamente.

**Elegir la conexión:** si su organización permite «los usuarios pueden
elegir» para un servicio y hay varias conexiones admitidas, la propuesta
muestra una selección. «Volver a generar» crea la propuesta con la conexión
elegida; la propuesta abierta anterior se sustituye. La elección queda en el
registro de auditoría.

**Elección de conexión:** si la organización permite «Los usuarios pueden elegir» para un servicio, una sugerencia también se puede regenerar con otra conexión permitida en el traspaso (textos de líneas), en la búsqueda de actividades y en «Explicar plan/real».

**Presupuesto mensual:** En la página **Consumo de IA**, «Presupuesto mensual»
fija por familia un límite por mes natural: en tokens para los modelos de
lenguaje y en caracteres para las traducciones. Una vez alcanzado, workDiary
rechaza con un aviso más llamadas de esta familia hasta final de mes; los
mosaicos muestran el uso. Si queda vacío, se aplica el valor predeterminado del
operador; sin él, el consumo es ilimitado.

**Enmascaramiento:** Antes de cada conexión en la nube, workDiary sustituye
IBAN, números de IVA, números fiscales, números de identificación fiscal,
direcciones de correo y números de teléfono por marcadores como `[IBAN-1]` o
`[EMAIL-2]`. Dentro de una misma solicitud, el mismo valor recibe siempre el
mismo marcador. En borradores, resúmenes, traducciones y valores de campo
reconocidos, workDiary restituye los valores antes de mostrarlos; el proveedor
nunca los ve. Solo se reconoce lo que supera la comprobación (dígitos de
control en IBAN, número de IVA e identificador fiscal); los importes y las
fechas no se tocan. Los nombres de clientes de su propia base se siguen
enmascarando siempre. Las conexiones locales ven los valores en texto claro
mientras «Enmascarar también ante conexiones locales» esté desactivado en
**Protección de datos**. El registro de auditoría cuenta los valores
enmascarados por tipo, sin nombrarlos.

**Aprobación de protección de datos:** Para cada conexión, «Aprobación de
protección de datos» deja constancia de que existe un contrato de encargo del
tratamiento (en servicios en la nube, con el documento), de que el tratamiento
se realiza en la UE y de que el proveedor no entrena con sus datos, junto con
la fecha y la persona que lo revisó. En una conexión local, el contrato solo es
necesario si un proveedor de servicios opera el modelo. La aprobación vale
tantos meses como se indique en **Protección de datos** (por defecto 12);
30 días antes de caducar, la cola de trabajo lo recuerda. La aprobación y la
revocación constan en el registro de auditoría. Las aprobaciones las concede
quien tiene el derecho «Conceder y revocar la aprobación de protección de
datos de conexiones de IA».

**Solo conexiones aprobadas:** Si este interruptor de **Protección de datos**
está activado, ninguna función de IA usa una conexión sin aprobación válida;
la prueba previa y el mensaje de error indican el motivo. Las organizaciones
nuevas empiezan con el interruptor activado. En las organizaciones que ya
existían antes de esta versión permanece desactivado hasta que un
administrador lo active; hasta entonces, las aprobaciones pendientes aparecen
como tareas en la cola de trabajo.
