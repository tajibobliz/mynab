# Design — Gestión de Monedas y Tipos de Cambio

## Decisión 1: Tabla `monedas` global (no por usuario)

**Contexto:** El código de moneda es estándar internacional (ISO 4217 para fiat: BOB, USD, EUR; convención de mercado para stablecoins: USDT, USDC). No tiene sentido que cada usuario redefina "Boliviano" en su propia tabla.

**Decisión:** Tabla `monedas` global, sin `user_id`. Seeder pre-carga BOB, USD, USDT como monedas activas.

**Consecuencia:** Un futuro admin o script puede agregar EUR, ARS, BRL, etc. sin afectar usuarios existentes. La activación/desactivación por usuario (si se quisiera) se resolvería con una tabla pivote `moneda_usuario`, pero eso es Fase 2. Por ahora, "activa/inactiva" es global en el sistema.

**Alternativa descartada:** monedas por usuario. Descartada porque agrega complejidad (cada seeder duplicaría BOB/USD/USDT por usuario) sin valor real.

## Decisión 2: `codigo` como primary key (no id auto-increment)

**Contexto:** El código de moneda es corto (3-5 chars), único, inmutable, e insensible al idioma. Es un identificador natural.

**Decisión:** `monedas.codigo` como PK (char 5). FK en otras tablas usa `moneda_codigo` en vez de `moneda_id`.

**Beneficios:**
- Queries legibles: `WHERE moneda_codigo = 'BOB'` sin joins
- Seeder no depende de orden de inserción
- No hay riesgo de que cambien IDs por reseed

**Alternativa descartada:** id auto-increment + columna `codigo` unique. Es la convención Laravel más común, pero para catálogos estables con identificadores naturales, la PK natural es más limpia.

## Decisión 3: Tipos de cambio bidireccionales (2 registros por par)

**Contexto:** En Bolivia el spread entre comprar y vender USDT es real (banco cambia diferente en cada dirección). Guardar solo una dirección y calcular la otra con `1/tasa` no captura esta asimetría.

**Decisión:** Cada par se guarda como 2 registros:
- `moneda_origen=BOB, moneda_destino=USDT, tasa=0.14` (compro USDT con BOB)
- `moneda_origen=USDT, moneda_destino=BOB, tasa=7.10` (vendo USDT a BOB)

**Consecuencia:** El usuario ingresa 2 tasas por par. La UI las agrupa visualmente ("BOB ↔ USDT" con 2 inputs).

**Cálculo automático (opcional):** al ingresar una dirección, la UI puede sugerir la inversa como `1/tasa` como default, pero el usuario la puede sobrescribir. En Fase 1 no implementamos esta sugerencia; el usuario ingresa las 2 tasas manualmente.

**Alternativa descartada:** una sola dirección con cálculo `1/tasa`. Descartada porque pierde la asimetría del spread real.

## Decisión 4: Tipos de cambio por usuario (no globales)

**Contexto:** Las tasas dependen de la fuente donde el usuario cambia (Binance P2P, banco, cambista de calle). Distintos usuarios en Bolivia pueden tener tasas distintas para el mismo par y fecha.

**Decisión:** `tipos_cambio.user_id` FK a `users`. Cada usuario mantiene sus propias tasas.

**Consecuencia:** El seeder crea 6 tipos de cambio para María (3 pares × 2 direcciones). Test User y otros usuarios arrancan sin tipos de cambio y deben ingresarlos.

**Alternativa descartada:** globales por admin. Descartada porque MyNAB es personal, no hay rol admin, y la tasa "oficial" no refleja la realidad P2P del usuario.

## Decisión 5: Precisión decimal — decimal(20,8) en columna `tasa`

**Contexto:** Las tasas cripto pueden requerir 8 decimales (1 BOB = 0.02083333 USDT). Los montos monetarios se guardan como enteros centavos, pero las tasas no.

**Decisión:** `tasa decimal(20, 8) NOT NULL`. PostgreSQL maneja decimales con precisión arbitraria sin errores de float.

**Alternativa descartada:** enteros × 10^8 (satoshi-style). Descartada porque agrega complejidad de conversión sin beneficio real para tasas (los errores de float en `decimal` de PostgreSQL no existen; el problema es específico de `float`/`double`).

## Decisión 6: Fecha de vigencia y resolución "tasa vigente en fecha X"

**Contexto:** Los reportes históricos deben usar las tasas de esa época, no las actuales. Si María gastó Bs 700 el 5 de septiembre y consolida en USDT el 1 de octubre, debe usar la tasa BOB→USDT del 5 de septiembre (o la más reciente anterior a esa fecha).

**Decisión:**
- `tipos_cambio.fecha DATE NOT NULL` — desde cuándo aplica esa tasa
- Unique compound: `(user_id, moneda_origen, moneda_destino, fecha)` — no puedes tener 2 tasas para el mismo par y fecha
- Service `TasaCambioService::resolver($userId, $origen, $destino, $fecha)` que retorna la tasa más reciente con `fecha <= $fecha` para ese usuario y par

**Casos edge:**
- No hay tasa para el par: retorna null. La UI muestra "Sin tasa configurada" y ofrece link a `/tipos-cambio/create` con el par prellenado.
- Fecha muy antigua sin tasa histórica: retorna la más reciente disponible (aunque sea posterior) con un flag `esExtrapolada=true` para que la UI lo indique.
- Origen = destino: retorna tasa 1.0 sin buscar en BD (identity case).

## Decisión 7: Moneda base del presupuesto es fija tras crear

**Contexto:** Cambiar la moneda base de un presupuesto implicaría recalcular todo el histórico de reportes. Es un feature de convergencia muy poco usado.

**Decisión:** En `Presupuesto` update, el campo `moneda_base_codigo` NO es editable. `UpdatePresupuestoRequest` lo omite de las reglas y el controller no lo actualiza aunque venga en el request.

**UI:** el formulario de Edit muestra la moneda como texto plano (no dropdown), con un tooltip explicando "La moneda base no se puede cambiar después de crear el presupuesto".

**Alternativa descartada:** permitir cambio con warning. Descartada porque requiere lógica de recálculo masivo; fuera del alcance de Fase 1.

## Decisión 8: Data migration en el cambio nullable → NOT NULL

**Contexto:** Change 2 creó `presupuestos.moneda_base_id` como nullable porque la tabla `monedas` no existía. Ahora hay que hacerlo NOT NULL sin romper los registros existentes.

**Decisión:** La migración de este change:
1. Crea tabla `monedas` (Fase migration 1)
2. Seedea BOB, USD, USDT (dentro de la misma migración, no en un seeder — para que aplique en producción sin `--seed`)
3. Renombra `presupuestos.moneda_base_id` a `presupuestos.moneda_base_codigo` (cambio de tipo)
4. UPDATE presupuestos SET moneda_base_codigo = 'BOB' WHERE moneda_base_codigo IS NULL
5. Aplica NOT NULL constraint + FK
6. Crea tabla `tipos_cambio`

**Alternativa descartada:** dejar `moneda_base_id` como bigint apuntando a `monedas.id` con auto-increment. Ya se decidió que la PK de monedas es el código (Decisión 2).

## Decisión 9: Vista `/monedas` solo con toggle activar/desactivar

**Contexto:** El seeder trae 3 monedas listas. El usuario típico no necesita crear EUR o BRL en Fase 1.

**Decisión:** La vista `/monedas` muestra las 3 monedas en una tabla con toggle. Sin botón "Crear moneda", sin borrado. Al desactivar una moneda, esta no aparece en dropdowns de nuevos presupuestos, pero los presupuestos existentes que ya la usan siguen funcionando.

**Validación:** no se puede desactivar la moneda base de algún presupuesto activo. UI muestra el mensaje "Esta moneda está en uso por: [Personal, Freelance USD]".

## Decisión 10: Vista `/tipos-cambio` con CRUD completo

**Contexto:** Los tipos de cambio son datos que el usuario debe poder ingresar, editar y borrar activamente.

**Decisión:** CRUD completo tipo el de presupuestos:
- Index: lista agrupada por par, con la tasa vigente actual y link a "Ver historial"
- Create: form con moneda origen (dropdown), moneda destino (dropdown), tasa, fecha
- Edit: mismos campos, sin cambiar moneda origen/destino (es la identidad del registro)
- Destroy: soft delete NO — hard delete. Los tipos de cambio son datos operacionales sin historial de auditoría (para eso está la fecha).
- Ver historial de un par: `/tipos-cambio?origen=BOB&destino=USDT` filtra la lista al par específico ordenado por fecha desc.