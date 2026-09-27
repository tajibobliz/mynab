# MyNAB — Instrucciones para Claude Code

Lee `openspec/project.md` para contexto de negocio y alcance. Este archivo
tiene las reglas técnicas que debes seguir SIEMPRE.

## Stack fijo (no proponer alternativas)

- Laravel 12 + PHP 8.4
- Inertia.js + Vue 3 Composition API con `<script setup>`
- PostgreSQL 17
- Tailwind CSS + Lucide icons
- Jetstream (Inertia stack) para auth
- Pest para tests

## Metodología: OpenSpec (SDD)

- Cada funcionalidad vive en un **change** dentro de `openspec/changes/<nombre>/`
- Un change tiene mínimo: `proposal.md`, `tasks.md`, `spec.md`
- **NO empezar a codear un change sin revisar su `spec.md` primero**
- Al terminar TODAS las tareas de `tasks.md`, ejecutar: `openspec archive <nombre-del-change>`
- Los changes archivados van a `openspec/changes/archive/` y consolidan `openspec/specs/`
- **Nunca inventar features fuera del spec.** Si algo falta, actualizar el spec primero.

## Patrones obligatorios de código

### Controladores (Inertia + redirect, NUNCA JSON)

```php
public function store(StoreTransaccionRequest $request)
{
    $transaccion = Transaccion::create($request->validated());
    return redirect()
        ->route('transacciones.index')
        ->with('flash.success', 'Transacción registrada.');
}

public function index()
{
    return Inertia::render('Transacciones/Index', [
        'transacciones' => Transaccion::with('categoria', 'beneficiario')
            ->latest('fecha_hora')
            ->paginate(20),
    ]);
}
```

**Prohibido:** `response()->json(...)`, `return response(..., 200)`, códigos HTTP semánticos.

### Validación

Siempre en FormRequest, nunca inline.

### Autorización

Siempre en Policy, nunca `if ($user->id === ...)` en el controller.

### Migraciones

- Tabla en plural español: `transacciones`, `cuentas`, `categorias`
- Montos en `bigInteger` como centavos: `$table->bigInteger('monto_centavos');`
- Fechas con hora: `$table->timestampTz('fecha_hora');`
- Foreign keys explícitas: `$table->foreignId('presupuesto_id')->constrained()->cascadeOnDelete();`

### Vue components

- Composition API con `<script setup>`
- Formularios con `useForm` de Inertia
- Nombres de componentes en PascalCase

## Estilo visual (aplicar SIEMPRE)

Ver `openspec/project.md` sección "Estilo visual" para la paleta completa.

- **Dark mode por defecto** (no light default)
- Iconos siempre de **Lucide** (paquete `lucide-vue-next`)
- Montos SIEMPRE con `font-mono` (JetBrains Mono)
- Animaciones suaves (transición de 150-200ms en hovers, modales, etc.)

## Tests

- Feature tests con Pest
- Cobertura mínima por cada change:
  - Happy path (crear/actualizar/eliminar exitoso)
  - Validación fallida
  - Autorización denegada
  - Edge case del dominio

## Comandos frecuentes

```bash
php artisan serve
npm run dev
php artisan test
php artisan migrate:fresh --seed
openspec list
openspec archive <nombre-change>
```

## Cosas que NO debes hacer

- No usar `dd()` ni `dump()` en código commiteado
- No crear archivos fuera de las convenciones Laravel
- No proponer librerías extra sin justificación fuerte
- No romper el patrón Inertia+redirect
- No commitear `.env` ni claves
- No hacer `git push --force` sin avisar