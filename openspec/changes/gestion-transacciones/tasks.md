## 1. Migración y modelo

**Hallazgo crítico verificado con tinker ANTES de asumir nada**: el plan
original (y tu comentario de aprobación) asumían que `Transaccion` no
necesitaría `$table` explícito, igual que `Categoria`/`Beneficiario`. Falso:
`Str::plural(Str::snake('Transaccion'))` = `'transaccions'` (el inflector en
inglés no reconoce el sustantivo español y solo agrega "s"), no
`'transacciones'`. Confirmado primero con `Str::plural`/`Str::snake` y
LUEGO con un insert real que falló con "undefined table transaccions" antes
de corregirlo. **Ambos modelos (`Transaccion` Y `TransaccionSplit`)
requieren `$table` explícito**, no solo el segundo como tasks.md asumía.

- [x] 1.1 Crear migración `create_transacciones_table`:
      - id
      - cuenta_id (FK cuentas.id, restrictOnDelete — no cascada, protege historial)
      - cuenta_destino_id (FK cuentas.id, restrictOnDelete, nullable)
      - categoria_id (FK categorias.id, restrictOnDelete, nullable)
      - beneficiario_id (FK beneficiarios.id, restrictOnDelete, nullable)
      - tipo (string, usará enum PHP)
      - monto_centavos (bigInteger)
      - monto_moneda_base_centavos (bigInteger)
      - monto_centavos_destino (bigInteger, nullable, solo para transfer multi-moneda)
      - tasa_cambio_aplicada (decimal 20,8, nullable)
      - fecha_hora (timestampTz)
      - notas (text nullable)
      - es_split (boolean default false)
      - timestamps + softDeletes
      - índices: cuenta_id, categoria_id, beneficiario_id, fecha_hora, (tipo, fecha_hora)

- [x] 1.2 Crear migración `create_transacciones_split_table`:
      - id
      - transaccion_id (FK transacciones.id, cascadeOnDelete)
      - categoria_id (FK categorias.id, restrictOnDelete)
      - monto_centavos (bigInteger)
      - notas (text nullable)
      - timestamps + softDeletes
      - índices: transaccion_id, categoria_id

- [x] 1.3 Enum PHP `app/Enums/TipoTransaccion.php` con casos Outflow/Inflow/Transfer, label() en español, icono() de Lucide (verificados: ArrowUpRight, ArrowDownLeft, ArrowLeftRight), color() token (status.danger/success/info)

- [x] 1.4 Modelo `app/Models/Transaccion.php`:
      - SoftDeletes, HasFactory, **`$table = 'transacciones'` explícito (ver hallazgo arriba)**
      - $fillable completo
      - $casts: tipo => TipoTransaccion::class, fecha_hora => datetime, es_split => boolean, monto_centavos/monto_moneda_base_centavos/monto_centavos_destino => integer, tasa_cambio_aplicada => decimal:8 (mismo cast que TipoCambio::tasa)
      - Relaciones: cuenta(), cuentaDestino(), categoria(), beneficiario(), splits() hasMany
      - Hook deleting() que soft-elimina splits (patrón Change 5)
      - **scope activos() omitido deliberadamente**: con SoftDeletes las filas borradas ya se excluyen automáticamente de cualquier query; un scope que no hace nada más que lo que Eloquent ya hace por defecto es abstracción sin necesidad real (YAGNI, confirmado contigo en el plan). Solo se implementó `scopeDelMes($year, $month)`, que sí tiene lógica propia (`whereYear`+`whereMonth`).

- [x] 1.5 Modelo `app/Models/TransaccionSplit.php`:
      - SoftDeletes, HasFactory, `$table = 'transacciones_split'` explícito
      - Verificado con tinker: `Str::plural(Str::snake('TransaccionSplit'))` = `'transaccion_splits'`, no `'transacciones_split'` — confirma la necesidad del `$table`
      - $fillable
      - Relaciones: transaccion() belongsTo, categoria() belongsTo

- [x] 1.6 Modificar `app/Models/Cuenta.php`:
      - transacciones() hasMany
      - transaccionesComoDestino() hasMany con foreignKey cuenta_destino_id
      - Accessor getSaldoActualCentavosAttribute() reescrito según design.md Decisión 9 (4 queries: outflows, inflows, transfersOut, transfersIn)

- [x] 1.7 Modificar `app/Models/Categoria.php`: transacciones() hasMany (vía categoria_id directo), splits() hasMany

- [x] 1.8 Modificar `app/Models/Beneficiario.php`: transacciones() hasMany

- [x] 1.9 Modificar `app/Models/Presupuesto.php`: 
      - **Decisión: `transacciones()` como `hasManyThrough(Transaccion::class, Cuenta::class)`** — idiomático, evita scopes manuales, resuelto en el plan y confirmado por el usuario

**Verificación end-to-end con tinker (no solo inferencia de nombres)**: tabla
de ambos modelos correcta tras el fix; `$fillable` completo en ambos; cast
`tipo` resuelve a instancia real de `TipoTransaccion`; relaciones inversas
(`cuenta()`, `categoria()`, `transaccion()` desde el split) correctas;
`splits()` cuenta 2 tras crear 2 líneas; `Presupuesto::transacciones()`
cuenta correctamente vía el join de `hasManyThrough`;
`transaccionesComoDestino()` cuenta 1 tras un transfer real;
`saldoActualCentavos` calculado coincide exactamente con la aritmética
esperada en ambas cuentas (origen y destino de un transfer); hook
`deleting()` soft-elimina ambos splits al borrar el padre. Datos de prueba
limpiados con `forceDelete()` al final.

`php artisan test`: 150/150 (144 passed + 6 skipped, 0 failed) — sin
regresiones tras modificar 4 modelos existentes.

## 2. Policies

- [x] 2.1 TransaccionPolicy:
      - viewAny/create = true
      - view/update/delete via $transaccion->cuenta->presupuesto->user_id (2 saltos)
      - restore/forceDelete = false

- [x] 2.2 TransaccionSplitPolicy: igual, via $split->transaccion->cuenta->presupuesto->user_id (3 saltos)
      - Load explícito en controller antes del authorize
      - Documentado en el propio archivo: sin rutas propias en este change, creada por completitud y como anticipación para Change 8

**Verificación tinker (matriz cruzada, 20 checks, todos OK):** María (dueña)
→ view/update/delete/viewAny/create todos `true` en ambas policies,
restore/forceDelete `false`. Usuario ajeno → view/update/delete/restore/
forceDelete todos `false` en ambas. Auto-discovery confirmado:
`Gate::getPolicyFor(Transaccion::class)` → `App\Policies\TransaccionPolicy`,
`Gate::getPolicyFor(TransaccionSplit::class)` → `App\Policies\TransaccionSplitPolicy`,
ninguna registrada manualmente en `AuthServiceProvider`.

`php artisan test`: 150/150 (144 passed + 6 skipped, 0 failed) — sin regresiones.

## 3. Requests

- [x] 3.1 StoreTransaccionRequest con validación condicional por tipo (ver design.md decisión 6):
      - prepareForValidation inyecta presupuesto_id del activo (contexto para validaciones)
      - rules() condicionales usando `TipoTransaccion::tryFrom()` (no comparación de string cruda) — si `tipo` es inválido, ninguna regla condicional extra se agrega (la regla `tipo` por sí sola ya reporta el problema, sin ruido)
      - Cada FK de cuentas/beneficiarios: `Rule::exists->where('presupuesto_id', activo)`
      - **categoria_id (directa y en splits): closure `whereHas('grupoCategoria', ...)`, NO `Rule::exists->where('presupuesto_id', ...)`** — `categorias` no tiene esa columna (hallazgo 2 del plan)
      - **es_split en inflow/transfer: `declined`, NO `prohibited`** (hallazgo 3 — `prohibited` rechaza incorrectamente el booleano `false`)
      - Validación split: `splits` array `min:2`, `splits.*.categoria_id` con `distinct` (built-in, cubre "no misma categoría 2 veces" sin lógica custom)
      - Validación suma split + validación de tasa de cambio disponible: ambas vía `withValidator()` (patrón nuevo del proyecto, ver 3.3)
      - Validación transfer: `different:cuenta_id`
      - Validación fecha_hora: `before_or_equal` a now+1 día

- [x] 3.2 UpdateTransaccionRequest:
      - Mismas reglas que Store PERO:
      - `tipo` y `es_split` se leen de `$this->route('transaccion')`, no están en `rules()` ni en el payload
      - `prepareForValidation` NO inyecta presupuesto_id (se lee directo de `$this->user()->presupuesto_activo_id` dentro de `rules()` para el scoping de FKs, sin validarlo como campo propio)
      - Mismo `withValidator()` duplicado (consistente con el patrón de duplicación Store/Update ya establecido en el proyecto desde Change 2, en vez de introducir una clase base compartida)

- [x] 3.3 Mensajes en español EXACTOS, verificados con script (ver abajo). `withValidator()` introducido por primera vez en el proyecto para las 2 validaciones cross-field que ninguna regla por campo puede expresar: suma de splits vs. monto del padre, y disponibilidad de tipo de cambio para la conversión.

**Verificación con `FormRequest::create()` + `setUserResolver()` + `Validator::make()` + invocación manual de `withValidator()`, organizada en los 4 sub-grupos pedidos (23/23 OK):**

| Sub-grupo | Casos | Resultado |
|---|---|---|
| A — reglas base | válido base, monto 0, fecha futura >1 día | 3/3 OK |
| B — condicionales por tipo | 3 por tipo (outflow/inflow/transfer): válido + 2 inválidos c/u | 9/9 OK |
| C — IDOR por FK | cuenta_id, cuenta_destino_id, categoria_id (whereHas), beneficiario_id, splits.\*.categoria_id (whereHas) ajenos | 5/5 OK |
| D — split + withValidator | suma correcta, 1 línea (min), categoría repetida (distinct), suma incorrecta, identity sin tasa, sin tasa real (USDT→USD) | 6/6 OK |

Mensajes `withValidator()` verificados literales:
- `"La suma de las líneas (Bs 11000) no coincide con el total (Bs 10000)."`
- `"No existe un tipo de cambio registrado para convertir de USDT a USD en la fecha 2026-10-01. Regístralo primero en Tipos de cambio."`

**2 bugs encontrados durante la verificación — ambos en los fixtures del script, NO en el Request** (mismo patrón de rigor que Changes 5-6: diagnosticar antes de concluir):
1. Los primeros 6 casos "válido" fallaban con "la cuenta no pertenece a tu presupuesto activo" — diagnóstico reveló que `presupuesto_activo_id` real de María en la BD de desarrollo apuntaba a "Freelance USD" (id 2), no "Personal" (id 1), arrastrado de una sesión Playwright anterior (Change 6) que cambia el presupuesto activo vía la UI real. El Request funcionaba correctamente — el fixture asumía "Personal" activo sin verificarlo. Corregido fijando `presupuesto_activo_id` explícitamente al inicio del script.
2. El caso D6 (sin tasa registrada) fallaba silenciosamente porque el usuario ad-hoc `$usuarioSinTasa` nunca tuvo `presupuesto_activo_id` seteado (crear un `Presupuesto` vía factory no lo marca como activo — eso solo lo hace el controller real al crear el primero), así que `cuenta_id` fallaba primero por "no pertenece a tu presupuesto activo", enmascarando el caso real a probar. Corregido fijando el activo explícitamente tras crear el fixture.

`php artisan test`: 150/150 (144 passed + 6 skipped, 0 failed). BD de desarrollo limpia tras la verificación (2 presupuestos de María, `presupuesto_activo_id` corregido a Personal).

## 4. Services (lógica de conversión)

- [x] 4.1 Crear `app/Services/CalculadoraTransaccionService`:
      - `resolverMontoMonedaBase(int $montoCentavos, string $monedaCuenta, string $monedaBase, int $userId, Carbon $fecha): array` — **firma con `$userId` explícito** (hallazgo 4 del plan: `TasaCambioService` resuelve tasas por usuario, no por presupuesto)
        → retorna [monto_moneda_base_centavos, tasa_aplicada]
      - Usa `TasaCambioService` internamente vía constructor injection (no `app()` ad-hoc por método, más testeable)
      - Si moneda cuenta == moneda base: retorna [montoCentavos, null]
      - Si `TasaCambioService::resolver()` retorna null: lanza `RuntimeException` (no debería ocurrir en producción — `StoreTransaccionRequest::validarTasaDisponible()` ya lo bloqueó en validación; si se dispara, es una inconsistencia validar-vs-persistir, no un caso normal)

- [x] 4.2 Para transfer multi-moneda:
      - `resolverMontoDestino(int $montoCentavos, string $monedaOrigen, string $monedaDestino, int $userId, Carbon $fecha): int`
      - Retorna el monto en moneda destino

- [x] 4.3 Tests unitarios del service en `tests/Unit/Services/CalculadoraTransaccionServiceTest.php`

**Corrección sobre el snippet propuesto:** el snippet del mensaje de aprobación tenía 2 desajustes con el contrato REAL de `TasaCambioService` (verificado leyendo el código fuente antes de implementar, como pediste):
1. `resolver()` retorna un **array** (`['tasa' => string, 'esExtrapolada' => bool, 'fechaResuelta' => Carbon]`), no un objeto — `$tasa->tasa` habría sido un fatal error; es `$resuelto['tasa']`.
2. `convertir()` tiene la firma real `convertir(int $userId, int $montoCentavos, string $origen, string $destino, Carbon $fecha): ?int` — recalcula la tasa internamente, no recibe una tasa ya resuelta como segundo argumento. El snippet lo llamaba como `convertir($montoCentavos, $tasa->tasa)`, que no existe. Implementado llamando `convertir()` con la firma real (vuelve a resolver la tasa internamente — 2 queries en vez de 1, aceptable para una operación de creación/edición, no un loop).

Confirmado además: `TasaCambioService::resolver()` nunca lanza excepción, siempre retorna `null` cuando no hay tasa — el `throw` es responsabilidad exclusiva de `CalculadoraTransaccionService`, no de `TasaCambioService` (contrato de Change 3 intacto).

**Infraestructura de tests:** `tests/Unit/` no estaba vinculado a `Tests\TestCase` en `tests/Pest.php` (solo `Feature` lo estaba) — sin bootstrap de Laravel, sin `app()`, sin DB. Se agregó `'Unit'` a `pest()->extend(TestCase::class)->in(...)` para habilitar tests unitarios con acceso real a la app (necesario para este service, que depende de `TipoCambio` en BD). No afecta `tests/Unit/ExampleTest.php` (declara su propia clase `extends PHPUnit\Framework\TestCase` explícitamente, ignora el binding de Pest). Decisión útil también para Change 8.

**6 tests unitarios (6/6 OK en el primer intento):**
- Identity BOB→BOB: retorna `[monto, null]`
- USD→BOB con tasa 6.96 vigente: convierte correctamente (10000→69600, tasa `'6.96000000'`)
- USDT→BOB con 2 tasas en fechas distintas: resuelve la vigente a la fecha consultada (no la más reciente del catálogo)
- `resolverMontoDestino` identity: retorna el mismo monto
- `resolverMontoDestino` con conversión real: 10000 USDT → 70000 BOB (tasa 7.00)
- `resolverMontoMonedaBase` sin tasa disponible: lanza `RuntimeException` con mensaje exacto

`php artisan test`: **156/156** (150 passed + 6 skipped, 0 failed). Delta exacto 150 → 156 (+6).

## 5. Controller

- [x] 5.1 TransaccionController con index/create/store/edit/update/destroy
- [x] 5.2 Guard presupuestoActivoOrRedirect() en index/create (patrón Presupuesto|RedirectResponse consolidado desde Change 4)
- [x] 5.3 index() con filtros:
      - método privado aplicarFiltros(Builder $q, Request $r): Builder
      - Filtros opcionales: tipo, cuenta, categoria, beneficiario, desde, hasta
      - Default: últimos 30 días
      - `cursorPaginate(50)` + `withQueryString()`
      - Eager loading: cuenta, cuentaDestino, categoria.grupoCategoria, beneficiario, splits.categoria.grupoCategoria

- [x] 5.4 store():
      - DB::transaction() porque crea padre + N splits
      - Calcula monto_moneda_base_centavos con CalculadoraTransaccionService
      - Si transfer multi-moneda: calcula monto_centavos_destino
      - Si es_split: crea los N registros en transacciones_split
      - Flash success

- [x] 5.5 update(): similar a store, DB::transaction, recalcula montos, **reemplazo completo de splits** (soft-delete de todos los existentes + recreación, decisión explícita del plan — hallazgo 7)

- [x] 5.6 destroy(): soft delete (hook del modelo cascada a splits)

**Verificación con controller instanciado directo + `FormRequest::validateResolved()` + ruta efímera (mismo patrón de Change 6 Grupo 4, incluyendo `refreshNameLookups()`) — 15/15 OK:**

| # | Caso | Resultado |
|---|---|---|
| 1 | index sin presupuesto activo → RedirectResponse + flash.danger exacto | OK |
| 2 | index con filtro `tipo=outflow` → responde (no redirect), query SQL real confirmada con `DB::listen` (incluye `and "tipo" = ?`) | OK |
| 3 | store outflow simple crea registro | OK |
| 3b | monto_moneda_base_centavos correcto (BOB=BOB, identity → tasa null) | OK |
| 4 | store transfer crea con `monto_centavos_destino` correcto | OK |
| 5 | store split crea 1 padre (`categoria_id=null`) | OK |
| 5b | store split crea 2 hijos atómicamente | OK |
| 6 | update: splits viejos soft-deleted | OK |
| 6b | update: 2 splits nuevos con suma correcta (10000) | OK |
| 7 | destroy: padre soft-deleted | OK |
| 7b | destroy: hijos soft-deleted (hook cascada) | OK |
| 8 | destroy ajeno → `AuthorizationException` | OK |
| 8b | transacción ajena intacta tras intento | OK |

**Nota de diagnóstico (caso 6b):** el primer intento del check falló con `count=2 suma=10000` — valores EXACTAMENTE correctos, pero la comparación `$sumaActual === 10000` fallaba. Diagnóstico con `var_dump` reveló que `Eloquent::sum('monto_centavos')` sobre Postgres retorna un **string** `"10000"`, no un int (consistente con cómo Postgres devuelve `SUM(bigint)` como `numeric`). Era un defecto de tipado en el *script de verificación* (comparación `===` sin castear), no en el controller — los datos persistidos eran correctos desde el primer intento. Confirmado que esto NO afecta `StoreTransaccionRequest::validarSumaSplits()` (que suma una `Collection` en PHP puro desde el payload, nunca toca la BD).

**CHECKPOINT CRÍTICO**: `php artisan test` → 156/156 (150 passed + 6 skipped, 0 failed) confirmado ANTES de pasar al Grupo 6. BD de desarrollo verificada limpia tras la verificación.

## 6. Rutas

- [x] 6.1 Route::resource('transacciones', TransaccionController::class)->except('show')
- [x] 6.2 **CORRECCIÓN CRÍTICA — "sin guión" NO bastaba aquí**, a diferencia de `categorias`/`beneficiarios`. Verificado con tinker: `Str::singular('transacciones')` = `'transaccione'` (el inflector en inglés trata el final "-es" como el patrón inglés "boxes"→"box", no reconoce el "-ciones" español), mientras que `Str::snake('Transaccion')` = `'transaccion'` (sin la "e" final). Sin `->parameters()`, el wildcard por defecto habría sido `{transaccione}`, que NUNCA calza con `TransaccionController::update(Transaccion $transaccion)` — mismo bug silencioso de `tipos-cambio` en Change 3. Agregado `->parameters(['transacciones' => 'transaccion'])`.
- [x] 6.3 Verificación empírica binding (patrón Change 6: match + `ImplicitRouteBinding::resolveForRoute()`)

**Esto invalida una asunción explícita de tasks.md y del plan aprobado**
("Sin guión → wildcard {transaccion} resuelve naturalmente") — la regla real
no es "sin guión = seguro", sino "verificar siempre con tinker", que es
exactamente lo que se hizo antes de confiar en el supuesto. Lección para
Change 8: cualquier sustantivo español terminado en "-ión"/"-ón" (ya
anotada en Change 7 Grupo 1 para `$table`) aplica el MISMO riesgo a nivel
de rutas `Route::resource`, no solo a nivel de nombres de tabla.

**`route:list --name=transacciones`** (6 rutas, wildcard `{transaccion}` confirmado tras el fix):
```
GET|HEAD  transacciones                      transacciones.index
POST      transacciones                      transacciones.store
GET|HEAD  transacciones/create               transacciones.create
PUT|PATCH transacciones/{transaccion}        transacciones.update
DELETE    transacciones/{transaccion}        transacciones.destroy
GET|HEAD  transacciones/{transaccion}/edit   transacciones.edit
```

**Verificación empírica del binding real:** match + `ImplicitRouteBinding::resolveForRoute()`
sobre un `PUT /transacciones/{id}` real → resuelve la instancia correcta de
`Transaccion` por ID y monto.

**Curl sin sesión:**
- `GET /transacciones` → `302` → `/login` ✅
- `POST /transacciones` sin CSRF → `419` ✅

`php artisan test`: 156/156 (150 passed + 6 skipped, 0 failed).

## 7. HandleInertiaRequests

- [x] 7.1 Agregar transaccionesRecientesDelPresupuestoActivo closure
      - Últimas 20 por fecha_hora desc
      - Eager loading optimizado (columnas seleccionadas: `cuenta:id,nombre,moneda_codigo`, `categoria:id,nombre`, `beneficiario:id,nombre`)
      - Para Dashboard futuro (Change 8 ya lo necesita)

**Desviación deliberada del patrón `loadMissing`** usado en los 3 closures
anteriores (documentada en el propio archivo): `Presupuesto::transacciones()`
es un `hasManyThrough`, y `loadMissing()` traería TODAS las transacciones
del presupuesto a memoria antes de poder aplicar un `limit()` en PHP — con
potencialmente cientos de filas por mes, exactamente la query que este prop
necesita evitar. Query directa con `limit(20)` en SQL es la única forma
correcta de limitar en la base de datos.

**Verificación N+1 con `DB::listen` (instancia fresca de usuario, sin relaciones cacheadas):**

| Escenario | Queries | Resultado |
|---|---|---|
| 0 transacciones | **1** (`select * from transacciones ... limit 20`) | `[]` |
| 5 transacciones (4 outflow + 1 inflow con beneficiario) | **4** | 5 filas |

El primer intento de verificación con 5 outflows puros dio 3 queries, no 4 —
no fue un bug: Eloquent omite inteligentemente la query de `beneficiario`
cuando NINGUNA fila tiene `beneficiario_id` no-nulo (nada que buscar). Al
incluir un inflow con beneficiario real en el fixture, las 4 queries
aparecen exactamente como se esperaba. Mismo principio de diagnóstico que
Grupo 5 (caso 6b): verificar antes de concluir, incluso cuando el resultado
"no cuadra" con la expectativa — a veces la expectativa es la que estaba
incompleta, no el código.

`php artisan test`: 156/156 (150 passed + 6 skipped, 0 failed). BD de desarrollo limpia tras la verificación.

## 8. Componente MontoCalculadora.vue (frontend)

- [x] 8.1 ~~npm install expr-eval~~ **REVERTIDO — ver hallazgo crítico de seguridad abajo.**
- [x] 8.2 Crear resources/js/Components/MontoCalculadora.vue:
      - Input que acepta expresiones
      - OnBlur: evalúa con parser propio (ver hallazgo)
      - Si inválido: borde rojo + mensaje "Expresión inválida"
      - Debajo: "= [moneda] [valor formateado]"
      - Props: modelValue (centavos), monedaCodigo, disabled, placeholder, id
      - Emits: update:modelValue (centavos), update:valido (boolean)
      - Reutilizable para hijos de split (Grupo 9)

**HALLAZGO CRÍTICO DE SEGURIDAD — expr-eval descartado.** `npm install
expr-eval` se completó sin error, pero `npm audit` reveló **3 CVEs críticos
sin parche disponible**: Prototype Pollution (GHSA-8gw3-rxh4-v6jx), no
restringe funciones pasadas a `evaluate()` (GHSA-jc85-fpwf-qm7x), y Code
Execution (GHSA-q9v2-7m5w-4693). El claim de design.md ("segura, no usa
eval()") resultó incompleto: la librería no usa `eval()` de JS, pero su
propio parser tiene huecos explotables equivalentes. Consultado con el
usuario (riesgo real bajo — el usuario solo evalúa su propio input en su
propio navegador, sin cruce de confianza entre usuarios — pero 3 CVEs
críticos en una dependencia nueva no se aceptan sin más), **se descartó
expr-eval y se construyó un parser propio** (decisión ya pre-autorizada
como fallback aceptable en el mensaje de kickoff del grupo).

**`resources/js/utils/evaluarExpresion.js`** (nuevo): shunting-yard clásico
sin dependencias — tokeniza → notación postfija → evalúa. Soporta `+ - * /
()`, decimales con punto o coma, y unario `+`/`-` con precedencia propia
(distinta de la binaria). **Primer intento del algoritmo tenía un bug real**:
el truco ingenuo de "insertar 0 antes del menos unario" (`-5` → `0 - 5`)
rompe la precedencia cuando el unario sigue a un operador de mayor
precedencia ya en la pila — `"3*-5"` evaluaba a `-5` en vez de `-15`.
Corregido dando al unario su propia precedencia (la más alta, asociativa a
la derecha) y evaluándolo como operador de 1 solo operando en la pila
postfija, no como resta binaria con un 0 implícito. **Verificado con un
script Node standalone, 27 casos (16 válidos + 11 inválidos), 27/27 OK**
antes de integrarlo en el componente Vue — incluyendo el caso `"3*-5"` que
habría fallado silenciosamente con el primer enfoque.

**Verificación Playwright del componente montado (9/9 PASS, 0 errores de
consola)**, usando una página y ruta temporales (`__TestMontoCalculadora.vue`
+ `/__test/monto-calculadora`) creadas solo para este grupo y **eliminadas
antes de cerrarlo** (`npm run build` posterior confirma que no quedó
referencia alguna):

| Caso | Resultado |
|---|---|
| `"50+30*2"` → preview `"= Bs. 110.00"`, emite 11000 centavos | PASS |
| `"50+"` → borde rojo + "Expresión inválida" | PASS |
| Input vacío → sin error, emite `modelValue=null` | PASS |
| `"100"` → `"= Bs. 100.00"` | PASS |
| `"100.50"` → `"= Bs. 100.50"` | PASS |
| Recuperación: de inválido a válido limpia el mensaje de error | PASS |

(Nota: el símbolo real de BOB en el seed es `"Bs."` con punto, no `"Bs"` —
corregido en las aserciones del script tras verificarlo en la BD, no
asumido.)

`npm run build`: limpio antes y después de la limpieza de la página/ruta
temporal. `package.json` sin cambios netos (expr-eval instalado y
desinstalado, `npm audit` final: 0 vulnerabilidades). `php artisan test`:
156/156 (150 passed + 6 skipped, 0 failed) — frontend puro, no afecta backend.

## 9. Vistas Inertia

Trabajado por sub-grupos (A: selectores, B: SplitForm + Create, C: Edit +
Filtros, D: Index). Progreso marcado dentro de cada tarea.

- [x] 9.1 Pages/Transacciones/Index.vue: **completado (Sub-grupo D)**
      - Lista con fecha_hora formateada ("01 oct 14:30"), tipo (icono+color en badge), descripción según tipo (outflow: Beneficiario→Categoría o "Dividida"; inflow: "Ingreso de" Beneficiario; transfer: Cuenta→Cuenta destino), monto en moneda de la cuenta
      - FiltrosTransacciones.vue arriba, `router.get(..., {preserveState, preserveScroll, replace: true})` al emitir
      - Cursor pagination: botones Anterior/Siguiente deshabilitados según `prev_page_url`/`next_page_url`
      - Botón "Nueva transacción"
      - Click en item (con `@click.stop` en el botón Eliminar para no interferir) navega a Edit
      - Empty state con icono `Receipt`
      - ConfirmationModal para eliminar

**Ajuste al controller necesario para esta vista**: `TransaccionController::index()`
no pasaba `tiposTransaccion` (solo `create()`/`edit()` lo hacían) — sin eso,
Index.vue no tenía forma de resolver el icono/color por tipo ya que
`transaccion.tipo` serializa como el string plano del enum (`"outflow"`),
no el objeto `{value,label,icono,color}`. Agregado al payload. También se
agregó `cuenta.moneda` al eager loading (faltaba, necesario para
`formatearMonto()` por fila).

**Verificación Playwright (empty state 1/1 + lista poblada 10/10, 0 errores
de consola)**: empty state correcto sin transacciones; con 4 transacciones
ad-hoc (outflow con beneficiario, inflow, transfer, outflow con notas) se
confirmó cada descripción según tipo, monto formateado, notas visibles;
filtro por tipo reduce la lista a la cantidad exacta esperada y "Limpiar
filtros" la restaura; click en item navega a `/transacciones/{id}/edit`;
eliminar con confirmación reduce la lista en 1 y se confirmó con tinker que
es soft delete real (`withTrashed()->count()` = activas + 1).

Nota de rigor aplicada (lección ya consolidada en Change 7): el primer
borrador del script de verificación usaba `waitForLoadState('networkidle')`
tras aplicar un filtro — corregido antes de ejecutar a `waitForFunction()`
sobre la cantidad real de ítems en el DOM, respetando la regla del proyecto
contra `networkidle` + snapshot para verificación post-mutación.

Verificación hecha con 4 transacciones ad-hoc creadas y limpiadas
(`forceDelete`) vía tinker; sin páginas/rutas temporales (Index.vue usa la
ruta real `/transacciones`).

`npm run build`: limpio. `php artisan test`: 156/156 (150 passed + 6 skipped, 0 failed).

## Grupo 9 completo

Los 4 sub-grupos (A: 3 selectores, B: SplitForm + Create, C: Edit + Filtros,
D: Index) están cerrados. 8 archivos nuevos + 1 util compartido
(`fechaHora.js`) + 2 fixes retroactivos a selectores de 9A (placeholder
seleccionable + bug `Number('')===0`) + 1 ajuste al controller del Grupo 5
(`tiposTransaccion` + `cuenta.moneda` en `index()`). Suite backend estable
en 156/156 durante todo el grupo. 0 regresiones.

- [x] 9.2 Pages/Transacciones/Create.vue: **completado (Sub-grupo B)**
      - Selector de tipo (outflow/inflow/transfer) prominente arriba (3 botones grandes con icono + color por tipo)
      - Según tipo, muestra/oculta campos:
        * cuenta_id (siempre, label dinámico "Cuenta"/"Cuenta origen")
        * cuenta_destino_id (solo transfer, excluye cuenta_id vía excluirId)
        * categoria_id (solo outflow sin split)
        * beneficiario_id (outflow opcional, inflow requerido, label dinámico)
      - MontoCalculadora para el monto (moneda de la cuenta seleccionada, no la base del presupuesto)
      - DateTimePicker: date + time HTML5 nativos, combinados en `fecha_hora` ISO vía `form.transform()` antes de postear
      - Toggle "Dividir en categorías" (solo outflow) → abre SplitForm.vue
      - Suma de splits visible en tiempo real (delegado a SplitForm.vue)
      - Nota debajo del total: "Esta transacción se registrará en el presupuesto {activo.nombre}"
      - Botones: Cancelar + Guardar

- [x] 9.3 Pages/Transacciones/Edit.vue: **completado (Sub-grupo C)**
      - Mismo layout que Create, factorizando `fechaYHoraLocal()`/`combinarFechaHora()` (nuevo util compartido `resources/js/utils/fechaHora.js`, usado también por Create.vue tras refactor)
      - Tipo fijo mostrado como **badge no-interactivo** (icono+label, sin botones) en vez de 3 botones deshabilitados — más honesto sobre que no es una decisión disponible, no solo "deshabilitada" (decisión explícita, ver nota abajo)
      - Toggle split: `<Checkbox disabled>` mostrando el valor real + texto "(fijo tras crear)"
      - Si venía con splits, los precarga completos en SplitForm (categoria_id, monto_centavos, notas por línea)
      - Botón "Guardar cambios", "Cancelar" vuelve a /transacciones

- [x] 9.4 Componente FiltrosTransacciones.vue: **completado (Sub-grupo C)**
      - Selectores de tipo, cuenta, categoria, beneficiario (reutiliza Selector{Cuenta,Categoria,Beneficiario}.vue con placeholder "Todas/Todos los X")
      - Date range picker (desde/hasta, inputs nativos)
      - Botón "Limpiar filtros" (resetea los 6 campos)
      - Debounce de 300ms (watch con deep + setTimeout/clearTimeout, sin librería) antes de emitir update:modelValue

**Decisión de diseño — tipo fijo en Edit como badge, no botones**: mostrar 3
botones (2 deshabilitados + 1 "activo" pero igual sin click) sugeriría
falsamente que cambiar de tipo sigue siendo una opción disponible, solo que
bloqueada. Un único elemento informativo (icono + label, sin rol de botón)
comunica mejor que el tipo ya no es una decisión en esta pantalla.

**Fix retroactivo en SelectorCuenta.vue y SelectorCategoria.vue (Sub-grupo A)**,
necesario para que FiltrosTransacciones.vue funcionara: el `<option value="">`
placeholder tenía `disabled`, correcto para un campo requerido (Create/Edit)
pero roto para un filtro opcional — una vez elegida una cuenta/categoría, no
había forma de volver a "Todas". Se quitó el `disabled`. Al quitarlo
apareció un bug real latente que el `disabled` había estado enmascarando:
`@change` hacía `Number($event.target.value)`, y `Number('') === 0` en
JavaScript — seleccionar el placeholder habría emitido `0` (un ID de cuenta
inexistente) en vez de `''` (sin selección). Corregido con un chequeo
explícito antes de castear. Verificado con Playwright que ahora emite `''`
correctamente.

**Verificación Playwright (Edit.vue: 14/14, FiltrosTransacciones: 7/7, 0
errores de consola en ambos)**: badge de tipo fijo sin botones clicables;
monto/notas/checkbox precargados correctamente para una transacción outflow
simple; para una transacción split, checkbox marcado, categoría principal
oculta, SplitForm precargado con las 2 líneas reales (montos y notas
exactos), resumen de suma en ✓; **submit real de edición con cambio de
monto → PUT responde 303, valor persistido confirmado con tinker
(20000 centavos exacto)**. FiltrosTransacciones: debounce colapsa 3 cambios
rápidos en 1 sola emisión (no 3), el valor final emitido es el último
tecleado, filtro de cuenta emite ID numérico, volver a "Todas las cuentas"
emite `''` (no `0`, confirma el fix), "Limpiar filtros" resetea los 6 campos.

Verificación realizada con 2 transacciones ad-hoc reales (un outflow simple
y un outflow split) creadas vía tinker y limpiadas (`forceDelete`) al
terminar, más una página/ruta temporal para FiltrosTransacciones.vue
(eliminada antes de cerrar el sub-grupo). Submission completa de
Transacciones/Create.vue (Sub-grupo B) se verificó indirectamente aquí: el
backend ya confirmó poder procesar creates/updates reales end-to-end antes
de que exista Index.vue.

`npm run build`: limpio. `php artisan test`: 156/156 (150 passed + 6 skipped, 0 failed).

- [x] 9.5 Componente SelectorCategoria.vue (reutilizable): **completado (Sub-grupo A)**
      - `<select>` nativo con `<optgroup>` (grupos como headers NO seleccionables, nativo del HTML — sin custom dropdown)
      - Usa gruposCategoriasDelPresupuestoActivo del share
      - Reutilizado en SplitForm.vue por línea

- [x] 9.6 Componente SelectorBeneficiario.vue: **completado (Sub-grupo A)**
      - Autocompletar hecho a mano (sin librería): filtro por computed sobre el array ya cargado del share
      - Usa beneficiariosDelPresupuestoActivo del share
      - Opción "Crear nuevo..." al final → `<Link>` a Beneficiarios/Create (Fase 2 hará inline-create)
      - 2 bugs encontrados y corregidos: `id` caía en el `<div>` raíz en vez del `<input>` (fallthrough automático, elemento raíz no es el control semántico — fix: `id` explícito + `inheritAttrs: false`); un segundo click sobre el input ya enfocado no reabría la lista (`focus` no se re-dispara en un elemento ya enfocado tras `mousedown.prevent` — fix: `@click` como disparador adicional idempotente)

- [x] 9.7 Componente SelectorCuenta.vue: **completado (Sub-grupo A)**
      - `<select>` nativo con `<optgroup>` por tipo (banco/efectivo/wallet)
      - Muestra saldo actual al lado del nombre (`formatearMonto`)
      - Recibe `cuentas` como prop explícito (no lee del share directo) + `excluirId`, para reusar la misma instancia en cuenta_id y cuenta_destino_id

- [x] 9.8 (no listado originalmente, sugerido y aprobado) Componente SplitForm.vue: **completado (Sub-grupo B)**
      - Lista dinámica de líneas (`modelValue` array), cada una con SelectorCategoria + MontoCalculadora + input de notas corto
      - Mínimo 2 líneas (botón Eliminar deshabilitado si `length <= 2`)
      - Resumen de suma en tiempo real: verde + ✓ si cuadra, rojo + "✗ Diferencia X" si no
      - Emite el array completo en cada mutación (actualizar línea, agregar, eliminar) — sin estado local propio aparte del recibido vía props, todo fluye por `v-model`

**Hallazgo real en Create.vue durante la verificación**: el toggle "Dividir en
categorías" usaba un `<span>` suelto junto al `<Checkbox>`, sin envolver
ambos en un `<label>`. Clickear el texto no togglea el checkbox (bug de UX
real, "funciona a veces" según cómo se hace click) — confirmado con
Playwright: el primer intento de activar split desde el texto no hacía
nada. Corregido envolviendo checkbox + texto en un `<label class="cursor-pointer">`,
asociación nativa de HTML sin necesitar `for`/`id`.

**Verificación Playwright (Sub-grupo A: 11/11, Sub-grupo B: 17/17, 0 errores
de consola en ambos)**: los 3 tipos muestran/ocultan los campos correctos
(incluyendo los labels dinámicos "Beneficiario" vs "Beneficiario (opcional)"),
cambiar de tipo resetea campos no aplicables, toggle de split
muestra/oculta SplitForm y la categoría principal correctamente, agregar/
eliminar líneas respeta el mínimo de 2, resumen de suma visible. Nota de
rigor: un primer intento de verificación de "la categoría principal se
oculta" dio falso negativo por un `label:has-text()` con match de substring
case-insensitive (el propio label "Dividir en categorías" contiene
"categoría" como substring) — corregido con `getByText(..., {exact: true})`,
no era un bug del componente.

Verificación completa de Create.vue (sin guardar, solo interacción de UI)
realizada contra los datos reales de María en `/transacciones/create` — sin
necesidad de rutas/páginas temporales, a diferencia del Sub-grupo A.
Submission real end-to-end se verifica formalmente en Grupo 12 (Pest) y
Grupo 13 (Playwright final), una vez exista Transacciones/Index.vue como
destino del redirect tras guardar.

`npm run build`: limpio. `php artisan test`: 156/156 (150 passed + 6 skipped, 0 failed).

## 10. NavLink

- [x] 10.1 Agregar "Transacciones" al navbar
      - **Orden final**: `Dashboard | Transacciones | Cuentas | Categorías | Beneficiarios | Presupuestos | Monedas | Tipos de cambio`
      - Transacciones va justo después de Dashboard — será la página de mayor uso una vez existe el feature
      - "Presupuestos" se reubica (no se omite) entre Beneficiarios y Monedas, agrupado con las entidades de configuración en vez de las de uso diario. Primer intento lo omitió por error (catch propio, señalado y corregido antes de que el usuario tuviera que notarlo él mismo)

**Verificación Playwright (8/8 PASS, 0 errores de consola):**

| Check | Viewport | Resultado |
|---|---|---|
| Orden del navbar correcto (8 links) | Desktop | PASS |
| Click navega a /transacciones | Desktop | PASS |
| Resaltado activo en index | Desktop | PASS |
| Resaltado activo persiste en /create (pattern `transacciones.*`) | Desktop | PASS |
| Links horizontales ocultos (<768px) | Mobile | PASS |
| Hamburguesa muestra "Transacciones" | Mobile | PASS |
| Orden del menú responsive correcto (8 links) | Mobile | PASS |
| Click en ResponsiveNavLink navega | Mobile | PASS |

`php artisan test`: 156/156 (150 passed + 6 skipped, 0 failed).

## 11. Factory y seeder

- [x] 11.1 TransaccionFactory con estados: outflow(), inflow(), transfer(), split()
- [x] 11.2 TransaccionSplitFactory
- [x] 11.3 **Las ~18 transacciones de María NO se agregaron a PresupuestoSeeder** — ver hallazgo crítico abajo. Creadas en un seeder nuevo `TransaccionSeeder.php`.

**Hallazgo crítico de orden de seeding (antes de tocar código, presentada la
lista completa de 18 transacciones al usuario para aprobación explícita
antes de implementar, como pidió)**: `DatabaseSeeder` corre `PresupuestoSeeder`
→ `TipoCambioSeeder` → (nuevo) `TransaccionSeeder`, en ese orden exacto —
`TipoCambioSeeder` depende de que María ya exista (creada por
`PresupuestoSeeder`), así que ese orden no se puede invertir. 2 de las 18
transacciones están en USDT y necesitan una tasa de cambio real
(`CalculadoraTransaccionService`) para calcular `monto_moneda_base_centavos`.
Meterlas directo en `PresupuestoSeeder` (como sugería tasks.md) habría
significado calcularlas ANTES de que `TipoCambioSeeder` sembrara las tasas.
Se creó `TransaccionSeeder.php` nuevo, que corre al final, sin tocar los
2 seeders ya archivados de Changes 3-6 — y de paso permite usar el service
real para TODAS las conversiones (no solo las 2 en USDT), en vez de
hardcodear montos a mano.

**2 adaptaciones a la escena "oficial" de `project.md`** (confirmadas con
el usuario antes de implementar): `project.md` describe un escenario
aspiracional de ANTES de Changes 5-6 con nombres de categoría/beneficiario
que no existen en la BD real (ej. "Materiales U", "Casero Don Luis", "Tigo").
Las 18 transacciones usan exclusivamente las categorías/beneficiarios/cuentas
REALES ya sembrados: (1) SIM Entel → categoría Suscripciones (resuelve la
pregunta abierta de design.md Decisión 11, truncada); (2) "Cliente freelance
A USD 100" se modeló como **USDT 100** (no existe cuenta en USD en el
escenario real) depositado en Binance USDT — ejercita la conversión
multi-moneda real del change.

**Composición final (18 transacciones, 2026-10-01 = hoy):**
- 2 inflows: Empresa X (sueldo) Bs 3500 hace 20d · Cliente freelance A USDT 100 hace 1d (convierte a Bs 710 a tasa 7.10)
- 4 outflows fijos: Alquiler Bs 1500 (hace 19d) · SIM Entel Bs 50 · Netflix Bs 45 · Spotify Bs 25 (hace 12-15d)
- 8 outflows variables: Corte de pelo, Regalo, Zapatos, Transporte×3, Salidas×2
- 1 outflow simple: Mercado (Comida básica) Bs 110
- 1 split: Hipermaxi Bs 320 = Comida básica 250 + Ropa 70 (hace 5d)
- 2 transfers: BNB→Efectivo Bs 300 (identity, misma moneda) · Binance→BNB USDT 20→Bs 142 (hace 4d, conversión real a tasa 7.10)
- Cobertura: 8/8 beneficiarios usados, 8/10 categorías usadas (Emergencia y Viajes del grupo Ahorros quedan sin transacciones a propósito — son categorías de asignación/ahorro, Change 8 las usará para "Assigned")

**Verificación exhaustiva con tinker (todo exacto, no solo "parece bien"):**
- 18 totales: 2 inflow + 14 outflow + 2 transfer ✓
- Split: padre `categoria_id=NULL`, 2 líneas (25000+7000=32000) ✓
- Transfer BOB→BOB: `tasa_cambio_aplicada=null` (identity), `monto_centavos_destino=monto_centavos` ✓
- Transfer USDT→BOB: `monto_centavos_destino=14200` (USDT 20 × 7.10 = Bs 142.00 exacto), `tasa_cambio_aplicada='7.10000000'` (coincide con la tasa real sembrada por `TipoCambioSeeder`) ✓
- Inflow USDT: `monto_moneda_base_centavos=71000` (USDT 100 × 7.10 = Bs 710.00 exacto) ✓
- **Aritmética de saldos verificada a mano para las 3 cuentas, coincide exacto**: BNB Checking 200000+350000-150000-5000-4500-2500-15000-18000-32000-30000+14200 = **307200** ✓; Efectivo 30000-4000-1500-2000-1800-9000-6000-11000+30000 = **24700** ✓; Binance USDT 5000+10000-2000 = **13000** ✓
- Fechas: la más antigua hace ~19.6 días, la más reciente hace ~3 horas — dentro de "los últimos 20 días" ✓

**Verificación visual Playwright (7/7 PASS, 0 errores de consola)**: `/transacciones` muestra las 18 reales con beneficiarios, split como "Dividida" y transfer visibles; `/cuentas` (vista de Change 4, sin tocar en este change) refleja los saldos actualizados exactos (Bs 3,072.00 / Bs 247.00 / USDT 130.00) — confirma integración correcta entre el accessor `saldoActualCentavos` de Change 4/Grupo 1 y las transacciones reales de este change.

`npm run build`: limpio. `php artisan test`: 156/156 (150 passed + 6 skipped, 0 failed).

## 12. Tests Pest (denso — 32 tests)

**Archivo:** `tests/Feature/TransaccionTest.php`, organizado en 10 `describe()`
(12A-12J) para legibilidad. Reutiliza `usuarioConPresupuestoActivo()` sin
redeclarar. Todo mensaje de error verificado con texto EXACTO vía
`assertSessionHasErrors`, nunca `assertInvalid` genérico — incluyendo los 2
mensajes de `withValidator()` (suma de splits, tasa de cambio no disponible).

- [x] 12.1 Store outflow simple válido
- [x] 12.2 Store inflow válido (sin categoria)
- [x] 12.3 Store transfer válido entre cuentas misma moneda
- [x] 12.4 Store transfer válido entre cuentas distinta moneda (calcula monto_centavos_destino)
- [x] 12.5 Store outflow split con 2 líneas suma correcta
- [x] 12.6 Store outflow split suma incorrecta → falla
- [x] 12.7 Store outflow split con 1 línea → falla (mínimo 2)
- [x] 12.8 Store transfer con cuenta_destino = cuenta_origen → falla
- [x] 12.9 Store outflow sin categoría (no split) → falla
- [x] 12.10 Store inflow con categoría → falla (prohibido)
- [x] 12.11 Store inflow sin beneficiario → falla
- [x] 12.12 Store transfer con categoría → falla
- [x] 12.13 Store transfer con beneficiario → falla
- [x] 12.14 Store con cuenta_id de otro usuario → validación falla (IDOR)
- [x] 12.15 Store con categoria_id de otro presupuesto → validación falla (IDOR)
- [x] 12.16 Store con beneficiario_id de otro presupuesto → validación falla (IDOR)
- [x] 12.17 Store con fecha_hora en futuro > 1 día → falla
- [x] 12.18 Store con monto 0 → falla
- [x] 12.19 Conversión implícita a moneda base: cuenta en USD, presupuesto base BOB, tasa 6.96, monto 100 USD → 696 BOB exacto
- [x] 12.20 Identity: cuenta en BOB, presupuesto base BOB → tasa_aplicada null, monto_moneda_base = monto
- [x] 12.21 Update permite cambiar monto, fecha, categoria, beneficiario, notas
- [x] 12.22 Update NO permite cambiar tipo
- [x] 12.23 Update NO permite cambiar es_split
- [x] 12.24 Update recalcula monto_moneda_base al cambiar fecha (2 tasas en fechas distintas, usa la vigente a la nueva fecha)
- [x] 12.25 Destroy soft delete transaccion y splits en cascada (hook)
- [x] 12.26 Policy: no puede editar transacción ajena → 403
- [x] 12.27 Policy: no puede eliminar transacción ajena → 403
- [x] 12.28 Index filtra por presupuesto activo (no muestra ajenas)
- [x] 12.29 Index con filtro tipo=outflow retorna solo outflows
- [x] 12.30 Index con filtro desde/hasta aplica correctamente
- [x] 12.31 Cuenta::saldoActualCentavos refleja transacciones correctamente
- [x] 12.32 forceDelete cuenta falla si tiene transacciones (restrictOnDelete)

**2 hallazgos reales corregidos en el código de producción MIENTRAS se
escribían los tests (no solo tests reforzados — bugs genuinos cerrados):**

1. **Gap de validación en transfers multi-moneda** (encontrado al diseñar
   el test 12.4, antes de escribirlo): `validarTasaDisponible()` en
   `StoreTransaccionRequest`/`UpdateTransaccionRequest` solo validaba la
   conversión cuenta→moneda base del presupuesto, nunca cuenta→cuentaDestino
   para transfers. Un transfer entre 2 monedas sin tasa registrada para ESE
   par específico habría pasado la validación y explotado con una
   `RuntimeException` sin capturar en el controller (500 feo) en vez de un
   error de validación limpio. Corregido agregando el segundo chequeo en
   ambos Requests.
2. **`declined` rechaza un `es_split` ausente, no solo valores truthy**
   (encontrado al correr los tests 12.2-12.4, que no mandaban `es_split` en
   el payload — igual que nunca lo haría un cliente que simplemente omite
   un campo opcional): a diferencia de `prohibited`, `declined` no considera
   "campo ausente" como válido — solo acepta los literales falsy explícitos.
   Ni siquiera `nullable` lo soluciona (`declined` no se salta con null,
   verificado empíricamente con `Validator::make()` antes de decidir el fix).
   La combinación correcta es `['sometimes', 'declined']`, confirmada con
   4 casos de prueba directos (ausente/null/false/true) antes de aplicarla.
   El frontend real (`Create.vue`) siempre envía `es_split` explícito vía
   `useForm`, así que este bug nunca se manifestó en la UI — pero sí
   afectaría cualquier otro cliente (Postman, una futura API) que omitiera
   el campo, que semánticamente debería ser válido para un campo opcional.

Ambos fixes se verificaron además contra el script de 23 casos del Grupo 3
(`verify-grupo3-requests.php`) y la suite completa, sin introducir
regresiones en ninguno de los dos.

**CHECKPOINT CRÍTICO**: `php artisan test` → **188/188** (182 passed + 6
skipped, 0 failed). Delta exacto: 156 → 188 (+32), tal como se esperaba.

## 13. Verificación manual Playwright

- [x] 13.1 migrate:fresh --seed → ~18 transacciones de María
- [x] 13.2 /transacciones → lista cronológica correcta (18 items, primero =
      Cliente freelance A/hace 1d, último = Empresa X/hace 20d)
- [x] 13.3 Filtros funcionan: tipo=inflow → 2 items; categoria=Transporte →
      3 items; desde=2026-09-25 → reduce la lista correctamente
- [x] 13.4 Crear outflow simple con calculadora: "50+30*2" → preview
      "= Bs 110.00", guardado correctamente
- [x] 13.5 Crear inflow con beneficiario → guardado correctamente
- [x] 13.6 Crear transfer BNB → Efectivo (misma moneda) → guardado
      correctamente
- [x] 13.7 Crear transfer Binance USDT → BNB BOB (multi-moneda) → verificado
      vía tinker: monto_centavos_destino=7100, tasa_cambio=7.10000000,
      10.00 USDT × 7.10 = Bs 71.00 exacto
- [x] 13.8 Crear outflow split con 3 líneas, categorías distintas (regla
      `distinct` del backend) → suma en tiempo real "Suma: Bs. 300.00 |
      Total: Bs. 300.00 ✓" visible antes de guardar
- [x] 13.9 Editar transacción, cambiar monto y categoría → verificado a
      nivel de BD (no solo visual): `categoria_id` cambió de 1→2,
      `monto_centavos` cambió a 99900, visible en la lista como "999.00"
- [x] 13.10 Eliminar transacción → desaparece de lista + saldo de cuenta
      actualizado: saldo de BNB Checking subió exactamente Bs 999.00
      (218400 → 318300 centavos), confirmado por tinker
- [x] 13.11 /cuentas muestra saldo actualizado tras la eliminación,
      verificado con locator escopado a la tarjeta de la cuenta (no un
      regex de página completa) → Bs 3,183.00, coincide con tinker
- [x] 13.12 Expresión inválida en calculadora ("50+") → "Expresión
      inválida" visible
- [x] 13.13 Validación: transfer misma cuenta (forzado vía DOM, la UI ya
      excluye la cuenta origen del select destino con `excluirId`) →
      "La cuenta destino debe ser distinta a la cuenta de origen."
- [x] 13.14 Validación: split suma != total → "La suma de las líneas...no
      coincide con el total" visible, con los montos exactos

**1 falso positivo diagnosticado y corregido en el PROCESO de verificación
(no en el código de producción — la app se comportó correctamente todo el
tiempo):**

El primer intento de 13.9/13.10/13.11 localizaba "la transacción a editar"
con `page.locator('ul > li').first()` (el primer item de la lista,
asumiendo que sería el split recién creado en 13.8). El check de saldo
post-eliminación falló: `antes=10275.9 despues=3183 diff=-7092.90` en vez
de la suba esperada de +999.00.

Diagnóstico (mismo patrón que toda la sesión: dudar primero del script de
verificación, no de la app, pero confirmar con BD antes de concluir nada):
`latest('fecha_hora')` no tiene un tiebreaker secundario (ej. `id`), y 13.7
y 13.8 se crean dentro del mismo segundo en los tests automatizados (algo
que no ocurre con usuarios reales tecleando a mano) → el orden entre ambas
filas con `fecha_hora` empatada no es determinístico. El `.first()` cayó
sobre la transferencia Binance→BNB de 13.7 (id=23), no sobre el split de
13.8 (id=24). Confirmado con `withTrashed()->find(23)`: tipo=transfer,
cuenta_id=3 (Binance), cuenta_destino_id=1 (BNB), monto_centavos=99900 tras
la edición, monto_centavos_destino=709290 (99900 × tasa 7.10 = exacto). Al
eliminar esa transferencia se retira su crédito del lado destino (BNB), por
lo que el saldo de BNB debía **bajar** Bs 7092.90 — y eso es exactamente lo
que el diff observado (-7092.90) reflejaba. La app recalculó la conversión
al editar el monto y descontó el crédito correcto al eliminar: cero bugs de
producción, 100% comportamiento esperado dada la fila que realmente se
tocó.

Fix aplicado **solo en el script de verificación**: en vez de `.first()`,
crear una transacción distintiva con un monto único (876.00) y localizarla
siempre por texto único (`hasText: '876.00'` / `'999.00'`), nunca por
posición en la lista. Re-verificado end-to-end: saldo de BNB Checking subió
de Bs 2,184.00 a Bs 3,183.00 (+999.00 exacto), confirmado tanto por tinker
como por un locator de Playwright escopado a la tarjeta de la cuenta
(`div.rounded-lg.border.border-border.bg-surface` con `hasText: 'BNB
Checking'`), no por un regex de página completa (ese approach también fue
abandonado por frágil, aunque en este caso no era la causa real del
mismatch).

**CHECKPOINT CRÍTICO**: `php artisan test` → **188/188** (182 passed + 6
skipped, 0 failed — los 6 skips son scaffolding de Jetstream no habilitado
en este proyecto: API tokens, password reset, 2FA, registro, no relacionados
con este change). `npm run build` limpio, sin `public/hot` residual.
`migrate:fresh --seed` ejecutado al final para dejar la BD de desarrollo en
el baseline de 18 transacciones.

## 14. Verificación de regresión

- [x] 14.1 php artisan test completo → **188/188** (182 passed + 6 skips
      preexistentes de Jetstream, 0 failed) — supera el mínimo esperado
      (150 + 30 = 180)
- [x] 14.2 Cuentas, Categorías (ruta real `/grupos-categorias`),
      Beneficiarios, Presupuestos, Monedas, Tipos de cambio siguen
      funcionando — verificado con Playwright, 0 errores de consola en
      ninguna de las 6 páginas
- [x] 14.3 TasaCambioService sigue funcionando (tests Change 3 en verde) →
      `--filter=TasaCambio` → 5/5 passed, 11 assertions
- [x] 14.4 Dashboard carga sin errores → verificado con Playwright, 0
      errores de consola

## 15. Cierre

- [ ] 15.1 Actualizar tasks.md marcando todo completado
- [ ] 15.2 Commit feat
- [ ] 15.3 openspec archive gestion-transacciones
- [ ] 15.4 Verificar openspec list (0 changes) y openspec list --specs (9 specs)
- [ ] 15.5 Commit chore + push