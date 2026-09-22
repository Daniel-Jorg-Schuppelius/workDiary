---
title: "Perfiles sectoriales"
topic: admin.branch-profiles
version: 2
audience:
    - admin
related:
    - admin.handbook
    - admin.import
---

Los perfiles sectoriales instalan en un solo paso un paquete curado de
plantillas por oficio: tipos de pedido, categorías, reglas
obligatorias, listas de verificación, requisitos de salas, etiquetas y,
según el oficio, planes de mantenimiento o plantillas SLA. Busque el
oficio en el catálogo, revise la **vista previa de contenido** de la
tarjeta y elija **Instalar**. La instalación es idempotente: repetirla
no crea duplicados ni sobrescribe datos adaptados localmente, y
**Aplicar de nuevo** restablece las plantillas importadas al estado del
perfil sin tocar las listas de verificación ya publicadas. Cada
instalación queda registrada de forma auditable y los nuevos oficios se
añaden por configuración, sin cambios de código.

Los perfiles pueden **combinarse**; el primero instalado es el **perfil
principal**, que determina el enfoque de navegación y los valores
predeterminados, mientras la recomendación de módulos reúne todos los
perfiles instalados («Establecer como perfil principal» lo cambia).
**Desinstalar** (administrador de plataforma) elimina tipos de encargo,
categorías y etiquetas no utilizados, desactiva las clasificaciones en uso
y borra las reglas obligatorias del perfil; las plantillas se conservan.
Un aviso de actualización en la tarjeta indica una versión más reciente;
**Importar** acepta un perfil JSON del catálogo. Los tipos de encargo se
muestran en el idioma del usuario.
