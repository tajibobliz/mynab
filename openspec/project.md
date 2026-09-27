# MyNAB — Sistema de presupuesto personal (réplica local de YNAB)

## Propósito

Sistema web local que replica el método YNAB (You Need A Budget) para gestión
de presupuesto personal, basado en el principio zero-based budgeting
("darle un trabajo a cada peso"). Proyecto académico para la materia Tópicos,
UAGRM, Facultad de Ingeniería en Ciencias de la Computación y Telecomunicaciones.

## Alcance funcional

### Dentro del alcance
- Autenticación de usuarios (Jetstream + Inertia)
- Multi-presupuesto por usuario (un usuario puede tener varios presupuestos)
- Multi-moneda: BOB, USD, USDT con tipo de cambio manual
- Cuentas simples (banco, efectivo, wallet cripto) sin lógica especial
- Category groups + categorías (sobres del método envelope)
- Beneficiarios (payees) como entidad de primera clase
- Transacciones outflow / inflow / transfer con timestamp completo
- Split transactions (una transacción dividida en múltiples categorías)
- Campo de monto tipo calculadora (acepta expresiones "50+30*2")
- Zero-based budgeting: Ready to Assign + Assigned/Activity/Available + rollover mensual
- Dashboard con overview del mes y navegación entre meses
- Dark mode por defecto con toggle a light
- UX pulida (toasts, empty states, loading states, atajos de teclado)

### Fuera del alcance (por decisión del docente y del alcance del avance)
- Conciliación de transacciones (cleared/reconciled)
- Tarjetas de crédito con lógica especial (Credit Card Payment category automática)
- Importación desde bancos, APIs externas, integraciones con terceros
- Targets/metas por categoría (Fase 2)
- Scheduled transactions (Fase 2)
- Reportes avanzados (Fase 2)
- Age of Money (Fase 2)

## Stack técnico

- **Backend:** Laravel 12, PHP 8.4
- **Frontend:** Inertia.js + Vue 3 Composition API
- **DB:** PostgreSQL 17
- **UI:** Tailwind CSS + Lucide icons
- **Auth:** Laravel Jetstream (Inertia stack)
- **Tests:** Pest
- **Metodología:** Spec-Driven Development con OpenSpec

## Convenciones

### Código
- **Nombres de tablas y columnas:** snake_case en español (`presupuestos`, `fecha_hora`, `monto_centavos`)
- **Nombres de modelos:** PascalCase en español (`Presupuesto`, `Cuenta`, `Categoria`, `Transaccion`, `Beneficiario`)
- **Rutas:** kebab-case en español (`/presupuestos`, `/cuentas/{cuenta}/transacciones`)
- **Componentes Vue:** PascalCase (`TransaccionForm.vue`, `CategoriaCard.vue`)
- **Montos:** guardados como enteros en centavos (evita errores de float)
- **Fechas:** `timestamp with time zone` en PostgreSQL, siempre con hora

### Patrón obligatorio de controladores
- **Inertia + redirect**, NO JSON, NO códigos HTTP semánticos
- `store`, `update`, `destroy` → `return redirect()->route('...')->with('flash', '...')`
- `index`, `show`, `create`, `edit` → `return Inertia::render('...', [...])`
- Validación siempre en FormRequest
- Autorización en Policies

### Estilo visual ("Neo-YNAB dark")
- Fondo base: `#0a0a0a`, superficies `#141414` y `#1c1c1c`, bordes `#262626`
- Texto principal `#fafafa`, secundario `#a3a3a3`
- Acento primario (verde disponible): `#22c55e`
- Acento secundario (violeta neutro): `#8b5cf6`
- Semánticos: éxito `#22c55e`, advertencia `#f59e0b`, peligro `#ef4444`, info `#3b82f6`
- Tipografía UI: **Inter**
- Tipografía numérica (montos): **JetBrains Mono**

## Escenario de prueba oficial (seeder)

**Usuario:** María — estudiante de ingeniería en Santa Cruz.
**Ingreso mensual:** Bs. 3.500 (trabajo medio tiempo) + USD 100 (freelance ocasional).

**Cuentas iniciales:**
- BNB Checking — BOB 2.000
- Efectivo — BOB 300
- Binance USDT — USDT 50

**Category Groups + categorías:**
- **Obligaciones inmediatas:** Alquiler (Bs. 1.200/mes), Internet (Bs. 150/mes), Celular (Bs. 80/mes)
- **Gastos reales:** Materiales U (Bs. 200/mes), Regalos (Bs. 100/mes), Ropa (Bs. 150/mes)
- **Calidad de vida:** Comida fuera, Entretenimiento, Café
- **Ahorros:** Fondo de emergencia, Viaje fin de año

**Beneficiarios preseed:** Casero (Don Luis), Tigo, COTAS, Librería Los Amigos,
Supermercado Slam, Tostaduría Café Vaca, Freelance Cliente USA.

## Fase actual

**Fase 1 (avance jueves 01/10/2026):** setup + 8 changes de núcleo funcional
1. add-jetstream-inertia-auth
2. gestion-presupuestos
3. gestion-monedas-tipos-cambio
4. gestion-cuentas
5. gestion-categorias-grupos
6. gestion-beneficiarios
7. gestion-transacciones
8. zero-based-budgeting-basico

**Fase 2 (post-avance):** targets, scheduled, reportes, age of money, cherry UX details.