# categorias Specification

## Purpose
Permite al usuario clasificar sus transacciones futuras (Change 7) mediante una jerarquía de dos niveles: grupos de categorías con identidad visual (color, icono) y categorías individuales dentro de cada grupo. Los grupos y categorías viven dentro del contexto del presupuesto activo, siguiendo el método envelope de YNAB.

## Requirements

### Requirement: Grupos y categorías pertenecen a un presupuesto

El sistema SHALL asociar cada grupo a un presupuesto (obligatorio) y cada categoría a un grupo (obligatorio), estableciendo herencia de contexto.

#### Scenario: Aislamiento por presupuesto activo

- **WHEN** un usuario con presupuesto activo "Personal" navega a `/grupos-categorias`
- **THEN** ve únicamente los grupos y categorías de "Personal"
- **AND** no ve datos de otros presupuestos del mismo usuario

#### Scenario: Sin presupuesto activo

- **WHEN** un usuario sin presupuesto activo intenta acceder a `/grupos-categorias`
- **THEN** el sistema redirige a `/dashboard` con mensaje explicativo

### Requirement: Grupos con identidad visual heredada

El sistema SHALL asignar color e icono al grupo, no a las categorías individuales. Las categorías heredan visualmente del grupo padre.

#### Scenario: Grupo con color e icono válidos

- **WHEN** un usuario crea un grupo con color #ef4444 e icono "AlertCircle"
- **THEN** el sistema lo registra y la UI muestra ese acento en el header del grupo

#### Scenario: Color hex inválido rechazado

- **WHEN** un usuario envía un color no hex (ej: "red")
- **THEN** el sistema rechaza con error de validación

#### Scenario: Icono fuera de whitelist rechazado

- **WHEN** un usuario envía un icono no en la whitelist
- **THEN** el sistema rechaza con error de validación

### Requirement: Nombre único case-insensitive por scope

El sistema SHALL exigir nombres únicos case-insensitive de grupos dentro del mismo presupuesto, y de categorías dentro del mismo grupo.

#### Scenario: Grupo duplicado en mismo presupuesto rechazado

- **WHEN** un usuario intenta crear "Obligaciones" cuando ya existe "obligaciones" en el mismo presupuesto
- **THEN** el sistema rechaza con error

#### Scenario: Mismo nombre de grupo en distinto presupuesto permitido

- **WHEN** el mismo usuario crea "Obligaciones" en "Personal" y luego "Obligaciones" en "Freelance USD"
- **THEN** el sistema acepta ambas

#### Scenario: Categoría duplicada en mismo grupo rechazada

- **WHEN** un usuario intenta crear "Emergencia" cuando ya existe "emergencia" en el mismo grupo
- **THEN** el sistema rechaza

#### Scenario: Categoría con mismo nombre en otro grupo permitida

- **WHEN** un usuario crea "Emergencia" en "Ahorros" y luego "Emergencia" en "Salud"
- **THEN** el sistema acepta ambas

### Requirement: Cascada soft delete

El sistema SHALL usar soft delete y cascada en el orden: presupuesto → grupos → categorías.

#### Scenario: Eliminar grupo cascada categorías

- **WHEN** un usuario elimina un grupo con 3 categorías
- **THEN** el grupo se soft-deleta
- **AND** las 3 categorías también se soft-deletan

#### Scenario: Eliminar presupuesto cascada grupos y categorías

- **WHEN** un usuario elimina un presupuesto con grupos y categorías
- **THEN** el presupuesto se soft-deleta
- **AND** todos sus grupos y sus categorías se soft-deletan (cascada)

### Requirement: Categorías con solo nombre

El sistema SHALL modelar categorías con solo nombre editable, sin color ni icono propios (heredan del grupo).

#### Scenario: Categoría minimalista

- **WHEN** un usuario crea una categoría con solo nombre
- **THEN** el sistema la registra sin requerir color ni icono

#### Scenario: UI de categoría muestra solo texto

- **WHEN** un usuario ve la lista de categorías dentro de un grupo
- **THEN** cada categoría se muestra como texto simple con acciones (Editar, Eliminar)
- **AND** hereda el color/icono del grupo padre visualmente

### Requirement: Autorización por dueño del presupuesto

El sistema SHALL restringir edición y eliminación de grupos y categorías al dueño del presupuesto al que pertenecen.

#### Scenario: Intento de editar grupo ajeno

- **WHEN** un usuario intenta PUT `/grupos-categorias/{id}` sobre un grupo cuyo presupuesto pertenece a otro usuario
- **THEN** el sistema retorna 403

#### Scenario: Intento de editar categoría ajena

- **WHEN** un usuario intenta PUT `/categorias/{id}` sobre una categoría cuyo grupo pertenece a otro usuario
- **THEN** el sistema retorna 403

### Requirement: Orden alfabético automático

El sistema SHALL ordenar grupos y categorías alfabéticamente por nombre en la UI. Sin orden manual en Fase 1.

#### Scenario: Grupos alfabéticos

- **WHEN** un usuario navega a `/grupos-categorias`
- **THEN** los grupos aparecen ordenados alfabéticamente por nombre

#### Scenario: Categorías alfabéticas dentro del grupo

- **WHEN** un usuario ve las categorías dentro de un grupo
- **THEN** aparecen ordenadas alfabéticamente por nombre
