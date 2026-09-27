# ui-base Specification

## Purpose

Define el layout global de MyNAB con dark mode por defecto, la paleta Neo-YNAB dark, tipografía Inter/JetBrains Mono y un toggle de tema persistente, que se aplica a todas las páginas del sistema para dar identidad visual consistente.

## Requirements

### Requirement: Dark mode por defecto

El sistema SHALL aplicar dark mode por defecto a todos los usuarios que ingresen sin preferencia previa registrada.

#### Scenario: Primera visita sin preferencia guardada

- **WHEN** un usuario carga cualquier página del sistema por primera vez y no existe la clave `mynab-theme` en localStorage
- **THEN** el `<html>` MUST tener la clase `dark`, el fondo del body es `#0a0a0a` y los textos usan la fuente Inter

### Requirement: Toggle de tema con persistencia

El sistema SHALL proveer un control visible en el navbar que permita alternar entre dark mode y light mode, guardando la preferencia elegida.

#### Scenario: Cambio de dark a light

- **WHEN** un usuario en dark mode activa el toggle de tema en el navbar
- **THEN** el sistema remueve la clase `dark` del `<html>`, aplica estilos de light mode y guarda `light` en `localStorage` bajo la clave `mynab-theme`

#### Scenario: Cambio de light a dark

- **WHEN** un usuario en light mode activa el toggle de tema
- **THEN** el sistema agrega la clase `dark` al `<html>` y guarda `dark` en `localStorage` bajo la clave `mynab-theme`

#### Scenario: Persistencia entre recargas

- **WHEN** un usuario recarga la página después de haber elegido light mode
- **THEN** el sistema lee `mynab-theme` de localStorage y aplica light mode sin flash de dark

### Requirement: Paleta Neo-YNAB dark aplicada

El sistema SHALL usar la paleta Neo-YNAB dark en todas las páginas: fondo base `#0a0a0a`, superficies `#141414` y `#1c1c1c`, bordes `#262626`, texto principal `#fafafa`, texto secundario `#a3a3a3`, acento primario verde `#22c55e`, acento secundario violeta `#8b5cf6`.

#### Scenario: Colores en superficies elevadas

- **WHEN** una página renderiza una tarjeta o modal en dark mode
- **THEN** su fondo MUST ser `#141414` (o `#1c1c1c` para elementos elevados) y su borde `#262626`

### Requirement: Tipografía consistente

El sistema SHALL usar la fuente Inter para todo el texto de UI y JetBrains Mono para todos los montos numéricos.

#### Scenario: Montos en font-mono

- **WHEN** una vista renderiza un monto o cantidad numérica financiera
- **THEN** el elemento MUST usar `font-mono` (JetBrains Mono) para asegurar alineación visual
