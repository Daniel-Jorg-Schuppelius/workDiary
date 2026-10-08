---
title: "Temas"
topic: admin.themes
version: 5
keywords:
    - modo oscuro
    - tema oscuro
    - modo claro
    - esquema de colores
    - colores personalizados
    - apariencia
    - colores corporativos
    - identidad visual
    - contraste
    - aspecto visual
audience:
    - admin
modules:
    - module.theming
related:
    - admin.handbook
    - admin.license
    - navigation.interface
---

Los temas son preajustes de diseño de su organización para la
interfaz. Definen la paleta de colores y de geometría (modo base claro
u oscuro). Además de los **Temas predefinidos**, puede crear sus
propios temas en **Temas personalizados** (doce como máximo).

Con **Nuevo tema** o **Editar** define para cada tema:

- **Datos maestros**: **Clave** (minúsculas, cifras, guion; no
  modificable tras la creación), **Nombre** y **Modo base** (**Claro**
  u **Oscuro**).
- **Colores**: colores base, de acento y de estado (p. ej. fondo,
  principal, secundario, acento, neutro así como
  info/éxito/advertencia/error). Los colores de texto se derivan
  automáticamente del contraste.
- **Geometría**: radios de las esquinas y **Ancho del borde**.

La **Vista previa** del diálogo muestra el efecto al instante. Se
impone un contraste mínimo (neutro frente a texto neutro) para que la
barra lateral y los paneles sigan siendo legibles.

Establecer el predeterminado:

- En el área **Tema predeterminado de la organización** elige un tema
  para el **Modo claro** y otro para el **Modo oscuro** y guarda con
  **Aplicar**. La elección se aplica a todos los miembros que no hayan
  elegido un tema propio en su perfil; los temas personalizados
  muestran entonces la marca **Claro predeterminado** u **Oscuro
  predeterminado**.
- La entrada **Predeterminado (Corporate)** o **Predeterminado
  (Corporate Dark)** anula de nuevo su selección; se aplican entonces
  los temas incluidos Corporate (claro) y Corporate Dark (oscuro, mismos
  colores sobre fondo oscuro).

Licencia/módulos: los temas personalizados forman parte del módulo
**Temas personalizados** y están disponibles en los planes superiores.
Tras una reducción de plan, un tema activo se mantiene (puramente
estético); la página **Temas**, con el editor y la selección
predeterminada, queda entonces bloqueada. Detalles en el capítulo
**Licencia**.

Permiso: los temas pueden gestionarlos los administradores de la
organización.

Riesgos: eliminar un tema en uso devuelve a los usuarios afectados a
un tema de reserva; si estaba establecido como predeterminado, vuelve a
aplicarse el predeterminado incluido. Compruebe la legibilidad de los
cambios de color antes de establecer un tema como predeterminado.
