## Why

MyNAB debe soportar los 3 contextos monetarios que maneja el escenario oficial (María en Santa Cruz): **BOB** (ingresos y gastos locales), **USD** (freelance ocasional), **USDT** (wallet cripto). Para consolidar reportes multi-moneda al presupuesto activo (Change 8), el sistema necesita:

- Un catálogo global de monedas con símbolo y precisión decimal
- Tipos de cambio manuales que reflejen la realidad boliviana (spread bancario: 1 USDT compra a Bs 6.90, vende a Bs 7.10)
- Historial de tasas por fecha para que reportes de meses pasados usen las tasas correctas de esa época
- Cada usuario mantiene sus propias tasas (María en Santa Cruz vs Juan en Cochabamba pueden tener spreads distintos según sus fuentes P2P)

Este change también cierra la deuda dejada en Change 2: `presupuestos.moneda_base_id` pasa de nullable a NOT NULL con FK a la tabla `monedas`.

## What Changes

- Tabla `monedas` global (código como PK: "BOB", "USD", "USDT"), con nombre, símbolo, decimales, estado activo
- Tabla `tipos_cambio` por usuario, bidireccional, con fecha de vigencia
- Migración de `presupuestos.moneda_base_id`: nullable → NOT NULL con FK a `monedas.codigo` + data migration a BOB por defecto
- Vista `/monedas`: lista con toggle activar/desactivar (sin CRUD completo)
- Vista `/tipos-cambio`: CRUD completo del usuario auth, con filtro por par de monedas
- Actualización de `PresupuestoSelector.vue`: mostrar moneda base junto al nombre (ej: "Personal · BOB")
- Actualización de formularios Create/Edit de presupuestos: dropdown de moneda base (solo activas)
- Restricción: moneda base del presupuesto es fija una vez creada (no editable en Update)
- Service `TasaCambioService` para resolver la tasa vigente de un par en una fecha dada (usa la tasa más reciente ≤ fecha)
- Seeder: 3 monedas activas + 6 tipos de cambio de María (BOB↔USDT, BOB↔USD, USD↔USDT) con fecha reciente

## Capabilities

### New Capabilities
- `monedas`: catálogo global de monedas con activación por usuario y precisión decimal por moneda.
- `tipos-cambio`: tipos de cambio bidireccionales por usuario, versionados por fecha, con resolución automática de tasa vigente.

### Modified Capabilities
- `presupuestos`: la moneda base pasa de nullable a obligatoria y fija; el selector del navbar muestra la moneda base del presupuesto activo.

## Impact

- Cambio de esquema en `presupuestos.moneda_base_id` (nullable → NOT NULL con FK). Data migration asigna BOB por defecto a registros existentes.
- Los seeders de Change 2 (María con "Personal" y "Freelance USD") se actualizan para asignar explícitamente monedas correctas (BOB a Personal, USD a Freelance USD).
- Nuevo middleware compartido `HandleInertiaRequests` incluye `monedasActivas` para uso en dropdowns.
- Todos los changes posteriores que trabajen con montos multi-moneda (Change 4: cuentas, Change 7: transacciones, Change 8: consolidación) dependerán del `TasaCambioService` para conversiones.
- La restricción "moneda base fija" en presupuestos evita el problema de recálculo histórico masivo si el usuario cambia de idea.