# Design — Gestión de Transacciones

## Decisión 1: 3 tipos (outflow, inflow, transfer) con enum

**Contexto:** YNAB real tiene estos 3 tipos. Transfer es su propio tipo porque involucra 2 cuentas, no un payee.

**Decisión:** columna `tipo` como enum backed `('outflow', 'inflow', 'transfer')`. Enum PHP `TipoTransaccion` con casos en español en el label pero valores en inglés (consistencia con `TipoCuenta` de Change 4).

**Razones:**
1. YNAB real: idéntico
2. Semántica clara en código: `TipoTransaccion::Outflow` vs string mágico
3. Transfer como tipo propio simplifica la validación: `cuenta_destino_id` solo tiene sentido en transfer
4. Permite polimorfismo visual (icono, color) por tipo

## Decisión 2: Split como tabla separada (1→N)

**Contexto:** se consideraron:
- A) Tabla separada `transacciones_split` con FK al padre
- B) Columna JSON `splits` en `transacciones`
- C) Columna `categoria_id` nullable + agregación manual

**Decisión:** A. Tabla `transacciones_split` con:
- `id`
- `transaccion_id` (FK `transacciones`, `cascadeOnDelete`)
- `categoria_id` (FK `categorias`, `restrictOnDelete`)
- `monto_centavos` (bigInteger, en moneda de la cuenta del padre)
- `notas` (text nullable)
- timestamps + soft delete

**Razones:**
1. **Change 8 depende de queries agregadas por categoría**: `SUM(monto)` sobre tabla relacional es idiomático. Sobre JSON requiere parsing (lento, no indexable).
2. **Relacional consistente**: toda otra entidad del proyecto es relacional. JSON sería un outlier.
3. **FK a categorias**: integridad referencial garantizada
4. **Soft delete independiente**: si se corrige un split borrando una línea sin borrar la transacción padre, se puede.

**Al crear transacción split:**
- `transacciones.es_split = true`
- `transacciones.categoria_id = NULL` (reside en los hijos)
- `transacciones.monto_centavos = SUM(hijos.monto_centavos)` validado en el request
- N registros en `transacciones_split`, uno por categoría

**Al borrar transacción split:**
- Hook `deleting()` en modelo `Transaccion` soft-elimina los `transacciones_split` hijos (patrón consolidado de Change 5 `GrupoCategoria`)

## Decisión 3: Monto en moneda de cuenta + columna convertida a moneda base

**Contexto:** María tiene cuentas en BOB, USD y USDT. "Ready to Assign" del Change 8 debe sumar todos los inflows en una sola moneda (la base del presupuesto).

**Decisión:** cada transacción almacena:
- `monto_centavos` (bigInteger) → en moneda de la cuenta
- `monto_moneda_base_centavos` (bigInteger) → convertido a moneda base del presupuesto, calculado al crear/editar usando `TasaCambioService` con tasa de `fecha_hora`
- `tasa_cambio_aplicada` (decimal 20,8 nullable) → la tasa usada en la conversión (null si moneda cuenta == moneda base, por identidad)

**Razones:**
1. **Consulta rápida en Change 8**: Ready to Assign suma `monto_moneda_base_centavos`, un solo SUM, sin resolver tasas en runtime
2. **Audit trail**: si una tasa se corrige después, las transacciones viejas conservan la tasa usada
3. **Reproducibilidad**: cualquier reporte puede mostrar "cuánto fue esto en BOB al momento de la transacción"

**Al editar transacción:** se recalcula `monto_moneda_base_centavos` con la tasa vigente a la `fecha_hora` actual (potencialmente distinta si cambió la fecha). Sin UI para "fijar" tasa manualmente en Fase 1.

**Transfer multi-moneda** (Change 7 lo cubre, caso real: Binance USDT → BNB BOB): se trata como una sola transacción con `monto_centavos` en moneda origen, `monto_moneda_base_centavos` convertido a base, y el saldo de la cuenta destino incrementa en moneda destino usando la tasa del par origen→destino de la misma fecha. Documentado en `TasaCambioService.convertirEntreMonedas()`.

## Decisión 4: Calculadora en el campo monto

**Contexto:** el docente pidió explícitamente campo monto tipo calculadora. YNAB real lo tiene.

**Decisión:** componente `MontoCalculadora.vue` que:
- Acepta tecleo de expresiones aritméticas (`50+30*2`, `(100-20)/4`)
- Evalúa onBlur usando librería `expr-eval` (segura, no usa `eval()`)
- Muestra el resultado calculado debajo del input: "= Bs 110.00"
- Si la expresión es inválida, borde rojo + mensaje "Expresión inválida"
- Si válida, el valor final enviado al backend es el resultado numérico
- Permite entrada decimal con punto y coma

**Librería `expr-eval`:**
- ~5KB
- No usa `eval()` ni `Function()` → sin riesgo de inyección
- Permite `+`, `-`, `*`, `/`, paréntesis
- Rechaza funciones no explícitamente permitidas

## Decisión 5: Timestamp con hora + zona horaria

**Contexto:** docente pidió fecha + hora. América/La_Paz UTC-04:00.

**Decisión:** columna `fecha_hora` tipo `timestampTz` (consistente con convención del proyecto: todas las fechas con hora en Change 3).

**UI:** date picker + time picker separados visualmente, pero el backend recibe un único timestamp ISO 8601. Default `now()` en zona del usuario.

**Validación:** debe ser `<= now() + 1 día` (permite transacciones futuras hasta un día para programar). Fecha en el futuro muy lejano = probable error de tipeo.

## Decisión 6: Validación condicional por tipo

**Contexto:** cada tipo tiene reglas distintas sobre qué FKs son requeridas/prohibidas.

**Decisión:**

| Tipo | `cuenta_id` | `cuenta_destino_id` | `categoria_id` | `beneficiario_id` | `es_split` |
|---|---|---|---|---|---|
| `outflow` | requerido | prohibido | requerido (si no es split) | opcional | permitido |
| `inflow` | requerido | prohibido | prohibido | requerido | prohibido |
| `transfer` | requerido | requerido (≠ cuenta_id) | prohibido | prohibido | prohibido |

**Razones de inflow sin categoria_id:**
- Siguiendo zero-based budgeting, todo ingreso entra a "Ready to Assign"
- Ready to Assign no es una categoría, es un bucket conceptual
- Si María recibe sueldo, no asigna en el momento del inflow; asigna después vía la UI del Change 8

**Razones de transfer sin categoria/beneficiario:**
- Transfer es movimiento interno, no gasto ni ingreso
- No afecta "Activity" de ninguna categoría
- No tiene payee externo

**Razones de es_split solo en outflow:**
- Un ingreso de split no tiene sentido (no se "divide" un sueldo en categorías)
- Un transfer tampoco (es movimiento interno)
- Solo gastos pueden dividirse

**Validación de FKs contra presupuesto activo (lección Changes 5-6):**
Cada FK se valida con `Rule::exists('tabla', 'id')->where('presupuesto_id', $presupuestoActivoId)`. Para cuentas, `beneficiarios`, `categorias` esto es directo. Para `categorias` en splits, se valida uno por uno.

## Decisión 7: Validación de split

**Contexto:** si transacción tiene `es_split = true`, debe tener ≥ 2 líneas y la suma debe cuadrar con el monto del padre.

**Decisión:**
- `es_split = true` requiere `splits` array con ≥ 2 elementos
- Cada split: `categoria_id` (válido en presupuesto activo), `monto_centavos > 0`, `notas` opcional
- Suma de `splits[*].monto_centavos` = `monto_centavos` del padre, tolerancia 0 (centavos son enteros, no hay rounding)
- Al menos 2 categorías distintas (no puede ser split a la misma categoría 2 veces)

**Edge case:** si la suma no cuadra, rechazar con mensaje "La suma de las líneas (Bs X) no coincide con el total (Bs Y)".

## Decisión 8: Soft delete en transacciones y splits

**Contexto:** historial financiero no se pierde. YNAB oculta transacciones borradas, no las purga.

**Decisión:**
- `transacciones.deleted_at` nullable timestampTz
- `transacciones_split.deleted_at` nullable timestampTz
- Hook `deleting()` en `Transaccion` soft-elimina los splits hijos
- Index filtra solo activos
- Fase 2: pantalla "Papelera" con restore

## Decisión 9: Saldo calculado en Cuenta

**Contexto:** Change 4 definió `saldo_actual` como accessor que retornaba `saldo_inicial` (porque no había transacciones). Ahora que existen, debe sumarlas.

**Decisión:** `Cuenta::saldoActualCentavos()` accessor:
```php
public function getSaldoActualCentavosAttribute(): int
{
    $outflows = $this->transacciones()
        ->where('tipo', 'outflow')
        ->sum('monto_centavos');
    
    $inflows = $this->transacciones()
        ->where('tipo', 'inflow')
        ->sum('monto_centavos');
    
    $transfersOut = $this->transacciones()
        ->where('tipo', 'transfer')
        ->sum('monto_centavos');
    
    $transfersIn = Transaccion::where('cuenta_destino_id', $this->id)
        ->where('tipo', 'transfer')
        ->sum('monto_centavos_destino');  // monto en moneda destino
    
    return $this->saldo_inicial_centavos + $inflows - $outflows - $transfersOut + $transfersIn;
}
```

**Nota:** transfer multi-moneda requiere columna adicional `monto_centavos_destino` en `transacciones` para el monto convertido a la moneda de la cuenta destino. Si origen y destino son misma moneda, es igual a `monto_centavos`.

**N+1:** el accessor dispara 4 queries por cuenta. Para el index de Cuentas (Change 4), es acceptable con pocas cuentas. Si María tiene 10+ cuentas, considerar un servicio con eager loading optimizado — backlog Fase 2.

## Decisión 10: Index con filtros

**Contexto:** María va a tener 50-200 transacciones por mes. Index sin filtros es inusable.

**Decisión:** filtros opcionales en query string:
- `?tipo=outflow` → solo gastos
- `?cuenta=5` → solo de cuenta 5
- `?categoria=12` → solo categoría 12 (incluye splits que tocan esa categoría)
- `?beneficiario=3` → solo beneficiario 3
- `?desde=2026-10-01&hasta=2026-10-31` → rango de fechas
- Combinables (AND)
- Default: últimos 30 días si no se pasa rango

**Paginación:** 50 por página con cursor pagination.

## Decisión 11: Seeder de María

**Contexto:** Change 8 necesita datos realistas para probar Ready to Assign / Activity.

**Decisión:** ~18 transacciones del mes actual en Personal:
- 2 inflows: Empresa X (sueldo Bs 3500), Cliente freelance A (USD 100 a BOB)
- Alquiler Bs 1500 (categoría Alquiler)
- SIM Entel Bs 50 (categoría Transporte... o Suscripciones? →