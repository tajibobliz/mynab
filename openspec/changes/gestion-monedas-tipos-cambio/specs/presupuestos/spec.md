## MODIFIED Requirements

### Requirement: Selector visible en navbar

El sistema SHALL mostrar en el navbar de todas las páginas autenticadas un selector con el presupuesto activo, su moneda base, y la posibilidad de cambiar a otros presupuestos.

#### Scenario: Usuario con presupuesto activo

- **WHEN** un usuario autenticado con al menos un presupuesto activo carga cualquier página
- **THEN** el navbar muestra un dropdown con nombre + color + código de moneda base del presupuesto activo (ej: "Personal · BOB")

#### Scenario: Usuario sin presupuestos

- **WHEN** un usuario autenticado sin presupuestos carga cualquier página
- **THEN** el navbar muestra un CTA "Crear presupuesto" que redirige a `/presupuestos/create`

## ADDED Requirements

### Requirement: Moneda base obligatoria y fija

El sistema SHALL requerir una moneda base al crear un presupuesto y NO permitir cambiarla posteriormente.

#### Scenario: Creación con moneda base

- **WHEN** un usuario crea un presupuesto sin especificar moneda base
- **THEN** el sistema rechaza con error de validación "La moneda base es requerida"

#### Scenario: Creación con moneda inactiva

- **WHEN** un usuario intenta crear un presupuesto con una moneda desactivada
- **THEN** el sistema rechaza con error "La moneda seleccionada no está activa"

#### Scenario: Intento de cambiar moneda base en update

- **WHEN** el dueño de un presupuesto envía PUT con `moneda_base_codigo` distinta a la actual
- **THEN** el sistema ignora silenciosamente el cambio de moneda
- **AND** actualiza los demás campos válidos
- **AND** el presupuesto mantiene su moneda base original

#### Scenario: UI de edición muestra moneda como readonly

- **WHEN** un usuario abre `/presupuestos/{id}/edit`
- **THEN** el campo moneda base se muestra como texto plano con tooltip "No se puede cambiar después de crear el presupuesto"