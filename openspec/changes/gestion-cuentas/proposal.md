## Why

Sin cuentas, un presupuesto en MyNAB es un contenedor vacío: no hay dónde vivan los saldos ni dónde se registren las transacciones. Este change introduce la entidad `Cuenta`, base de todo el flujo transaccional que viene en Change 7.

El escenario oficial de María en Santa Cruz ilustra la necesidad de multi-moneda dentro de un mismo presupuesto:
- **BNB Checking** (BOB 2000) — cuenta bancaria en bolivianos
- **Efectivo** (BOB 300) — dinero físico en bolivianos
- **Binance USDT** (USDT 50) — wallet cripto en Tether

Las 3 cuentas viven dentro del presupuesto "Personal" (moneda base BOB), y el Change 8 usará el `TasaCambioService` del Change 3 para consolidar el saldo total en BOB.

Este change también respeta las restricciones del docente: **NO tarjetas de crédito con lógica especial, NO conciliación**. Las cuentas aquí son puros "contenedores de saldo" con transacciones simples de entrada/salida.

## What Changes

- Tabla `cuentas` con: nombre, tipo (enum), moneda propia (FK a monedas.codigo), saldo inicial (bigInteger centavos), fecha de apertura, número/alias de referencia opcional, soft delete
- Enum PHP 8.4 `TipoCuenta` con casos: `banco`, `efectivo`, `wallet`
- Cuenta pertenece a UN presupuesto (FK a presupuestos.id)
- Accessor `saldo_actual` en modelo Cuenta que retorna el saldo inicial (Change 7 lo extenderá con la suma de transacciones)
- CRUD completo `/cuentas`: index (agrupado por tipo), create, edit, show (placeholder para Change 7), destroy (soft delete)
- Vista integrada al presupuesto activo: solo se ven cuentas del presupuesto activo
- Redirect inteligente: si no hay presupuesto activo, redirige a crear uno
- Seeder de María con las 3 cuentas del escenario oficial, todas en el presupuesto "Personal"
- Formato visual de saldos usando decimales y símbolo de la moneda de cada cuenta (no la del presupuesto)
- Componente reutilizable `CuentaCard` con variantes `default` y `compact`

## Capabilities

### New Capabilities
- `cuentas`: cuentas por presupuesto con moneda propia, tipos limitados, saldo inicial en centavos, soft delete.

### Modified Capabilities
- Ninguna. `presupuestos`, `monedas` y `tipos-cambio` quedan intactas — este change consume esas capacidades pero no las modifica.

## Impact

- Change 7 (`gestion-transacciones`) extenderá el modelo `Cuenta` con relación `transacciones()` y el accessor `saldo_actual` sumará movimientos.
- Change 8 (`zero-based-budgeting-basico`) consumirá el saldo total consolidado en moneda base del presupuesto usando `TasaCambioService`.
- Nueva prop en `HandleInertiaRequests`: `cuentasDelPresupuestoActivo` para que dropdowns futuros (Change 7) muestren solo cuentas del contexto activo.
- Sin cambios de esquema en tablas existentes.
- Sin data migration destructiva.