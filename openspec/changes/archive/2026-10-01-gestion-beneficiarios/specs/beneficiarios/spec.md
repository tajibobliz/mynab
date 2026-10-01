## Purpose

Permite al usuario registrar beneficiarios (payees) dentro de cada presupuesto para vincular transacciones con el "quién" del movimiento. Cada beneficiario tiene nombre único dentro del presupuesto y notas opcionales. Soft delete preserva el histórico transaccional del Change 7.

## ADDED Requirements

### Requirement: Beneficiarios pertenecen a un presupuesto

El sistema SHALL asociar cada beneficiario a exactamente un presupuesto, y filtrar la vista por el presupuesto activo del usuario.

#### Scenario: Aislamiento por presupuesto activo

- **WHEN** un usuario con presupuesto activo "Personal" navega a `/beneficiarios`
- **THEN** ve únicamente beneficiarios de "Personal"

#### Scenario: Sin presupuesto activo

- **WHEN** un usuario sin presupuesto activo intenta acceder a `/beneficiarios`
- **THEN** el sistema redirige a `/dashboard` con mensaje explicativo

#### Scenario: Mismo nombre en distintos presupuestos permitido

- **WHEN** un usuario crea "Entel" en Personal y luego "Entel" en Freelance USD
- **THEN** el sistema acepta ambos (son beneficiarios independientes)

### Requirement: Nombre único case-insensitive dentro del presupuesto

El sistema SHALL rechazar nombres duplicados case-insensitive dentro del mismo presupuesto.

#### Scenario: Duplicado rechazado

- **WHEN** un usuario intenta crear "Netflix" cuando ya existe "netflix" en el mismo presupuesto
- **THEN** el sistema rechaza con error "Ya existe un beneficiario con ese nombre en este presupuesto"

### Requirement: Notas opcionales

El sistema SHALL permitir texto libre opcional de hasta 1000 caracteres para contexto adicional del beneficiario.

#### Scenario: Beneficiario sin notas

- **WHEN** un usuario crea un beneficiario sin notas
- **THEN** el sistema acepta (campo nullable)

#### Scenario: Beneficiario con notas

- **WHEN** un usuario crea "Mi barbero" con nota "Avenida Beni, cerca del semáforo"
- **THEN** la nota se almacena y se muestra en la UI

#### Scenario: Notas excesivamente largas rechazadas

- **WHEN** un usuario envía notas > 1000 caracteres
- **THEN** el sistema rechaza con error de validación

### Requirement: Soft delete

El sistema SHALL usar soft delete para preservar transacciones históricas del Change 7.

#### Scenario: Eliminación

- **WHEN** el dueño elimina un beneficiario
- **THEN** `deleted_at` se setea
- **AND** no aparece en listados ni dropdowns activos
- **AND** transacciones históricas (Change 7) permanecen consultables con withTrashed()

### Requirement: Autorización por dueño del presupuesto

El sistema SHALL restringir edición y eliminación al dueño del presupuesto.

#### Scenario: Intento ajeno

- **WHEN** un usuario intenta PUT o DELETE sobre beneficiario de otro usuario
- **THEN** el sistema retorna 403

### Requirement: Orden alfabético automático

El sistema SHALL ordenar beneficiarios alfabéticamente por nombre.

#### Scenario: Lista alfabética

- **WHEN** un usuario navega a `/beneficiarios`
- **THEN** los beneficiarios aparecen ordenados alfabéticamente