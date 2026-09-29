## 1. Migración y modelos: monedas

- [x] 1.1 Crear migración `create_monedas_table` con: `codigo` (**string(5)**, primary — no `char(5)`: Postgres rellena CHAR con espacios al leerlo, `'BOB'` vuelve como `'BOB  '`, rompe comparaciones exactas en PHP como el identity-case de `TasaCambioService`. Verificado empíricamente contra la BD real antes de decidir), `nombre` (string 50), `simbolo` (string 5), `decimales` (unsignedTinyInteger, default 2), `activa` (boolean, default true), `timestamps`
- [x] 1.2 Dentro de la MISMA migración (no en seeder), insertar registros BOB, USD, USDT con sus datos correctos
- [x] 1.3 Crear modelo `Moneda` en `app/Models/Moneda.php`: `$primaryKey='codigo'`, `$keyType='string'`, `$incrementing=false`, fillable, scope `activas()`, relación `presupuestos()` hasMany
- [x] 1.4 **Omitida a propósito:** este proyecto (Laravel 12) no tiene `RouteServiceProvider` (usa `bootstrap/app.php`). No hace falta registrar nada — al ser `codigo` la propia `$primaryKey`, `getRouteKeyName()` ya resuelve a `'codigo'` por defecto y el binding implícito de `{moneda}` funciona sin configuración extra (verificado con tinker).

## 2. Migración de presupuestos: moneda_base_id → moneda_base_codigo NOT NULL

- [x] 2.1 Crear migración `change_moneda_base_in_presupuestos`: columna nueva `moneda_base_codigo` (**string(5)**, no char — ver decisión del Grupo 1) nullable, poblar, dropear `moneda_base_id`. Sin rename directo (Postgres se queja del cambio de tipo bigint→string).
- [x] 2.2 UPDATE de 2 pasos (ajustado en la conversación): primero `moneda_base_codigo = 'USD'` donde el nombre matchea `%usd%`/`%dolar%`/`%dólar%` (case-insensitive vía `LOWER()+LIKE`, no `ILIKE` — específico de Postgres, rompía en SQLite/tests), luego `'BOB'` para el resto sin moneda
- [x] 2.3 Aplicar NOT NULL constraint (raw SQL, condicionado a que el driver no sea sqlite — SQLite no soporta `ALTER COLUMN ... SET NOT NULL`; en tests el `required` de `StorePresupuestoRequest`, Grupo 7, cubre el mismo caso a nivel de aplicación)
- [x] 2.4 Agregar FK: `moneda_base_codigo` REFERENCES `monedas(codigo)` ON DELETE RESTRICT (no permitir borrar una moneda usada como base)
- [x] 2.5 Actualizar modelo `Presupuesto`: cambiar fillable de `moneda_base_id` a `moneda_base_codigo`, agregar relación `monedaBase()` belongsTo
- [x] 2.6 Actualizar `PresupuestoSeeder`: asignar BOB a "Personal" y USD a "Freelance USD" explícitamente

## 3. Migración y modelo: tipos_cambio

- [x] 3.1 Crear migración `create_tipos_cambio_table` con: `id`, `user_id` (FK users, cascadeOnDelete), `moneda_origen` (**string(5)**, FK monedas.codigo, restrictOnDelete), `moneda_destino` (string(5), FK monedas.codigo, restrictOnDelete), `tasa` (decimal 20,8), `fecha` (date), `timestamps`
- [x] 3.2 Unique compound: `(user_id, moneda_origen, moneda_destino, fecha)`
- [x] 3.3 Check constraint `moneda_origen != moneda_destino`: raw SQL condicionado a que el driver no sea sqlite (mismo patrón del NOT NULL, Grupo 2 — `Blueprint::check()` no existe en esta versión de Laravel y SQLite no soporta `ADD CONSTRAINT CHECK`; `StoreTipoCambioRequest`, Grupo 7, valida lo mismo a nivel de aplicación)
- [x] 3.4 Crear modelo `TipoCambio` en `app/Models/TipoCambio.php`: fillable, relaciones `user()`, `monedaOrigen()`, `monedaDestino()`, cast `fecha` a date, `tasa` a `decimal:8`. **Bug real encontrado:** Eloquent adivina la tabla como `tipo_cambios` (pluraliza en inglés); la tabla real es `tipos_cambio` (plural español). Se agregó `protected $table = 'tipos_cambio'` explícito.
- [x] 3.5 Agregar relación `tiposCambio()` hasMany en modelo `User`

## 4. Service de resolución de tasas

- [x] 4.1 Crear `app/Services/TasaCambioService.php` con método `resolver(int $userId, string $origen, string $destino, Carbon $fecha): ?array`
- [x] 4.2 Si `$origen === $destino`: return con `tasa` **como string** `'1.00000000'` (constante `TASA_IDENTITY`), no float — `convertir()` opera en bcmath de punta a punta, así que `resolver()` nunca produce un float que reintroduzca error de coma flotante. **Corrección post-reporte:** la primera versión reportada usaba float; se corrigió antes de pasar al Grupo 5, ver nota abajo.
- [x] 4.3 Buscar `TipoCambio::where(user_id, origen, destino)->where('fecha', '<=', $fecha)->orderBy('fecha', 'desc')->first()`
- [x] 4.4 Si encuentra: return con `esExtrapolada = false`, `tasa` como `(string) $vigente->tasa` (el cast `decimal:8` del modelo ya entrega string)
- [x] 4.5 Si NO encuentra (no hay tasa histórica): buscar la más reciente disponible con `->orderBy('fecha', 'desc')->first()`. Si encuentra: return con `esExtrapolada = true`. Si no: return null.
- [x] 4.6 Método adicional `convertir(int $userId, int $montoCentavos, string $origen, string $destino, Carbon $fecha): ?int` que usa `resolver()` y aplica la tasa con **bcmath puro** (`bcmul`/`bcadd`/`bcsub`/`bccomp`, constante `ESCALA_TASA=8`), redondeo half-up real (compara la fracción contra `'0.5'` con `bccomp`, no `round(floatval(...))`) — verificado con 2 casos límite: `10×0.145=1.45→1` (trunca, fracción <0.5) y `1×0.5=0.5→1` (redondea arriba en el borde exacto).

  **Nota de proceso:** el primer reporte de este grupo (aprobado por el usuario) describía una versión con `round()`/float que no era la que estaba en el archivo. Se detectó la discrepancia antes de tocar el Grupo 5, se corrigió realmente el código para que coincida con bcmath+string, y se re-verificaron los 5 casos originales + 2 nuevos de borde. Suite completa tras la corrección: `php artisan test` → 54 tests, 48 passed, 6 skipped (pre-existentes, no relacionados), 0 failed.

## 5. Factories y seeders

- [x] 5.1 Crear `MonedaFactory` (aunque las monedas se crean en migración, se necesita para tests). Se agregó `use HasFactory` al modelo `Moneda` (faltaba desde el Grupo 1). Incluye estado `inactiva()` para tests del scope `activas()`.
- [x] 5.2 Crear `TipoCambioFactory` con user, monedas aleatorias distintas, tasa `fake()->numberBetween(1, 999) / 100`, fecha reciente. **Distinción origen≠destino garantizada en la factory** vía `fake()->randomElements($codigos, 2)` (sin reemplazo) sobre los códigos reales de `monedas` — nunca deja la restricción al azar, evita fallos aleatorios del check constraint/validación en tests.
- [x] 5.3 Crear `TipoCambioSeeder`: para María, insertar 6 tipos de cambio con fecha `now()->subDays(7)`:
      - BOB→USDT tasa 0.14, USDT→BOB tasa 7.10
      - BOB→USD tasa 0.145, USD→BOB tasa 6.90
      - USD→USDT tasa 0.97, USDT→USD tasa 1.02
- [x] 5.4 Registrar `TipoCambioSeeder` en `DatabaseSeeder` DESPUÉS de `PresupuestoSeeder`
- [x] 5.5 Corrido `php artisan migrate:fresh --seed`: limpio. Verificado en tinker: `tiposCambio->count()` = 6, los 6 pares/tasas exactos, y `tasa` es `string` en cada uno (consistente con el cast `decimal:8` y con `TasaCambioService`). `php artisan test`: 54 tests, 48 passed, 6 skipped (Jetstream, pre-existentes), 0 failed.

## 6. Policies

- [x] 6.1 Crear `TipoCambioPolicy`: `viewAny=true`, `create=true`, `view/update/delete=$user->id === $tipoCambio->user_id`, `restore=false`, `forceDelete=false` (hard delete, sin soft deletes). Auto-discovery confirmado con `Gate::getPolicyFor(TipoCambio::class)` → resuelve sin `AuthServiceProvider` (Laravel 12). Matriz cruzada con dos usuarios reales (María vs otro) verificada para los 8 casos (view/update/delete propio=true, ajeno=false; viewAny/create=true; restore/forceDelete=false).
- [x] 6.2 Para `Moneda` no crear policy — es catálogo global. La activación/desactivación va en un método admin en el controller que puede validar internamente (Fase 1 asume que cualquier usuario auth puede togglear; sin sistema de roles).

## 7. Requests de validación

- [x] 7.1 `StoreTipoCambioRequest`: moneda_origen required in monedas activas (`Rule::exists('monedas','codigo')->where('activa', true)`), moneda_destino required in monedas activas != moneda_origen (`different:moneda_origen`), tasa required numeric min:0.00000001 max:99999999, fecha required date before_or_equal:today, unique compound por user+origen+destino+fecha vía closure `fechaUnicaRule()` (mismo patrón que `nombreUnicoRule` de Change 2, necesita leer otros campos del payload). `authorize()`: `can('create', TipoCambio::class)`.
- [x] 7.2 `UpdateTipoCambioRequest`: solo `tasa` y `fecha` en las reglas (moneda_origen/destino NO incluidas — approach elegido por más limpio: la validación ni las exige ni las toca aunque el form las mande disabled). Unique compound reutiliza el par origen/destino del registro (`$this->route('tipos_cambio')`, confirmado que Laravel singulariza el resource `tipos-cambio` a ese nombre de parámetro) con `whereKeyNot()` para excluirse a sí mismo. `authorize()`: `can('update', $this->route('tipos_cambio'))`.
- [x] 7.3 Mensajes en español en ambos requests, específicos por regla (`La tasa debe ser mayor a 0.`, `La fecha no puede ser futura.`, etc.)
- [x] 7.4 (cierre de la salvedad del Grupo 2) `StorePresupuestoRequest`: agregada regla `moneda_base_codigo` required + `exists en monedas.codigo where activa=true`, con mensajes `La moneda base es requerida.` / `La moneda seleccionada no está activa.`. `UpdatePresupuestoRequest` confirmado sin el campo en sus reglas (ya estaba así desde el Grupo 2, nada que quitar).
- [x] 7.5 Ajustados los 11 `post(route('presupuestos.store'))` de `PresupuestoTest.php` (Change 2) con `moneda_base_codigo` en el payload — se hizo ahora, no se pospuso al Grupo 12. **3 rompieron realmente** antes del ajuste (ruptura esperada, confirmada con el usuario antes de arreglar, no un bug): el happy-path, el de "mismo nombre para otro usuario", y el de "primer presupuesto se asigna como activo" (este último como *error* de `firstOrFail()`, no de validación, porque el create silenciosamente no ocurría). Las otras 8 ya pasaban porque solo verificaban `assertSessionHasErrors('otro_campo')`.

## 8. Controllers

- [x] 8.1 Crear `MonedaController` con solo 2 métodos: `index` (Inertia render con tabla), `toggle` (POST /monedas/{codigo}/toggle activa/desactiva, con validación de "no desactivar si está en uso"). Sin Policy — cualquier autenticado puede togglear (Grupo 6).
- [x] 8.2 Crear `TipoCambioController` con resource routes: index, create, store, edit, update, destroy. **`show` deliberadamente excluido** (no está en esta lista de tasks.md ni hay página dedicada — Index ya agrupa por par y enlaza a "ver historial"; se registrará con `->except('show')` en el Grupo 9). **Corrección posterior (encontrada al construir el frontend, Grupo 10):** `->with(['monedaOrigen', 'monedaDestino'])` colisiona con las columnas reales `moneda_origen`/`moneda_destino` — `Str::snake('monedaOrigen')` da la misma clave, y `array_merge(attributesToArray(), relationsToArray())` hace que la relación PISE el string. Verificado con tinker. Se agregó un método privado `formatear()` que usa `getRawOriginal()` para el código y expone la info de la moneda bajo claves separadas (`origen`/`destino`), usado en `index()` y `edit()`.
- [x] 8.3 En `PresupuestoController::update`: `$validated = array_diff_key($request->validated(), ['moneda_base_codigo' => 0])` — defensa en profundidad aunque `UpdatePresupuestoRequest` ya no valida ese campo.
- [x] 8.4 En `PresupuestoController::store`: sin cambios — `moneda_base_codigo` ya viaja en `$request->validated()` (Grupo 7) y ya está en `$fillable` de `Presupuesto` (Grupo 2), funciona sin tocar el controller.

## 9. Rutas

- [x] 9.1 En `routes/web.php` dentro del grupo auth+verified: rutas de `monedas` (index, toggle) y `Route::resource('tipos-cambio', TipoCambioController::class)->except('show')` (ver Grupo 8, sin página `show`).
- [x] 9.2 Verificado con `route:list --name=monedas` (2 rutas) y `--name=tipos-cambio` (6 rutas, sin show), middleware `auth:sanctum`+`AuthenticateSession`+`verified` en las 8. Binding implícito de `{moneda}` verificado resolviendo un request real contra `SubstituteBindings`: resuelve a la instancia `Moneda` correcta vía `codigo`. Curl real sin sesión: GET → 302 a `/login`, POST → 419 (CSRF corre antes que `auth` en el stack `web`, coincide con lo anticipado). **Corrección posterior (Grupo 12):** el wildcard de `tipos-cambio` originalmente quedó como `{tipos_cambio}` (plural, generado automáticamente) y rompía el binding implícito contra `TipoCambio $tipoCambio` del controller — jamás se probó con una request HTTP real autenticada en este grupo (solo `{moneda}`), así que el bug no se vio hasta los tests end-to-end. Se corrigió con `->parameters(['tipos-cambio' => 'tipo_cambio'])`; el wildcard real ahora es `{tipo_cambio}` (singular), confirmado con `route:list -v`.

## 10. Vistas Inertia

- [x] 10.1 `resources/js/Pages/Monedas/Index.vue`: tabla (código, nombre, símbolo, decimales, estado) + toggle switch por fila. El aviso de "en uso" llega vía Banner/flash.danger (controller, Grupo 8), no lógica propia de la página.
- [x] 10.2 `resources/js/Pages/TiposCambio/Index.vue`: agrupado por par (`computed`, unordered — BOB→USDT y USDT→BOB caen en el mismo grupo "BOB↔USDT"), muestra la dirección vigente de cada una (primera ocurrencia, ya viene ordenado por fecha desc desde el backend) + "Ver historial" (query string `?origen=&destino=`). Cuando la URL trae ese filtro, la página cambia a vista "historial": lista plana de todos los registros de esa dirección específica, con botón "Volver". Empty state si no hay tipos de cambio. `ConfirmationModal` para eliminar (mismo patrón que Presupuestos).
- [x] 10.3 `resources/js/Pages/TiposCambio/Create.vue`: `MonedaSelector` origen + destino (destino con `excluir=[origen]`), `TextInput type="number" step="0.00000001"` para tasa, `TextInput type="date"` para fecha (date picker nativo, sin librería nueva).
- [x] 10.4 `resources/js/Pages/TiposCambio/Edit.vue`: mismos campos, `MonedaSelector` con `disabled` para origen/destino, solo tasa/fecha editables.
- [x] 10.5 Actualizar `resources/js/Pages/Presupuestos/Create.vue`: `MonedaSelector` para `moneda_base_codigo`, ubicado antes de Color/Icono (más estructural que cosmético). `useForm` incluye `moneda_base_codigo: ''` por defecto.
- [x] 10.6 Actualizar `resources/js/Pages/Presupuestos/Edit.vue`: badge de solo lectura (icono `Coins` + código en `font-mono` + nombre/símbolo resuelto contra `monedasActivas`, con fallback al código plano si la moneda fue desactivada) con `title` tooltip. `useForm` no incluye el campo — nunca viaja al backend.
- [x] 10.7 Crear componente `resources/js/Components/MonedaSelector.vue` reusable (dropdown nativo con código + símbolo + nombre, consume `usePage().props.monedasActivas`). Props extra sobre lo mínimo pedido: `excluir` (array de códigos a deshabilitar — para que destino no repita el código de origen en TiposCambio/Create) y `disabled` (para TiposCambio/Edit, origen/destino bloqueados).
- [x] 10.8 Actualizar `resources/js/Components/PresupuestoCard.vue` variante `selector`: "Nombre · CODIGO" (código en `font-mono text-xs text-text-secondary`) cuando `moneda_base_codigo` esté definido; solo nombre si no (fallback legacy). Verificado visualmente que el navbar sigue mostrando el presupuesto activo correctamente tras el cambio.

## 11. HandleInertiaRequests

- [x] 11.1 En `share()` agregar `monedasActivas` (Moneda::activas()->orderBy('codigo')->get()) — compartido a todas las páginas para dropdowns. **Bug de infraestructura encontrado y corregido:** al ser una query real (no config estático como `iconosPresupuesto`), rompía `ExampleTest.php` (el scaffold de Laravel que pega a `/` sin `RefreshDatabase`, nunca antes tocado) con "no such table: monedas" en SQLite. Se le agregó `use RefreshDatabase` — es el único test de la suite que no la tenía, y ahora la app legítimamente necesita DB migrada para renderizar cualquier página.
- [x] 11.2 `loadMissing(['presupuestos', 'presupuestoActivo.monedaBase'])` en el share de `auth.user`.

## 12. Tests (Pest)

- [x] 12.1 Test: MonedaController::index muestra las 3 monedas
- [x] 12.2 Test: MonedaController::toggle activa/desactiva correctamente
- [x] 12.3 Test: MonedaController::toggle rechaza desactivar una moneda en uso por algún presupuesto
- [x] 12.4 Test: TipoCambioController::store crea tipo de cambio válido
- [x] 12.5 Test: TipoCambioController::store rechaza moneda_origen == moneda_destino
- [x] 12.6 Test: TipoCambioController::store rechaza fecha futura
- [x] 12.7 Test: TipoCambioController::store rechaza tasa <= 0
- [x] 12.8 Test: TipoCambioController::store rechaza duplicado (mismo user + origen + destino + fecha)
- [x] 12.9 Test: TipoCambioController::update permite editar tasa y fecha pero no origen/destino
- [x] 12.10 Test: TipoCambioController::destroy elimina hard-delete
- [x] 12.11 Test: TipoCambioController policy — usuario NO puede editar tipo de cambio ajeno (403)
- [x] 12.12 Test: TasaCambioService::resolver retorna 1.0 para origen == destino
- [x] 12.13 Test: TasaCambioService::resolver retorna la tasa más reciente <= fecha
- [x] 12.14 Test: TasaCambioService::resolver retorna esExtrapolada=true cuando no hay histórico
- [x] 12.15 Test: TasaCambioService::resolver retorna null cuando no hay ninguna tasa para el par
- [x] 12.16 Test: TasaCambioService::convertir aplica la tasa correctamente y redondea half-up
- [x] 12.17 Test: PresupuestoController::store requiere moneda_base_codigo válida
- [x] 12.18 Test: PresupuestoController::update ignora cambio de moneda_base_codigo (fija tras crear)
- [x] 12.19 (extra) Test: `monedasActivas` se comparte a todas las páginas vía Inertia
- [x] 12.20 (extra) Test: `TipoCambioFactory` garantiza `moneda_origen` != `moneda_destino` consistentemente (20 iteraciones)
- [x] 12.21 (extra) Test: tasa en el límite superior permitido (99999999) pasa validación
- [x] 12.22 (extra) Test: tasa en el límite inferior permitido (0.00000001) pasa validación
- [x] 12.23 (extra) Test: Presupuesto con moneda inactiva → validación rechaza en store

  **Dos bugs reales encontrados por estos tests end-to-end (ninguno visible en la verificación manual de Grupos 7-9, que siempre invocó controllers/FormRequests directamente o simuló el routing a mano):**

  1. **Route model binding roto en `tipos-cambio.update`/`destroy`/`edit`.** `Route::resource('tipos-cambio', ...)` genera wildcard `{tipos_cambio}` (plural — `Str::singular('tipos-cambio')` no le quita la 's' a "tipos", confirmado en Grupo 7), pero el método del controller usa `TipoCambio $tipoCambio` (singular). `ImplicitRouteBinding::getParameterName()` prueba el nombre exacto y su `Str::snake()` (`tipo_cambio`) — ninguno matchea `tipos_cambio` — así que el binding se salta en silencio y el parámetro queda como el ID crudo (string), no el modelo. Verificado con tinker: tras `SubstituteBindings`, el parámetro seguía siendo `"9"`. Consecuencia: `UpdateTipoCambioRequest::authorize()` hacía `Gate::can('update', "9")`, que no puede resolver policy para un scalar y deniega por defecto → 403 incluso para el dueño real. **Fix de raíz:** `->parameters(['tipos-cambio' => 'tipo_cambio'])` en la definición de la ruta (`routes/web.php`), en vez de renombrar el parámetro del controller — corrige el wildcard mal generado en su origen. Actualizado también `UpdateTipoCambioRequest::authorize()`/`fechaUnicaRule()` de `$this->route('tipos_cambio')` a `$this->route('tipo_cambio')`.
  2. **`fechaUnicaRule()` (Store y Update) nunca detectaba duplicados reales.** El cast `date` del modelo normaliza cualquier valor asignado a `"Y-m-d H:i:s"` al guardar, aunque la columna sea `DATE` — pero la regla comparaba con `where('fecha', $value)` usando el input crudo (`"Y-m-d"`, sin hora), que nunca matchea el valor real almacenado en SQLite (Postgres sí trunca el string al tipo `DATE` en el INSERT, por eso no se había visto antes). Consecuencia: la validación dejaba pasar duplicados, y el INSERT reventaba con una `UniqueConstraintViolationException` sin manejar (500) en vez de un 422 controlado. **Fix:** `whereDate('fecha', $value)` en ambos Requests — extrae solo la parte de fecha en SQL, portable entre SQLite y Postgres.

  Suite tras ambos fixes: 77/77 (71 passed + 6 skipped pre-existentes, 0 failed). Delta: 54 → 77 (+23: 18 del plan + 5 extra).

## 13. Verificación manual

- [x] 13.1 `migrate:fresh --seed`: 3 monedas (BOB/USD/USDT) + 6 tipos de cambio de María confirmados por tinker.
- [x] 13.2 Login como María (Playwright) → navbar muestra "Personal · BOB". PASS.
- [x] 13.3 Cambiar activo a "Freelance USD" → navbar muestra "Freelance USD · USD". PASS.
- [x] 13.4 `/monedas` → 3 filas, 3 "Activa". PASS.
- [x] 13.5 Desactivar BOB → banner "No se puede desactivar BOB: en uso por Personal". PASS.
- [x] 13.6 `/tipos-cambio` → 3 grupos: BOB↔USDT, BOB↔USD, USD↔USDT. PASS.
- [x] 13.7 Crear BOB→USDT fecha hoy tasa 0.15 → aparece como vigente del grupo. PASS.
- [x] 13.8 Editar el recién creado, tasa → 0.16 → refleja el cambio. PASS.
- [x] 13.9 Borrar el recién creado → el grupo vuelve a mostrar la tasa del seed (0.14, fecha del seed). PASS.
- [x] 13.10 `/presupuestos/create` → dropdown con BOB, USD, USDT. PASS.
- [x] 13.11 Crear "Ahorros Emergencia" con USDT → tarjeta en Index refleja la moneda. PASS.
- [x] 13.12 `/presupuestos/{id}/edit` del recién creado → badge de solo lectura, sin `<select>`. PASS.
- [x] 13.13 `php artisan test`: 77/77 (71 passed + 6 skipped, 0 failed).

  Recorrido único con Playwright (mismo harness de Change 2 Grupos 7/8/10 y de este change Grupo 10), `waitFor({state:...})` con retry en cada paso, nunca `networkidle`+snapshot. Cero errores de consola en todo el recorrido. Datos de prueba (tipo de cambio y presupuesto temporales) limpiados al final; BD devuelta exactamente al estado del seed (6 tipos de cambio, 2 presupuestos, Personal activo) antes de `php artisan test`.