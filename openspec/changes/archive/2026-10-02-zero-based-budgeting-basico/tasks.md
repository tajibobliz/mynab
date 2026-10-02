## 1. Migraciones

- [x] 1.1 Migración `create_asignaciones_table`: id, presupuesto_id (FK
      cascade), categoria_id (FK restrict), año (unsignedSmallInteger), mes
      (unsignedTinyInteger), monto_centavos (bigInteger), timestamps,
      UNIQUE(presupuesto_id, categoria_id, año, mes), índice
      (presupuesto_id, año, mes). Verificado con tinker: FKs con
      ON DELETE CASCADE/RESTRICT correctos, UNIQUE rechaza duplicado, columna
      `año` (con ñ) funciona de forma transparente con el query builder de
      Eloquent — la composición `(año,mes) <= (Y,M)` se arma con
      `where()`/`orWhere()` puro, sin necesitar `whereRaw()` ni citado manual
      (confirmado antes de escribir el Service del Grupo 5).
- [x] 1.2 Migración `add_monto_moneda_base_centavos_to_transacciones_split`:
      columna bigInteger nullable temporalmente, backfill con bucle PHP +
      `CalculadoraTransaccionService` real (no SQL crudo ni valor
      hardcodeado), después `ALTER ... SET NOT NULL` (guardado con
      `DB::getDriverName() !== 'sqlite'`, mismo patrón que Change 3 — SQLite,
      el driver de los tests, no soporta ese ALTER). Verificado con tinker
      post-backfill: los 2 splits existentes (Hipermaxi: Comida básica y
      Ropa) quedaron con `monto_moneda_base_centavos` idéntico a
      `monto_centavos` (caso identidad BOB→BOB), como se esperaba.

**2 bugs reales detectados y corregidos al verificar este grupo (no
hipotéticos — los dos reventaron la migración/el seeder al correrlos):**

1. **El backfill no backfilleaba nada.** Primer intento: `$split->update([
   'monto_moneda_base_centavos' => $montoBase])` dentro de la migración. La
   migración completa (ambos `Schema::table` + backfill + ALTER, todo en una
   sola transacción de Postgres) falló con "la columna ... contiene valores
   null" al llegar al `ALTER ... SET NOT NULL`, y por ser una sola
   transacción, TODO se revirtió (confirmado con `migrate:status`: la
   migración volvió a "Pending"). Diagnóstico: `TransaccionSplit::$fillable`
   todavía NO incluye `monto_moneda_base_centavos` en este punto del change
   (eso es el Grupo 2, que corre DESPUÉS de este) — `update()` usa mass
   assignment internamente, así que la clave se descartó en silencio sin
   error visible, dejando la columna en null. Fix: `forceFill([...])->save()`
   en vez de `update()` (apropiado acá: es una migración de sistema, no
   input de usuario). Re-verificado con tinker tras el fix: ambos splits con
   identidad exacta.
2. **`migrate:fresh --seed` se rompía en `TransaccionSeeder`.** Consecuencia
   directa del nuevo `NOT NULL`: en un fresh install, esta migración corre
   sobre una tabla `transacciones_split` vacía (el backfill no tiene nada que
   hacer), y luego `TransaccionSeeder::run()` crea los 2 splits de Hipermaxi
   directamente vía `createMany()` sin pasar `monto_moneda_base_centavos` —
   mismo problema de `$fillable` que el bug anterior, esta vez del lado del
   seeder. Dado que esto rompía `migrate:fresh --seed` (comando usado
   constantemente en todo el proyecto) y es consecuencia directa de este
   mismo grupo, adelanté el fix de 1 línea del Grupo 2
   (`TransaccionSplit::$fillable` + cast `monto_moneda_base_centavos`) y
   corregí `TransaccionSeeder.php` para calcular cada línea del split con el
   service real (no copiar el valor del padre, no hardcodear). Documentado
   acá en vez de en el Grupo 2 porque la causa y el fix nacieron en este
   grupo; el Grupo 2 no repetirá este cambio.

**Verificación final del grupo:** `migrate:fresh --seed` limpio de punta a
punta, splits con `monto_moneda_base_centavos` poblado correctamente en
ambos escenarios (incremental sobre BD existente y fresh), `php artisan
test` → 188/188 (sin tests nuevos todavía, solo confirmando cero
regresiones).

## 2. Modelo Asignacion

- [x] 2.1 Creado `app/Models/Asignacion.php`: `$table='asignaciones'`
      (confirmado en Grupo 1), `$fillable` con los 5 campos, `$casts` (`año`,
      `mes`, `monto_centavos` a integer), `presupuesto()`, `categoria()`.
      Clase PHP sin ñ (`Asignacion`), columna de BD sí la tiene (`año`) —
      sin conflicto, son identificadores de capas distintas. Incluye
      `HasFactory` + `database/factories/AsignacionFactory.php` (no estaba
      en tasks.md explícitamente, agregado por consistencia: los 8 modelos
      previos del proyecto siguen ese patrón 100% de las veces).
- [x] 2.2 **Ya hecho en Grupo 1** (ver nota ahí): `TransaccionSplit::$fillable`
      + cast de `monto_moneda_base_centavos` — se adelantó por la
      dependencia circular con el backfill/seeder. No se repite acá.
- [x] 2.3 `app/Models/Presupuesto.php`: `asignaciones(): HasMany`.
- [x] 2.4 `app/Models/Categoria.php`: `asignaciones(): HasMany`.
- [x] 2.5 (no listado en tasks.md, ejecutado igual por ser la modificación
      cruzada aprobada en el plan) `app/Http/Controllers/TransaccionController.php`
      — `store()` y `update()`: cada línea de split calcula su propio
      `monto_moneda_base_centavos` con `CalculadoraTransaccionService`,
      reutilizando `$cuenta`/`$presupuesto`/`$fechaHora` ya resueltos en el
      scope del padre (cero queries extra por línea más allá de la
      resolución de tasa, que ya está cacheada/es la misma consulta que el
      padre si la tasa es la misma fecha).

**Verificación con tinker (modelo + relaciones):** creación de asignación,
`asignacion->presupuesto->nombre` y `->categoria->nombre` correctos,
`$personal->asignaciones()->count()` y `$alquiler->asignaciones()->count()`
ambos en 1, UNIQUE constraint rechaza el duplicado (presupuesto, categoria,
año, mes) con `QueryException`, cleanup confirmado (0 asignaciones
restantes).

**Verificación de la modificación cruzada (controller real, no simulado):**
invocación directa de `TransaccionController::store()`/`update()` vía el
patrón ya establecido `FormRequest::create()` + `setUserResolver()` +
`setContainer()` + `setRedirector()` + `validateResolved()` (+
`setRouteResolver()` con un `Route` real para `update()`, necesario para que
`route-model binding` del parámetro `$transaccion` funcione en el método):
- Split BOB→BOB (cuenta BNB, caso identidad): ambas líneas con
  `monto_moneda_base_centavos === monto_centavos` exacto.
- Split USDT→BOB (cuenta Binance, tasa real 7.10 de `TipoCambioSeeder`):
  línea de 600 centavos → 4260 (600×7.10 exacto), línea de 400 → 2840
  (400×7.10 exacto), `tasa_cambio_aplicada = 7.10000000`.
- `update()` con reemplazo completo de splits: los 2 splits nuevos
  (post-reemplazo) recalculan `monto_moneda_base_centavos` correctamente
  (caso identidad verificado).

Todas las filas de prueba limpiadas con `forceDelete()`. BD de desarrollo
confirmada en baseline limpio tras la verificación (18 transacciones, 2
splits, 0 asignaciones — sin residuos).

**Suite:** `php artisan test` → 188/188 (sin tests nuevos todavía).

## 3. Policy

- [x] 3.1 `app/Policies/AsignacionPolicy.php`: `viewAny`/`create` → true,
      `view`/`update`/`delete` → `$user->id === $asignacion->presupuesto->user_id`
      (mismo patrón de 1 salto que `BeneficiarioPolicy`), `restore`/
      `forceDelete` → false (sin soft delete en Asignacion).

**Verificación con tinker (matriz cruzada, 8 casos):**

| Check | Esperado | Resultado |
|---|---|---|
| `maria->can('view', $asig)` | true | ✅ |
| `maria->can('update', $asig)` | true | ✅ |
| `maria->can('delete', $asig)` | true | ✅ |
| `otro->can('view', $asig)` | false | ✅ |
| `otro->can('update', $asig)` | false | ✅ |
| `otro->can('delete', $asig)` | false | ✅ |
| `maria->can('viewAny', Asignacion::class)` | true | ✅ |
| `maria->can('create', Asignacion::class)` | true | ✅ |

**Auto-discovery:** `Gate::getPolicyFor(Asignacion::class)` →
`App\Policies\AsignacionPolicy` (convención de nombres de Laravel, sin
registro manual).

**Suite:** `php artisan test` → 188/188.

## 4. Request

- [x] 4.1 `app/Http/Requests/StoreAsignacionRequest.php` (upsert, no
      distingue store/update): `categoria_id` required + closure
      `whereHas('grupoCategoria', ...)` (mismo patrón consolidado de
      `StoreTransaccionRequest`, Change 7 — categorías no tienen
      `presupuesto_id` directo), `año` required int `between:2020,2100`,
      `mes` required int `between:1,12`, `monto_centavos` required int
      `min:0` (0 = sin asignar es válido), `prepareForValidation` inyecta
      `presupuesto_id` desde `presupuesto_activo_id`. Mensajes en español
      exactos en `messages()`.

**Verificación con `FormRequest::create()` + `setUserResolver()` (patrón
consolidado), 9 casos, mensaje exacto leído de `messages()` antes de
assertar (no hardcodeado por separado):**

| # | Caso | Resultado |
|---|---|---|
| 1 | Store válido | ✅ PASS |
| 2 | Sin `categoria_id` | ✅ "La categoría es requerida." |
| 3 | `categoria_id` de otro presupuesto (IDOR) | ✅ "La categoría no existe en este presupuesto." |
| 4 | `año` < 2020 | ✅ "El año debe estar entre 2020 y 2100." |
| 5 | `año` > 2100 | ✅ "El año debe estar entre 2020 y 2100." |
| 6 | `mes` = 0 | ✅ "El mes debe estar entre 1 y 12." |
| 7 | `mes` = 13 | ✅ "El mes debe estar entre 1 y 12." |
| 8 | `monto_centavos` negativo | ✅ "El monto no puede ser negativo." |
| 9 | `monto_centavos` = 0 | ✅ PASS (sin asignar es válido) |

**Nota de verificación (no es bug de app):** el primer intento de
verificar el caso 3 (IDOR) comparó contra la clave `categoria_id.integer`
del array `messages()` — equivocado, porque el mensaje de ese caso viene
del `$fail()` dentro de la closure `categoriaValidaRule()`, que nunca pasa
por el array `messages()` (los closures de validación no se registran ahí).
Corregido comparando contra el string literal de la closure; el request
siempre devolvió el mensaje correcto, el error estaba en mi propio script
de verificación.

**Suite:** `php artisan test` → 188/188.

## 5. Service

- [x] 5.1 `app/Services/PresupuestoMensualService.php` (sin dependencias
      inyectadas — todo lo que agrega ya viene en moneda base):
      `calcularReadyToAssign()`, `calcularActivity()` (del mes exacto),
      `calcularAvailable()` (acumulado, con rollover), `obtenerVistaMensual()`
      (optimizado por lotes, ver detalle abajo). Composición `(año,mes) <=
      (Y,M)` con query builder puro (`where()`/`orWhere()` anidados), cero
      `whereRaw` tocando la columna `año`. Todos los `->sum()` casteados a
      `(int)` antes de operar.
- [x] 5.2 `tests/Unit/Services/PresupuestoMensualServiceTest.php`: 8 tests
      (RTA positivo/cero/negativo, Activity con outflow directo, Activity
      con split verificando que lee `transacciones_split` y no el padre,
      Available con rollover de mes anterior, Available negativo/overspent,
      `obtenerVistaMensual` estructura completa + prueba explícita de
      "sin N+1" comparando cantidad de queries con 1 vs. 8 categorías).

**`obtenerVistaMensual`: 7 queries físicas, constante sin importar cuántas
categorías tenga el presupuesto (confirmado con `DB::listen` contra los
datos reales de María — 4 grupos, 10 categorías — y con el test que compara
1 vs. 8 categorías y exige el mismo conteo exacto).** Nota de precisión: el
plan original hablaba de "6 queries max"; el total real es 7 porque el
eager load de `categorias` dentro de `with()` es, por diseño de Laravel,
2 queries físicas (grupos + categorías), no 1 — sigue siendo plano/constante,
no proporcional a la cantidad de categorías, que es lo que realmente importa
para evitar N+1. Desglose: (1) grupos, (2) categorías eager, (3) asignado del
mes por categoría, (4) asignado acumulado por categoría, (5) outflows
directos del mes+acumulado en una sola query vía agregación condicional
sobre `fecha_hora`, (6) splits del mes+acumulado ídem vía JOIN, (7) inflows
acumulados del presupuesto para RTA. El total de asignaciones acumuladas del
presupuesto (para RTA) se deriva sumando en PHP el resultado ya obtenido por
categoría en la query 4, sin una query adicional (optimización extra sobre
lo pedido).

**2 bugs reales detectados y corregidos al escribir/correr este grupo:**

1. **`TransaccionSplitFactory` no seteaba `monto_moneda_base_centavos`**
   (el factory es de Change 7, la columna NOT NULL es de Change 8/Grupo 1):
   cualquier `TransaccionSplit::factory()->create()` sin ese campo
   explícito habría violado el constraint, en SQLite (los tests) igual que
   en Postgres. Corregido con el mismo patrón que `TransaccionFactory`
   (variable compartida `$montoCentavos` entre ambas columnas, caso
   identidad por defecto).
2. **Closure de `with(['categorias' => ...])` tipado como `Builder`**: Laravel
   pasa una instancia de la relación (`HasMany`), no un `Builder` plano, a
   los closures de constraint de eager load — error real de tipo (`TypeError`
   en tiempo de ejecución, no solo advertencia estática), detectado por el
   primer test que ejercitó `obtenerVistaMensual()`. Corregido quitando el
   type-hint, igual que el patrón ya usado en
   `GrupoCategoriaController::index()` (`fn ($query) => ...` sin tipar).

**1 falso N+1 diagnosticado en el propio test, no en el service:** la
primera versión de la prueba "sin N+1" comparaba `DB::getQueryLog()` antes y
después de agregar 7 categorías, usando `disableQueryLog()`/`enableQueryLog()`
entre medio — pero `disableQueryLog()` NO vacía el log acumulado, solo apaga
el flag de logging. Resultado: la segunda medición venía con las 7 queries
de la primera YA adentro, dando 14 en vez de 7 y pareciendo un N+1 real
(exactamente proporcional, 7 extra por 7 categorías extra — coincidencia
sospechosa que ameritaba diagnóstico antes de tocar el service). Corregido
agregando `DB::flushQueryLog()` explícito antes de la segunda medición;
confirmado que el service real no escala (7 en ambos casos).

**Suite:** `php artisan test` → **196/196** (188 + 8 nuevos, delta exacto
esperado). BD de desarrollo confirmada en baseline (18 transacciones, 2
splits, 0 asignaciones).

## 6. Controller

- [x] 6.1 `app/Http/Controllers/AsignacionController.php` (nuevo): `store()`
      hace `Asignacion::updateOrCreate()` por `(presupuesto_id, categoria_id,
      año, mes)`, flash success. Sin index/create/edit/update/destroy/show.
- [x] 6.2 `app/Http/Controllers/PresupuestoMensualController.php` (nuevo):
      `index()` con guard `presupuestoActivoOrRedirect()` (mismo patrón que
      Cuenta/GrupoCategoria/Beneficiario/TransaccionController), `año`/`mes`
      de query string (default `now()`), valida rango y redirige con flash
      si es inválido, usa `PresupuestoMensualService::obtenerVistaMensual()`.
- [x] 6.3 `app/Http/Controllers/DashboardController.php` — **se crea, no se
      modifica** (hallazgo de la fase de planificación: `/dashboard` era un
      closure inline desde Change 1, nunca existió este controller).
      `index()`: sin presupuesto activo → `tienePresupuestoActivo: false`;
      con activo → RTA del mes actual + top 5 "sobres atentos" (categorías
      con menor `available`, ordenadas ascendente — las negativas caen
      primero naturalmente con un `sortBy` simple, sin necesitar un
      criterio de desempate explícito).
- [x] 6.4 (no numerado en tasks.md, parte de la misma modificación cruzada)
      `routes/web.php`: reemplaza el closure de `/dashboard` por
      `[DashboardController::class, 'index']`, dentro del mismo grupo de
      middleware ya existente (sin agregar `->middleware()` inline — no es
      el patrón de este archivo, todas las rutas autenticadas ya viven
      dentro de un único `Route::middleware([...])->group()`). Documentado
      como modificación cruzada mínima a Change 1.

**1 bug real detectado y corregido (en el Request de Grupo 4, no en este
grupo, pero solo se manifestó al conectar el controller real):**
`StoreAsignacionRequest::rules()` nunca declaraba una regla para
`presupuesto_id`, aunque `prepareForValidation()` sí lo inyectaba vía
`merge()`. Laravel's `$request->validated()` **solo devuelve las claves que
tienen una regla definida en `rules()`**, sin importar qué haya sido
mergeado — así que `$validado['presupuesto_id']` llegaba indefinido al
controller, y `Asignacion::updateOrCreate()` insertaba con `presupuesto_id =
null`, violando el `NOT NULL`. La verificación del Grupo 4 no lo detectó
porque solo comprobaba pass/fail de la validación, nunca inspeccionaba el
contenido real de `validated()`. Corregido agregando
`'presupuesto_id' => ['required', 'exists:presupuestos,id']` a `rules()`
(mismo patrón que `StoreBeneficiarioRequest`, que había leído momentos antes
de escribir el Request original). Re-verificadas las 5 muestras clave del
Grupo 4 tras el fix: siguen comportándose igual.

**Verificación con controllers reales (no simulados):**
- `AsignacionController::store()` vía `FormRequest::create()` +
  `setUserResolver()`: 1ra llamada crea (count=1), 2da llamada con mismo
  `(p,c,año,mes)` y monto distinto actualiza sin duplicar (count sigue en
  1), 3ra llamada con `monto_centavos=0` acepta (sin asignar es válido).
- `PresupuestoMensualController::index()` con `?año=2026&mes=10`: Inertia
  component `PresupuestoMensual/Index`, `vista.readyToAssign=421000` (Bs
  4210, correcto — aún sin `AsignacionSeeder`, Grupo 11), `vista.grupos`
  con 4 elementos.
- `PresupuestoMensualController::index()` sin presupuesto activo: redirect a
  `/dashboard` confirmado.
- `DashboardController::index()` sin presupuesto activo:
  `tienePresupuestoActivo=false`.
- `DashboardController::index()` con María: `tienePresupuestoActivo=true`,
  `readyToAssign=421000`, `sobresAtentos` con 5 categorías ordenadas por
  `available` ascendente (Alquiler -150000 primero, la más negativa).

**Nota de verificación:** el caso "año inválido → redirect" de
`PresupuestoMensualController` no se pudo ejercitar end-to-end todavía
porque `route('presupuesto-mensual.index')` no existe hasta el Grupo 7 — se
confirmó que la rama de validación se alcanza correctamente (el intento de
resolver esa ruta falló exactamente como se esperaba dado que aún no
existe, no por un bug). Se re-verificará de punta a punta en el Grupo 7.

**CHECKPOINT CRÍTICO:** `php artisan test` → **196/196** (sin tests nuevos
en este grupo — Grupo 12 cubrirá controllers). BD de desarrollo confirmada
en baseline (18 transacciones, 2 splits, 0 asignaciones). Ruta `dashboard`
confirmada apuntando a `DashboardController@index` vía `route:list`.

## 7. Rutas

- [x] 7.1 `Route::resource('asignaciones', AsignacionController::class)
      ->only(['store'])->parameters(['asignaciones' => 'asignacion'])`.
      `->parameters()` es un no-op hoy (sin wildcard en la única ruta
      registrada), se deja por si Fase 2 agrega update/destroy.
- [x] 7.2 `Route::get('/presupuesto-mensual', [PresupuestoMensualController::class, 'index'])
      ->name('presupuesto-mensual.index')`.

**Verificación `route:list`:**
- `--name=asignaciones` → 1 ruta: `POST asignaciones` → `asignaciones.store`
- `--name=presupuesto-mensual` → 1 ruta: `GET|HEAD presupuesto-mensual` → `presupuesto-mensual.index`

**Verificación año/mes inválido (postergada del Grupo 6, ahora end-to-end
con la ruta real existente):**
| Caso | Resultado |
|---|---|
| `?año=1999&mes=10` | ✅ redirect a `/presupuesto-mensual`, flash "El mes solicitado no es válido." |
| `?año=2026&mes=13` | ✅ redirect a `/presupuesto-mensual` |
| sin query | ✅ default a mes actual (2026/10) |

**Verificación con curl real (servidor dev corriendo):**
- `POST /asignaciones` sin sesión → **419** (CSRF, el middleware `VerifyCsrfToken`
  del grupo `web` corre antes que `auth:sanctum`)
- `GET /presupuesto-mensual` sin sesión → **302** → `/login`

**Suite:** `php artisan test` → 196/196. BD de desarrollo en baseline (18
transacciones, 2 splits, 0 asignaciones).

## 8. HandleInertiaRequests

- [x] 8.1 Sin cambios, datos resueltos en controllers (Decisión design.md 8).

## 9. Vistas (dividido en 9A/9B para aprobación granular)

### 9A — Vista mensual + componentes

- [x] 9.1 `resources/js/Pages/PresupuestoMensual/Index.vue`: `SelectorMes`
      arriba, hero de Ready to Assign (3 estados de color con tokens
      Tailwind literales), tabla de categorías agrupadas reutilizando
      `GrupoCategoriaCard` (mismo componente de `GruposCategorias/Index.vue`,
      vía su slot `#categorias` con un grid de 4 columnas Nombre/Assigned/
      Activity/Available), Activity navega a `/transacciones?categoria=X&
      desde=...&hasta=...` vía `route()` con parámetros objeto (no
      concatenación manual de query string).
- [x] 9.2 `resources/js/Components/AsignacionInlineInput.vue`: celda → input
      autofocus+select → blur/Enter guarda vía `router.post`, Escape cancela
      sin guardar, loading state (`guardando`) deshabilita el input durante
      el POST con guard anti-doble-submit. **Desviación del prop
      `monedaCodigo` del recordatorio:** se usa `moneda: Object` (no un
      string de código) porque `formatearMonto()`/`centavosDesdeMonto()`
      necesitan `{simbolo, decimales}`, no solo el código — un string solo
      no alcanza para ninguna de las dos funciones.
- [x] 9.3 `resources/js/Components/SelectorMes.vue`: Anterior/Siguiente +
      "Octubre 2026", maneja rollover de año (diciembre→enero y
      enero→diciembre), navega con `router.get('/presupuesto-mensual', {año, mes})`.

**Verificación Playwright (7/7), datos del seeder sin `AsignacionSeeder`
todavía (Grupo 11), 0 errores de consola:**
| Check | Resultado |
|---|---|
| Vista carga, mes actual (Octubre 2026) | ✅ |
| Ready to Assign = Bs 4,210.00 | ✅ |
| Navegación mes anterior (Septiembre 2026) | ✅ |
| Navegación mes siguiente x2 (Noviembre 2026) | ✅ |
| Available de Alquiler en rojo (overspent, Assigned=0) | ✅ |
| Click en Assigned → input con autofocus | ✅ |
| Edición inline guarda y UI refleja Bs 1,500.00 | ✅ |

`npm run build` limpio. `php artisan test` → 196/196. BD de desarrollo
limpiada tras la verificación (0 asignaciones, baseline intacto).

### 9B — Dashboard

- [x] 9.4 `resources/js/Pages/Dashboard.vue`: reemplaza el stub (que todavía
      decía literalmente "Aquí vivirá el dashboard del presupuesto activo").
      Branching ahora por `tienePresupuestoActivo` (prop del controller), no
      por `page.props.auth.user.presupuesto_activo` (el viejo criterio del
      stub) — más confiable al venir decidido server-side. Hero RTA con los
      mismos 3 tokens de color y mensajes que `PresupuestoMensual/Index.vue`
      + sub-texto de mes/año (reutilizando `nombreMes` ya calculado por el
      service, ver fix de `DashboardController` abajo). Grid de 5 "sobres
      atentos" con borde izquierdo del color del grupo padre (mismo patrón
      visual que `GrupoCategoriaCard`, color arbitrario vía `:style` inline,
      no un token Tailwind — no aplica la regla de tokens literales porque
      no es un valor fijo conocido). CTAs a `/presupuesto-mensual` y
      `/transacciones/create`. Rama "sin presupuesto activo" preservada
      intacta del stub original.

**2 fixes necesarios en `DashboardController.php` (Grupo 6), encontrados al
diseñar la UI de 9B — no HAY forma de que el frontend cumpla el pedido sin
ellos:**
1. `flatMap(fn ($grupo) => $grupo['categorias'])` descartaba el color del
   grupo padre al aplanar — la UI pide "color del grupo padre" en cada card,
   dato que simplemente no llegaba. Corregido inyectando `grupoColor` en
   cada categoría antes de aplanar.
2. `'mesAño' => ['año' => $año, 'mes' => $mes]` se reconstruía a mano,
   descartando el `nombreMes` que `obtenerVistaMensual()` ya calcula con
   Carbon. Corregido reutilizando `$vista['mesAño']` directo — evita además
   duplicar un array de 12 nombres de mes en el frontend solo para este
   sub-texto (ya existe una copia en `SelectorMes.vue`, no hacía falta una
   tercera).

**Verificación Playwright (8/8), 0 errores de consola:**
| Check | Resultado |
|---|---|
| RTA = Bs 4,210.00 | ✅ |
| Sub-texto "octubre 2026" | ✅ |
| Grid de 5 sobres atentos | ✅ |
| CTA "Nueva transacción" visible | ✅ |
| CTA "Ver presupuesto completo" visible | ✅ |
| Click en sobre atento → `/presupuesto-mensual` | ✅ |
| Click en "Nueva transacción" → `/transacciones/create` | ✅ |
| Sin presupuesto activo → hero onboarding + CTA | ✅ |

Nota: `/register` está deshabilitado en este proyecto (Fortify/Jetstream —
confirmado por `RegistrationTest` skippeado en toda la suite), así que el
usuario sin presupuesto para el último check se creó directo por tinker, no
vía el formulario de registro.

`npm run build` limpio. `php artisan test` → 196/196. BD de desarrollo
limpiada tras la verificación (usuario QA y asignaciones de prueba
eliminados, baseline intacto: 18 transacciones, 2 splits, 0 asignaciones,
2 usuarios).

## 10. NavLink

- [x] 10.1 `resources/js/Layouts/AppLayout.vue`: "Presupuesto" agregado entre
      Dashboard y Transacciones (desktop + responsive), `href` a
      `presupuesto-mensual.index`, `:active` con patrón `presupuesto-mensual.*`.
      Orden final: Dashboard | Presupuesto | Transacciones | Cuentas |
      Categorías | Beneficiarios | Presupuestos | Monedas | Tipos de cambio.

## 11. Seeder

- [x] 11.1 `database/seeders/AsignacionSeeder.php` (nuevo, corre tras
      `TransaccionSeeder` en `DatabaseSeeder.php`): 10 asignaciones de María
      para octubre 2026, total Bs 3610 exacto.

**Hallazgo real detectado al verificar (no un bug de cálculo — justo lo que
se pidió "pescar"): `TransaccionSeeder.php` (Change 7) ancla sus fechas a
`now()->subDays($diasAtras)` con offsets de hasta 20 días. Verificado con
`now()` real = 2026-10-02: 17 de las 18 transacciones caían en SEPTIEMBRE,
no octubre — el escenario llevaba "roto" (fuera de su mes previsto) desde
que se escribió, no por el paso del tiempo durante esta sesión. RTA y
Available seguían siendo exactos porque son acumulados (agnósticos al mes
exacto), pero Activity (estrictamente del mes consultado) mostraba ~0 para
casi todas las categorías, contradiciendo las cifras esperadas del
escenario.** Presentado al usuario con las fechas reales impresas; aprobó
anclar a una fecha fija. **Corrección propia durante la implementación:** la
primera propuesta (anclar a `2026-10-01`) no resolvía nada — restar días a
un ancla que es el PRIMER día de octubre solo puede retroceder hacia
septiembre, el problema habría quedado igual de roto, solo que fijo en vez
de a la deriva. Corregido con ancla `2026-10-21` (da margen para que los 20
días de offset cubran octubre 1-21 completo). Modificación cruzada mínima a
Change 7, documentada en el commit de Change 8.

**Verificación exhaustiva con tinker, con reconciliación independiente
(sumas crudas de transacciones vs. salida del service, no solo "coincide"):**
- RTA = 60000 centavos (Bs 600.00) — **exacto**.
- Suma de outflows directos + splits = 262300 centavos, coincide exacto con
  la suma de Activity de las 10 categorías calculada por el service.
- Suma de `asignaciones.monto_centavos` = 361000 (Bs 3610) — coincide exacto
  con design.md Decisión 9.
- Alquiler: Assigned 150000 / Activity 150000 / Available 0 — match exacto
  con la tabla de verificación del usuario (el caso más simple, sin split).
- Emergencia/Viajes: Activity 0, Available = Assigned (ahorro puro, sin
  transacciones) — match exacto.
- Nota de transparencia: algunas cifras individuales de la tabla de
  verificación del usuario (Transporte 120→real 53, Comida básica
  320→real 360, Suscripciones 115→real 120) no coincidieron con lo
  calculado — verificadas a mano contra los montos reales del seeder
  (Transporte: 1500+2000+1800=5300; Comida básica: outflow directo 11000 +
  parte del split 25000=36000; Suscripciones: Entel 5000+Netflix 4500+
  Spotify 2500=12000) y confirmadas como pequeños errores aritméticos en la
  tabla de expectativas, no bugs del service — el total agregado (262300)
  reconcilia exacto de cualquier forma.

**Verificación Playwright (8/8 con los Grupos 10+11 combinados), 0 errores
de consola:**
| Check | Resultado |
|---|---|
| NavLink desktop: orden completo correcto | ✅ |
| Click "Presupuesto" → `/presupuesto-mensual` | ✅ |
| NavLink "Presupuesto" visible en menú responsive | ✅ |
| Dashboard RTA = Bs 600.00 | ✅ |
| `/presupuesto-mensual` RTA = Bs 600.00 | ✅ |
| Tabla muestra 4 grupos | ✅ |
| Alquiler: 1,500.00 / 1,500.00 / 0.00 | ✅ |
| Emergencia: Available 300.00 (ahorro) | ✅ |

`npm run build` limpio. `php artisan test` → 196/196. Nuevo baseline de BD
de desarrollo: 18 transacciones, 2 splits, **10 asignaciones**.

## 12. Tests Pest (25 tests: 17 nuevos + 8 del Grupo 5)

- [x] 12.1-12.5 `tests/Unit/Services/PresupuestoMensualServiceTest.php`: ya
      cubiertos por los 8 tests del Grupo 5 (RTA positivo/cero/negativo,
      Activity directo/split, Available rollover/overspent,
      `obtenerVistaMensual`).
- [x] 12.6-12.10 `tests/Feature/AsignacionTest.php` (nuevo, helper
      `usuarioConAsignacion()`): store crea, store repetido actualiza sin
      duplicar, monto=0 válido, IDOR (categoría de otro presupuesto del
      mismo usuario, y de otro usuario por completo — 2 variantes), monto
      negativo con mensaje exacto, año/mes fuera de rango con mensaje
      exacto (4 sub-casos), policy directa (ver nota abajo). **9 tests.**
- [x] 12.11-12.13 `tests/Feature/PresupuestoMensualControllerTest.php`
      (nuevo, helper `usuarioConVistaMensual()`): vista con datos
      (Inertia props exactos vía `AssertableInertia`), vista sin
      asignaciones del mes (Assigned=0 por fila ausente, no por valor
      explícito), sin presupuesto activo → redirect, sin query string →
      mes actual por defecto, año/mes inválido → redirect con flash. **5 tests.**
- [x] 12.14-12.17 Rollover: 3 tests nuevos agregados a
      `PresupuestoMensualServiceTest.php` (acumulación a través de 3 meses
      consecutivos, mes intermedio sin fila de asignación — ausente, no
      cero explícito —, RTA acumulando asignaciones de múltiples meses) +
      el test de rollover ya existente del Grupo 5. **4 tests en total
      para este punto.**
- [x] 12.18-12.20 IDOR + policy: cubiertos dentro de `AsignacionTest.php`
      (las 2 variantes de IDOR en `categoria_id` + el test de policy
      directa) y `PresupuestoMensualControllerTest.php` (sin presupuesto
      activo → redirect).

**Nota sobre 12.10 (policy):** no existe ruta HTTP de `update`/`destroy`
para `Asignacion` (solo `store`/upsert), y por construcción el upsert nunca
puede alcanzar la asignación de otro usuario (`presupuesto_id` siempre sale
del presupuesto activo del propio request, nunca del payload). Se verifica
la `Policy` directamente vía `$user->can(...)`, mismo patrón ya usado para
`TransaccionSplitPolicy` en Change 7 (definida y correcta aunque ninguna
ruta la ejercite todavía).

**Nota sobre CSRF (descartado deliberadamente, no un gap):** se consideró un
test Pest de "POST /asignaciones sin sesión → 419", pero Laravel desactiva
la verificación CSRF por defecto en el entorno de test (confirmado
empíricamente: un POST sin token ahí da 302, nunca 419) — un test así no
reproduciría el comportamiento real y daría una falsa sensación de
cobertura. Ya verificado con curl real contra el servidor dev en el Grupo 7
(419 confirmado).

**Patrones aplicados sin excepción:** mensajes exactos leídos de
`messages()` del Request antes de assertar (no hardcodeados por separado),
helpers con nombre único por archivo (`usuarioConAsignacion`,
`usuarioConVistaMensual` — ninguno colisiona con `usuarioConPresupuestoActivo`
de `CuentaTest.php` ni `fixturesTransaccion` de `TransaccionTest.php`).

**CHECKPOINT:** `php artisan test` → **213/213** (207 passed + 6 skips
preexistentes de Jetstream, 0 failed). Delta exacto: 196 → 213 (+17 tests
nuevos). El estimado verbal previo fue "~216" — 213 es el número real
alcanzado cubriendo los 20 puntos de 12.1-12.20 con profundidad (varios
puntos tienen más de 1 test), sin inflar el conteo con tests redundantes
solo para acercarse a la cifra aproximada.

## 13. Verificación manual

- [x] 13.1 `migrate:fresh --seed` → 10 asignaciones de octubre confirmadas.
- [x] 13.2 Dashboard: Ready to Assign Bs 600.00, 8 sobres atentos visibles.
- [x] 13.3 `/presupuesto-mensual`: Octubre 2026, 4 grupos, tabla completa.
- [x] 13.4 Navegación mes anterior (Septiembre) / siguiente (Octubre) OK.
- [x] 13.5 Edit inline Assigned (Transporte): click → input → 350 → blur →
      persiste en UI (`Bs. 350.00`). Revertido a 300 tras la verificación.
- [x] 13.6 Available en rojo si overspent: outflow ad-hoc de Bs 100 en
      Suscripciones (assigned 150, activity previo 120) → Available pasa a
      **Bs -70.00**, confirmado con `text-status-danger` en la clase real
      del elemento (no solo el texto) y con ground-truth de
      `calcularAvailable()` vía tinker ANTES de mirar la UI (-7000
      centavos, coincide exacto). Outflow ad-hoc eliminado tras la prueba.
- [x] 13.7 Click en Activity (Alquiler) navega a
      `/transacciones?categoria=1&desde=2026-10-01&hasta=2026-10-31`.

BD de desarrollo confirmada en baseline tras la verificación (18
transacciones, 2 splits, 10 asignaciones, Transporte de vuelta en 300).

## 14. Regresión

- [x] 14.1 `php artisan test` → **213/213** (207 passed + 6 skips
      preexistentes de Jetstream, 0 failed).
- [x] 14.2 Cuentas, Categorías, Beneficiarios, Transacciones, Dashboard,
      Presupuesto mensual — 6/6 vía Playwright, 0 errores de consola.
- [x] 14.3 Saldos de cuentas sin alterar por el backfill (que solo tocó
      `transacciones_split.monto_moneda_base_centavos`, nunca
      `monto_centavos`): BNB Checking Bs 3,072.00, Efectivo Bs 247.00,
      Binance USDT ₮ 130.00 — coinciden exacto con los valores finales
      verificados al cierre de Change 7.

## 15. Cierre

- [x] 15.1 tasks.md completo
- [x] 15.2 Commit feat + push
- [x] 15.3 openspec archive zero-based-budgeting-basico
- [x] 15.4 Verificar openspec list (0 changes) y --specs (10 specs)
- [x] 15.5 Commit chore + push