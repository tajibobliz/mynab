# Design — Gestión de Cuentas

## Decisión 1: Cuenta pertenece a UN presupuesto (no compartida)

**Contexto:** YNAB real vincula cada cuenta a exactamente un presupuesto. María tiene "Personal" (BOB) y "Freelance USD" (USD) como presupuestos separados: si las cuentas fueran compartidas, los saldos y transacciones se mezclarían y perderíamos la separación de gestión que motiva tener múltiples presupuestos.

**Decisión:** `cuentas.presupuesto_id` FK obligatoria a `presupuestos.id`. Si el usuario tiene el mismo dinero físico distribuido entre dos "presupuestos" mentales, debe crear dos cuentas separadas (una en cada presupuesto).

**Consecuencia:** las cuentas se filtran por el presupuesto activo. `Index.vue` solo muestra cuentas del presupuesto activo actual. Cambiar de presupuesto activo cambia la vista de cuentas.

**Alternativa descartada:** cuenta compartida entre presupuestos del mismo usuario. Descartada porque agrega complejidad de "en qué presupuesto va este gasto" y no está en el escenario oficial.

## Decisión 2: Moneda de la cuenta independiente de la del presupuesto

**Contexto:** el escenario oficial exige que María tenga:
- BNB Checking en BOB (moneda base del presupuesto "Personal" = BOB) ✓ coincide
- Efectivo en BOB ✓ coincide
- **Binance USDT (moneda distinta a la del presupuesto)** ← este es el caso interesante

Sin cuentas multi-moneda, el escenario oficial no se puede modelar. Además YNAB real soporta esto.

**Decisión:** `cuentas.moneda_codigo` FK a `monedas.codigo`. Puede ser cualquier moneda activa, incluyendo una distinta a la del presupuesto.

**Consecuencia:** Change 8 usará `TasaCambioService::convertir()` para consolidar saldos en la moneda base del presupuesto. Change 7 permitirá transacciones directas en la moneda de la cuenta.

## Decisión 3: Enum PHP 8.4 para tipos de cuenta (no tabla)

**Contexto:** el docente definió exactamente 3 tipos: banco, efectivo, wallet. Sin tarjetas de crédito. No hay perspectiva de extensión inmediata que justifique una tabla.

**Decisión:** enum PHP nativo con backed values:

```php
enum TipoCuenta: string {
    case Banco = 'banco';
    case Efectivo = 'efectivo';
    case Wallet = 'wallet';
}
```

Cast automático en modelo Cuenta con `protected $casts = ['tipo' => TipoCuenta::class]`.

**Beneficios:**
- Type safety en PHP e IDE
- Cast automático string ↔ enum en Eloquent
- Validación implícita al asignar (rechazo de valores inválidos)
- Iconos por tipo definidos en el frontend con un mapa simple (banco → Landmark, efectivo → Banknote, wallet → Wallet de Lucide)

**Alternativa descartada:** tabla `tipos_cuenta` con FK. Descartada por overhead innecesario para 3 valores fijos.

## Decisión 4: Saldo inicial en centavos como bigInteger

**Contexto:** convención del proyecto (documentada en CLAUDE.md): todos los montos monetarios se guardan como `bigInteger` en centavos. Change 7 (transacciones) también usará centavos. Consistencia.

**Decisión:** `cuentas.saldo_inicial_centavos BIGINT NOT NULL DEFAULT 0`. El nombre incluye `_centavos` para que sea imposible confundirlo con "moneda base" (leer código años después).

**Formateo en frontend:** un helper `formatearMonto(centavos, moneda)` divide por `10^moneda.decimales` y aplica el símbolo. Ejemplo: `200000 / 100 = 2000.00 → "Bs. 2.000,00"` para BOB (2 decimales), o `5000000000 / 100 = 50.00000000 → "₮ 50.00000000"` para USDT (aunque display default es 2 o 4 decimales según UX).

**Alternativa descartada:** `decimal(20,2)`. Descartada porque rompe convención y agrega conversiones repetidas.

## Decisión 5: Saldo actual como accessor computed (no persistido)

**Contexto:** el saldo real de una cuenta cambia con cada transacción. Persistirlo como campo (`saldo_actual_centavos`) requiere que cada `TransaccionController::store/update/destroy` actualice el campo, con riesgo de:
- Race conditions bajo concurrencia (dos transacciones simultáneas)
- Desincronización si algo rompe (hook Eloquent que falla parcialmente)
- Historial inconsistente si se hace `TRUNCATE` o soft-delete de transacciones

**Decisión:** el modelo `Cuenta` expone un **accessor** `getSaldoActualCentavosAttribute()` que retorna:

```php
$this->saldo_inicial_centavos 
  + $this->transacciones()->where('tipo', 'inflow')->sum('monto_centavos')
  - $this->transacciones()->where('tipo', 'outflow')->sum('monto_centavos')
  + $this->transaccionesEntrantes()->sum('monto_centavos')  // transferencias entrantes
  - $this->transaccionesSalientes()->sum('monto_centavos')  // transferencias salientes
```

**En Change 4** (este change): el accessor retorna solo `saldo_inicial_centavos` porque la tabla `transacciones` aún no existe.

**En Change 7** (transacciones): se extiende el accessor a la suma completa.

**Rendimiento:** con índices en `transacciones.cuenta_id + tipo`, la suma es O(log n) sobre miles de registros. Suficiente para escala personal.

**Alternativa descartada:** campo persistido con hook Eloquent. Descartada por riesgos anteriores.

## Decisión 6: Soft delete con SoftDeletes trait

**Contexto:** las cuentas tienen transacciones asociadas. Si se hace hard delete y hay transacciones existentes, o rompen (FK), o se cascada y se pierde histórico. Soft delete resuelve ambos: la cuenta desaparece de la UI pero sus transacciones históricas permanecen consultables.

**Decisión:** `cuentas.deleted_at TIMESTAMPTZ NULL`, trait `SoftDeletes` en el modelo. Al eliminar via `$cuenta->delete()`, se setea `deleted_at`. Filtros por defecto excluyen soft-deleted (comportamiento estándar de Eloquent).

**Restore:** Fase 2. Por ahora solo delete unidireccional.

**Consecuencia:** el `saldo_actual` accessor debe seguir funcionando en cuentas soft-deleted (para reportes históricos de Change 8), pero la cuenta no aparece en dropdowns ni en `/cuentas` index.

## Decisión 7: Validación de moneda al crear/editar

**Contexto:** un usuario podría intentar asignar una moneda desactivada a una cuenta. Además, cambiar la moneda de una cuenta con transacciones existentes rompería toda la contabilidad (los montos son en centavos de una moneda específica).

**Decisión:**
- **Store:** `moneda_codigo` required, exists en monedas, WHERE activa=true (mismo patrón que Presupuesto)
- **Update:** `moneda_codigo` es **fija tras crear** (misma decisión que moneda base de presupuesto). El request no incluye la regla, y el controller aplica `array_diff_key` defensivo.

**UI:** el formulario Edit muestra la moneda como badge readonly + tooltip "No se puede cambiar la moneda después de crear la cuenta".

**Alternativa descartada:** permitir cambio con warning. Descartada por consistencia con la decisión de `moneda_base_codigo` fija en presupuestos.

## Decisión 8: Fecha de apertura obligatoria

**Contexto:** MyNAB planea Age of Money en Fase 2 (métrica: cuánto tiempo pasa entre que el dinero entra y sale). Requiere saber cuándo se abrió cada cuenta. Además, filtros por fecha en reportes históricos también lo necesitan.

**Decisión:** `cuentas.fecha_apertura DATE NOT NULL`. Se captura al crear. Editable (por si el usuario se equivoca al ingresar la fecha de una cuenta vieja).

**Validación:** `date, before_or_equal:today` (no puede ser futura).

## Decisión 9: Número de referencia opcional (últimos 4, alias)

**Contexto:** María puede tener "BNB Checking" y "BNB Ahorro" del mismo banco. Sin un identificador extra, ambas se ven idénticas en la lista.

**Decisión:** `cuentas.numero_referencia VARCHAR(50) NULL`. Formato libre — no se valida como número real (puede ser "**4417", "@usuario_binance", "cuenta oficial", etc.).

**UI:** se muestra como texto pequeño gris debajo del nombre en `CuentaCard`.

## Decisión 10: Vista integrada al presupuesto activo

**Contexto:** las cuentas están vinculadas a presupuestos. Si María tiene "Personal" activo, no tiene sentido mostrarle las cuentas de "Freelance USD".

**Decisión:**
- **Index** (`/cuentas`): muestra solo cuentas del presupuesto activo. Query: `Cuenta::where('presupuesto_id', auth()->user()->presupuesto_activo_id)`.
- **Create**: el formulario no muestra selector de presupuesto — usa el activo automáticamente.
- **Edit**: no permite cambiar `presupuesto_id` (fijo tras crear, misma lógica que moneda).
- **Sin presupuesto activo**: redirige a `/dashboard` con flash message "Necesitas un presupuesto activo para gestionar cuentas".

## Decisión 11: Agrupamiento visual por tipo en Index

**Contexto:** el orden natural de aparición de cuentas es por tipo: cuentas bancarias primero, efectivo después, wallets al final. Es la mental model contable típica.

**Decisión:** `Index.vue` agrupa por `TipoCuenta` en 3 secciones con headers ("Bancarias", "Efectivo", "Wallets"). Si un tipo no tiene cuentas, esa sección no se muestra.

**Alternativa descartada:** orden alfabético plano. Descartada por menor legibilidad cuando hay 5+ cuentas.