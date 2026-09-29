## 1. Enum y migración

- [x] 1.1 Crear enum `app/Enums/TipoCuenta.php` (PHP 8.4 backed string): Banco='banco', Efectivo='efectivo', Wallet='wallet'
- [x] 1.2 Método estático `TipoCuenta::opciones()` que retorna `[['value' => 'banco', 'label' => 'Cuenta bancaria', 'icono' => 'Landmark'], ...]` para uso en frontend. Verificado con tinker: `TipoCuenta::from('wallet')`, `opciones()` con los 3 casos.
- [x] 1.3 Crear migración `create_cuentas_table`: id, presupuesto_id (FK cascadeOnDelete), moneda_codigo (**string(5)**, no char — mismo motivo que `monedas.codigo` en Change 3, FK restrictOnDelete), nombre (string 100), tipo (string 20), saldo_inicial_centavos (bigInteger default 0), fecha_apertura (date), numero_referencia (string 50 nullable), timestamps, softDeletes.
- [x] 1.4 Índice en `(presupuesto_id, tipo)` para agrupamiento rápido en Index.
- [x] 1.5 **Sin unique a nivel DB** para `(presupuesto_id, nombre)`: la unicidad case-insensitive se aplica en el FormRequest (Grupo 4) vía `whereRaw('LOWER(nombre) = ?', ...)`, igual que `presupuestos.nombre` — un unique de Postgres sería case-sensitive y entraría en conflicto con esa regla.

  `migrate:fresh --seed` corrido limpio, `php artisan test`: 77/77 (71 passed + 6 skipped, 0 failed) — sin regresiones antes de tocar nada más.

## 2. Modelo Cuenta

- [x] 2.1 Crear `app/Models/Cuenta.php` con: use SoftDeletes, use HasFactory
- [x] 2.2 Fillable: `presupuesto_id`, `moneda_codigo`, `nombre`, `tipo`, `saldo_inicial_centavos`, `fecha_apertura`, `numero_referencia`
- [x] 2.3 Casts: `tipo => TipoCuenta::class`, `fecha_apertura => 'date'`, `saldo_inicial_centavos => 'integer'`
- [x] 2.4 Relación `presupuesto()` belongsTo. Agregada también `Presupuesto::cuentas()` hasMany (modificación cruzada) — verificado `loadMissing('cuentas')` con datos reales.
- [x] 2.5 Relación `moneda()` belongsTo(Moneda::class, 'moneda_codigo', 'codigo'). **Verificado que NO colisiona** con `moneda_codigo` (a diferencia del bug de `TipoCambio` en Change 3): `Str::snake('moneda')` = `'moneda'`, y no existe ninguna columna literal `moneda` en `cuentas` (la columna real es `moneda_codigo`) — sin ambigüedad de clave al serializar.
- [x] 2.6 Accessor `getSaldoActualCentavosAttribute()`: retorna `saldo_inicial_centavos` en este change (Change 7 lo extenderá) — verificado con instancia en memoria (200000 → 200000). **Corrección retroactiva (encontrada en Grupo 8 al construir `CuentaCard.vue`):** un accessor "mágico" (`get{X}Attribute`) no se serializa en `toArray()`/JSON a menos que esté en `$appends` — a diferencia de columnas reales. Verificado con tinker (`saldo_actual_centavos` ausente del JSON), corregido agregando `protected $appends = ['saldo_actual_centavos']`. Sin esto, la prop habría llegado `undefined` al frontend en silencio.
- [x] 2.7 Method `saldoActualFormateado()`: verificado con datos reales — `Bs. 2000.00` para una cuenta BOB de 200000 centavos.

  `php artisan test`: 77/77 (71 passed + 6 skipped, 0 failed).

## 3. Policy

- [x] 3.1 Crear `CuentaPolicy` (`--model=Cuenta`) con: viewAny=true, create=true, view/update/delete=$user->id === $cuenta->presupuesto->user_id, restore/forceDelete=false (scaffold, sin cambios). Auto-discovery confirmado: `Gate::getPolicyFor(Cuenta::class)` resuelve sin registrar nada.
- [x] 3.2 Autorización vía relación (no user_id directo en cuenta): matriz cruzada con dos usuarios reales (María vs. uno nuevo vía factory) verificada para los 10 casos (view/update/delete propio=true, ajeno=false; viewAny/create=true; restore/forceDelete=false). Datos de prueba limpiados al final.

  `php artisan test`: 77/77 (71 passed + 6 skipped, 0 failed).

## 4. Requests

- [x] 4.1 `StoreCuentaRequest`: nombre (required, max 100, unique case-insensitive vía closure `nombreUnicoRule()` — no `Rule::unique()->where()`, su comparación base es exacta y dejaría pasar "PERSONAL" vs "Personal", mismo patrón que Change 2/3), tipo (`Rule::enum(TipoCuenta::class)`), moneda_codigo (**min:3/max:5**, no `size:3` — un `size:3` fijo habría rechazado "USDT", que tiene 4 caracteres y es parte del escenario oficial; corregido antes de escribir el código, no después de que fallara — + `Rule::exists('monedas','codigo')->where('activa', true)`), saldo_inicial_centavos (integer, min:0), fecha_apertura (date, before_or_equal:today), numero_referencia (nullable, max:50). `presupuesto_id` inyectado en `prepareForValidation()`. `authorize()`: **solo** `can('create', Cuenta::class)` — la verificación de "presupuesto activo debe existir" vive en el controller (Grupo 5), no aquí (decisión ya resuelta en el plan).
- [x] 4.2 `UpdateCuentaRequest`: mismas reglas salvo `moneda_codigo`/`presupuesto_id` (fijos tras crear, ni siquiera se validan). `nombreUnicoRule()` lee el presupuesto del registro existente vía `$this->route('cuenta')` y usa `whereKeyNot()`. `authorize()`: `can('update', $this->route('cuenta'))`.
- [x] 4.3 Mensajes en español en ambos, específicos por regla.

  **Verificación con `FormRequest::create()` + `setUserResolver`/`setRouteResolver`** (mismo patrón de Change 3 Grupo 7): los 10 casos pedidos pasaron — store válido, duplicado case-insensitive mismo presupuesto (falla), mismo nombre en otro presupuesto (pasa), tipo inválido (falla, mensaje en español), moneda inactiva (falla), fecha futura (falla), saldo negativo (falla), update ignora `moneda_codigo` (pasa, el campo ni está en las reglas), update con nombre igual al propio (pasa, `whereKeyNot`), update con nombre igual a otra cuenta del mismo presupuesto (falla). Datos de prueba limpiados al final.

  `php artisan test`: 77/77 (71 passed + 6 skipped, 0 failed).

## 5. Controller

- [x] 5.1 Crear `CuentaController` con index, create, store, edit, update, destroy (sin show).
- [x] 5.2 `index()`: guard de presupuesto activo, `Cuenta::where('presupuesto_id', activo)->with('moneda')->orderBy('tipo')->orderBy('nombre')->get()`, agrupamiento en frontend (Grupo 8).
- [x] 5.3 `create()`: guard + Inertia render con `tiposCuenta` (`monedasActivas` ya viene del share global).
- [x] 5.4 `store()`: `Cuenta::create($request->validated())` (presupuesto_id ya viene auto-inyectado por `prepareForValidation`), redirect a index con flash success.
- [x] 5.5 `edit()`: `$this->authorize('update', $cuenta)` explícito (defensa en profundidad, mismo patrón que Presupuesto/TipoCambio — sin esto, un no-dueño podría ver el formulario aunque no pudiera enviarlo) + `cuenta->load('moneda')`.
- [x] 5.6 `update()`: authorize + `array_diff_key` contra `moneda_codigo`/`presupuesto_id`, redirect con flash success.
- [x] 5.7 `destroy()`: authorize + soft delete + redirect con flash info.
- [x] 5.8 Guard centralizado en `presupuestoActivoOrRedirect(): Presupuesto|RedirectResponse` (método privado, un solo lugar para el mensaje en vez de repetirlo en 4 métodos), usado en index/create/store.

  **Verificación de los 6 edge cases** (tinker, controller invocado directo con `Auth::login()` + `FormRequest::create()+setRedirector()`, mismo patrón de Change 3 Grupo 8): index sin activo → redirect a `/dashboard` con `flash.danger` ✓; store válido → `presupuesto_id` correcto ✓; store con `presupuesto_id` manipulado en el payload (Freelance USD) → se ignora, queda el activo real (Personal) ✓; update con `moneda_codigo` distinta → se ignora, saldo sí se actualiza ✓; destroy → `deleted_at` seteado, sigue en BD con `withTrashed()`, no aparece en query normal ✓; destroy de cuenta ajena → `AuthorizationException` (403) ✓. Datos de prueba limpiados.

  **CHECKPOINT CRÍTICO cumplido**: `php artisan test`: 77/77 (71 passed + 6 skipped, 0 failed) antes de pasar al Grupo 6.

## 6. Rutas

- [x] 6.1 En routes/web.php dentro del grupo auth+verified: `Route::resource('cuentas', CuentaController::class)->except('show')`.
- [x] 6.2 Verificado con `route:list -v`: 6 rutas, sin `show`, wildcard `{cuenta}` (singular, sin ambigüedad — "cuentas" no tiene guión, a diferencia de `tipos-cambio`), middleware `auth:sanctum`+`AuthenticateSession`+`verified` idéntico en las 6. **Binding verificado empíricamente igual que en Change 3** (no asumido solo porque "se ve bien"): request real contra `SubstituteBindings` resuelve `{cuenta}` a la instancia `Cuenta` correcta por ID. No hizo falta `->parameters()`. Curl real sin sesión: GET → 302 a `/login`, POST → 419. Playwright con sesión válida pero sin presupuesto activo: `/cuentas` → redirige a `/dashboard` con el flash "Necesitas un presupuesto activo para gestionar cuentas".

  `php artisan test`: 77/77 (71 passed + 6 skipped, 0 failed).

## 7. HandleInertiaRequests

- [x] 7.1 Agregado al share `cuentasDelPresupuestoActivo` como closure — retorna `[]` si no hay usuario o no hay presupuesto activo (nunca `null`).
- [x] 7.2 `loadMissing('presupuestoActivo.cuentas.moneda')` dentro del closure. **Verificado que anida correctamente** sobre `presupuestoActivo` ya cargado por el `loadMissing` anterior (Change 3, `presupuestoActivo.monedaBase`) sin duplicar queries — confirmado con `DB::listen()`.
- [x] 7.3 Solo se cargan cuentas del presupuesto activo (a través de la relación `presupuestoActivo.cuentas`), nunca de otros presupuestos del usuario.

  **Corrección sobre la premisa del enunciado:** la instrucción decía "closure lazy: solo se ejecuta cuando la prop se pide en el frontend". Verifiqué contra el código real de `inertiajs/inertia-laravel` (`Response::resolvePartialProperties()`): en una carga completa (no partial reload), solo se excluyen props que implementan `IgnoreFirstLoad`/`Deferrable` (eso es lo que hace `Inertia::lazy()`) — un `Closure` plano NO se salta ahí, se resuelve en `resolvePropertyInstances()` igual que cualquier otro prop, en **cada** visita autenticada. No es distinto de `monedasActivas` en ese sentido; el closure es solo conveniencia sintáctica para el guard `null`, no una optimización de "solo si se usa". Lo anoto para que quede preciso en el historial, no cambia la implementación.

  **Verificación de N+1** (`DB::listen()`, 3 cuentas de prueba con monedas BOB/BOB/USDT): exactamente **2 queries** al resolver el closure — una para `cuentas` (`whereIn presupuesto_id`), una para `monedas` (`whereIn codigo`, con los 2 códigos distintos, no 3). Sin usuario autenticado: retorna `[]`. Datos de prueba limpiados.

  `php artisan test`: 77/77 (71 passed + 6 skipped, 0 failed).

## 8. Vistas Inertia

- [x] 8.1 `resources/js/Pages/Cuentas/Index.vue`: 3 secciones por tipo (solo las que tengan cuentas), grid `CuentaCard`, empty state global, botón "Nueva cuenta", `ConfirmationModal` para eliminar (patrón Change 2). Totales por moneda separados (`Map` agrupado por `moneda_codigo`, sumando `saldo_actual_centavos`, renderizado con `formatearMonto()`) — sin consolidar, como se decidió en el plan.
- [x] 8.2 `resources/js/Pages/Cuentas/Create.vue`: nombre + `TipoCuentaSelector`, `MonedaSelector` + input de saldo en **unidades** (no centavos) con símbolo de la moneda al lado, fecha_apertura + numero_referencia. Nota "se creará en {presupuestoActivo.nombre}". `form.transform()` convierte `monto` → `saldo_inicial_centavos` vía `centavosDesdeMonto()` justo antes de enviar, sin mutar el estado reactivo del form (el input sigue mostrando unidades aunque el payload real vaya en centavos).
- [x] 8.3 `resources/js/Pages/Cuentas/Edit.vue`: mismo layout, `MonedaSelector` con `disabled` + `title` tooltip (atributo pasado directo al `<select>` vía fallthrough, mismo mecanismo que `type`/`step` en TextInput). `monto` prellenado desde `saldo_inicial_centavos / 10^decimales` (nunca desde `saldo_actual_centavos` — ese es el accessor computed, editar ahí no tendría efecto una vez que Change 7 lo extienda). Mismo `transform()` al enviar.
- [x] 8.4 `resources/js/Components/CuentaCard.vue`: prop `cuenta` + `variant` (default | compact). Icono resuelto vía `tipoCuentaIconPorValor` (mapa por el value crudo del enum, ya que la cuenta serializada trae `tipo` como string plano, no el array `{value,label,icono}`). Variant `compact` en una sola línea (icono + nombre + saldo), lista para Change 7.
- [x] 8.5 `resources/js/Components/TipoCuentaSelector.vue`: dropdown estilizado reutilizando el componente `Dropdown.vue` ya existente (un `<select>` nativo no puede mostrar iconos dentro de sus `<option>`, a diferencia de `MonedaSelector`). Consume `tiposCuenta` del controller.
- [x] 8.6 `resources/js/utils/formatoMoneda.js`: `formatearMonto(centavos, moneda)` con `toLocaleString('en-US')` para separadores de miles + símbolo. Agregado también `centavosDesdeMonto(valorUnidades, decimales)` (inverso, no pedido explícitamente pero necesario para el Sub-grupo B: convierte lo que el usuario escribe en unidades enteras a centavos antes de enviar al backend).

  **Sub-grupo C — verificación de regresión** (Playwright, servidor real): `npm run build` limpio. Recorrido completo con María autenticada por `/dashboard`, `/presupuestos`, `/presupuestos/create`, `/presupuestos/{id}/edit` (badge readonly confirmado), `/monedas`, `/tipos-cambio`, `/tipos-cambio/create`, `/cuentas` (empty state, sin seeder todavía), `/cuentas/create` — sin errores de consola. Navbar "Personal · BOB" intacto. `ThemeToggle` cambia y revierte el tema correctamente. Flash success al crear y al eliminar un tipo de cambio de prueba. Flujo de Presupuesto: crear con moneda, cambiar activo desde el navbar, volver, eliminar — todo funcional. Dashboard empty state (usuario sin presupuestos, aparte) verificado con un usuario temporal. Todos los datos de prueba limpiados; estado final de María idéntico al baseline (2 presupuestos, 6 tipos de cambio, 0 cuentas).

  `php artisan test`: 77/77 (71 passed + 6 skipped, 0 failed).

  **Bug retroactivo del Grupo 2 encontrado y corregido aquí** (ver nota en 2.6): `saldo_actual_centavos` no llegaba al frontend por faltar `$appends` en el modelo.

  `npm run build` limpio. `php artisan test`: 77/77 (71 passed + 6 skipped, 0 failed).

## 9. NavLinks + navbar

- [x] 9.1 Link "Cuentas" agregado en `AppLayout.vue` entre "Presupuestos" y "Monedas", en ambas variantes (`NavLink` desktop, `ResponsiveNavLink` móvil), `:active="route().current('cuentas.*')"`.
- [x] 9.2 Verificado con Playwright en dos viewports: desktop (1280px) — orden correcto Dashboard|Presupuestos|Cuentas|Monedas|Tipos de cambio, link visible en el top nav, resaltado (`border-accent-primary`) tanto en `/cuentas` como en `/cuentas/create` (confirma que el patrón `cuentas.*` cubre todas las sub-rutas); móvil (375px) — el top nav horizontal permanece oculto (0 links visibles, aunque siguen en el DOM), y tras abrir el menú hamburguesa el link "Cuentas" aparece visible.

  `php artisan test`: 77/77 (71 passed + 6 skipped, 0 failed).

## 10. Factory y seeder

- [x] 10.1 `CuentaFactory`: nombre (`fake()->company()`), tipo (random enum value), moneda_codigo (random BOB/USD/USDT), saldo_inicial_centavos (0–10_000_000), fecha_apertura (-2 años a hoy), numero_referencia (50% null), presupuesto_id (`Presupuesto::factory()`, para tests independientes).
- [x] 10.2 Estados `banco()`/`efectivo()`/`wallet()` para tests específicos.
- [x] 10.3 `PresupuestoSeeder` actualizado (sin `CuentaSeeder` separado): 3 cuentas del escenario oficial en "Personal" — BNB Checking (banco, BOB, 200000 centavos, -1 año, "**4417"), Efectivo (efectivo, BOB, 30000 centavos, -6 meses, sin referencia), Binance USDT (wallet, USDT, 5000 centavos = USDT 50.00 con decimales=2, -3 meses, "@maria_binance"). Freelance USD queda sin cuentas a propósito (test 12.3 verifica el empty state).
- [x] 10.4 Verificado con `migrate:fresh --seed` + tinker: Personal → 3 cuentas (`["BNB Checking","Efectivo","Binance USDT"]`), Freelance USD → 0. `saldoActualFormateado()`: "Bs. 2000.00", "Bs. 300.00", "₮ 50.00". Verificación visual (Playwright): las 3 cuentas agrupadas correctamente por sección (Bancarias/Efectivo/Wallets), números de referencia visibles, totales "Total BOB: Bs. 2,300.00" / "Total USDT: ₮ 50.00" (formato del frontend con separador de miles — distinto pero consistente con `saldoActualFormateado()` del backend, que no lo lleva), cambio a Freelance USD → empty state, sin errores de consola.

  `php artisan test`: 77/77 (71 passed + 6 skipped, 0 failed).

## 11. Tests (Pest)

- [x] 11.1 usuario autenticado ve solo cuentas de su presupuesto activo
- [x] 11.2 usuario sin presupuesto activo es redirigido a /dashboard con flash
- [x] 11.3 create renderiza con opciones de tipo y monedas activas
- [x] 11.4 store crea cuenta válida asignada al presupuesto activo
- [x] 11.5 store rechaza tipo inválido (no en enum)
- [x] 11.6 store rechaza moneda inactiva
- [x] 11.7 store rechaza fecha_apertura futura
- [x] 11.8 store rechaza nombre duplicado en el mismo presupuesto (case insensitive)
- [x] 11.9 store permite nombre duplicado en OTRO presupuesto del mismo usuario
- [x] 11.10 update permite editar nombre, tipo, saldo, fecha, referencia
- [x] 11.11 update ignora silenciosamente moneda_codigo (fija tras crear)
- [x] 11.12 update ignora silenciosamente presupuesto_id (fija tras crear)
- [x] 11.13 destroy hace soft delete (deleted_at seteado, registro sigue en BD)
- [x] 11.14 policy: usuario NO puede editar cuenta de otro usuario (403)
- [x] 11.15 policy: usuario NO puede eliminar cuenta de otro usuario (403)
- [x] 11.16 accessor saldo_actual_centavos retorna saldo_inicial_centavos en este change
- [x] 11.17 usuario NO autenticado no accede a /cuentas (redirect a login)
- [x] 11.18 saldo_inicial_centavos = 0 es válido (cuenta nueva sin fondos)
- [x] 11.19 numero_referencia nullable acepta null y string
- [x] 11.20 (extra) eliminar un presupuesto elimina en cascada sus cuentas (`forceDelete()` real, no soft delete — verificado que dispara el `cascadeOnDelete` de la FK)
- [x] 11.21 (extra) no se puede eliminar una moneda con cuentas asociadas (`restrictOnDelete`) — confirmado antes de escribir que `foreign_key_constraints=true` en la conexión sqlite de test (no es automático como en Postgres, SQLite lo apaga por defecto)
- [x] 11.22 (extra) nombre de exactamente 100 caracteres pasa (límite superior)
- [x] 11.23 (extra) nombre de 101 caracteres falla
- [x] 11.24 (extra) saldo_inicial_centavos negativo falla

  `tests/Feature/CuentaTest.php`: 24/24 en aislado. Suite completa: 101/101 (95 passed + 6 skipped, 0 failed) — delta exacto 77 → 101 (+24: 19 del plan + 5 extra). Sin regresiones.

## 12. Verificación manual

- [x] 12.1 `migrate:fresh --seed`: 3 cuentas de María en Personal confirmadas por tinker.
- [x] 12.2 Login María → `/cuentas`: 3 cuentas agrupadas (Bancarias: BNB Checking, Efectivo: Efectivo, Wallets: Binance USDT). PASS.
- [x] 12.3 Cambiar activo a "Freelance USD" → `/cuentas` empty state. PASS.
- [x] 12.4 Crear "Trabajo USD" en Freelance USD, banco, USD, 1000 unidades → tarjeta muestra "$ 1,000.00" (confirma `form.transform()` unidades→centavos end-to-end en un navegador real). PASS.
- [x] 12.5 Volver a "Personal" → siguen las 3 cuentas originales, sin rastro de "Trabajo USD" (aislamiento por presupuesto). PASS.
- [x] 12.6 Editar "BNB Checking", saldo → 2500 unidades → tarjeta muestra "Bs. 2,500.00". PASS.
- [x] 12.7 Moneda de "BNB Checking" en Edit: `disabled=true` + `title="No se puede cambiar la moneda después de crear la cuenta"` confirmado por atributo real del DOM, no solo visualmente. PASS.
- [x] 12.8 Eliminar "Efectivo" → desaparece de la lista; tinker confirma `deleted_at` seteado y ausencia en la query normal. PASS.
- [x] 12.9 Crear "BNB Checking" duplicado → "Ya existe una cuenta con ese nombre en este presupuesto". PASS.
- [x] 12.10 Eliminar Freelance USD → eliminar Personal (último) → redirect automático a `/dashboard` (Change 2) → `/cuentas` redirige a `/dashboard` con el flash esperado → dashboard muestra el CTA "Crea tu primer presupuesto" (Change 2 intacto). PASS.
- [x] 12.11 `php artisan test`: 101/101 (95 passed + 6 skipped, 0 failed).

  Corrección previa (encontrada al preparar el script de verificación, antes de que causara un falso fallo): `Cuentas/Create.vue`/`Edit.vue` no le pasaban `id`/`for` a `MonedaSelector`/`InputLabel` (a diferencia de `Presupuestos` y `TiposCambio`, Change 2/3) — corregido por consistencia y accesibilidad, no solo por testabilidad. Base de datos restaurada a `migrate:fresh --seed` (estado limpio del escenario oficial) después de la verificación destructiva de 12.10.