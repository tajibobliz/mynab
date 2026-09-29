## Purpose

Catálogo global de monedas del sistema MyNAB con código ISO 4217 o convención de mercado, símbolo visual, precisión decimal y estado activo. Cada moneda es identificable por su código de 3-5 caracteres y se usa como referencia en presupuestos y tipos de cambio.

## ADDED Requirements

### Requirement: Catálogo predefinido de monedas

El sistema SHALL proveer al menos 3 monedas activas predefinidas al momento de la instalación: BOB (Boliviano, símbolo Bs., 2 decimales), USD (Dólar estadounidense, símbolo $, 2 decimales), USDT (Tether USD, símbolo ₮, 2 decimales).

#### Scenario: Sistema recién instalado

- **WHEN** un usuario nuevo se registra y accede a `/monedas`
- **THEN** el sistema muestra al menos las 3 monedas predefinidas con estado activo

#### Scenario: Código como identificador natural

- **WHEN** cualquier tabla del sistema referencia una moneda
- **THEN** la referencia usa el `codigo` (string 3-5 chars) como foreign key, no un id numérico

### Requirement: Activación y desactivación por moneda

El sistema SHALL permitir a cualquier usuario autenticado activar o desactivar monedas del catálogo global.

#### Scenario: Desactivación exitosa

- **WHEN** un usuario envía POST `/monedas/USD/toggle` estando USD activa
- **THEN** el sistema cambia `activa` a false
- **AND** USD no aparece en dropdowns de moneda base de nuevos presupuestos
- **AND** los presupuestos existentes con USD como base siguen funcionando

#### Scenario: Bloqueo de desactivación si está en uso

- **WHEN** un usuario intenta desactivar una moneda que es `moneda_base_codigo` de algún presupuesto activo (no soft-deleted) de cualquier usuario
- **THEN** el sistema rechaza la operación con error "Esta moneda está en uso por: [lista de presupuestos]"
- **AND** la moneda mantiene su estado activo

#### Scenario: Reactivación

- **WHEN** un usuario activa una moneda que estaba desactivada
- **THEN** vuelve a aparecer en dropdowns

### Requirement: Precisión decimal por moneda

El sistema SHALL respetar la precisión decimal declarada en cada moneda al mostrar montos.

#### Scenario: Formateo de monto BOB

- **WHEN** el sistema muestra un monto de 350000 centavos en BOB (2 decimales)
- **THEN** el output visual es "Bs. 3.500,00" o equivalente localizado