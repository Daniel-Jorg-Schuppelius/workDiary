---
title: "Servicios de IA"
topic: ai.services
version: 3
keywords:
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
