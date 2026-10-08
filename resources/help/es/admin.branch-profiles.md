---
title: "Perfiles sectoriales"
topic: admin.branch-profiles
version: 3
keywords:
    - plantilla sectorial
    - oficio
    - gremio
    - paquete de plantillas
    - electricidad
    - fontanería y calefacción
    - limpieza
    - tipos de pedido
    - plantillas de checklist
    - configuración inicial
    - variante de perfil
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

**Variantes específicas del cliente:** una variante se superpone a un perfil
sectorial – adopta el perfil base, omite elementos y añade los propios, sin
modificar el perfil. «Crear variante» pide el perfil base, un código
(minúsculas, cifras y guiones) y una denominación. En «Omitir elementos»
marca lo que no debe crearse al instalar; las entradas ya existentes no se
tocan. Los «Añadidos» son un extracto de perfil en formato JSON, estructurado
como un perfil sectorial; los elementos con el mismo nombre sustituyen a los
del perfil base. **Instalar** aplica la variante, «Actualizar entradas
existentes» pone al día las entradas ya instaladas; cada guardado incrementa
la versión. «Exportar como JSON» transmite la variante; al eliminarla, las
entradas instaladas se conservan.
