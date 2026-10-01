## Why

MyNAB implementa el método envelope de YNAB: cada peso/dólar/USDT tiene un trabajo asignado a una categoría. Sin categorías, no hay envelope y no hay zero-based budgeting (Change 8). Sin grupos, la UI con 20+ categorías es una lista plana ilegible.

El escenario oficial de María define 4 grupos con 10 categorías:
- **Obligaciones inmediatas:** Alquiler, Transporte, Comida básica
- **Gastos reales:** Ropa, Cortes de pelo, Regalos
- **Calidad de vida:** Salidas, Suscripciones
- **Ahorros:** Emergencia, Viajes

Cada grupo tiene identidad visual (color + icono) que sus categorías heredan, evitando UI de picker por cada categoría (Fase 2 podría añadirlo).

Este change vive dentro del contexto del presupuesto activo (mismo patrón que Cuentas del Change 4): María en "Personal" ve sus 4 grupos y 10 categorías; en "Freelance USD" ve otro conjunto vacío al que agrega lo que necesite.

## What Changes

- Tabla `grupos_categorias` por presupuesto: nombre, color hex, icono Lucide, timestamps, soft delete
- Tabla `categorias` por grupo: nombre, timestamps, soft delete
- Categoría PERTENECE a un grupo obligatorio (FK cascadeOnDelete)
- Grupo PERTENECE a un presupuesto obligatorio (FK cascadeOnDelete)
- CRUD `/grupos-categorias` con Index (lista con categorías embebidas), Create, Edit, Destroy
- CRUD `/categorias` con Store, Edit, Update, Destroy (sin Index, sin Show — se ven dentro del grupo)
- Vista integrada: `/grupos-categorias` es la vista maestra que muestra grupos con sus categorías anidadas
- Orden: grupos alfabético, categorías alfabético dentro de cada grupo (drag&drop es Fase 2)
- Reutilización de `IconoSelector` + `ColorPicker` (componentes de Change 2)
- Seeder de María con los 4 grupos + 10 categorías del escenario oficial
- Nueva prop en `HandleInertiaRequests`: `gruposCategoriasDelPresupuestoActivo` con categorías anidadas para dropdowns futuros (Change 7)

## Capabilities

### New Capabilities
- `categorias`: grupos de categorías y categorías individuales por presupuesto, con identidad visual heredada, soft delete, orden alfabético.

### Modified Capabilities
- Ninguna. `presupuestos`, `monedas`, `tipos-cambio`, `cuentas` quedan intactos.

## Impact

- Change 7 (`gestion-transacciones`) usará `categoria_id` como FK en la tabla `transacciones` para clasificar cada movimiento.
- Change 8 (`zero-based-budgeting-basico`) leerá las categorías del presupuesto activo para mostrar la tabla Assigned/Activity/Available.
- Nueva prop compartida en Inertia para dropdowns futuros.
- Sin cambios de esquema en tablas existentes.
- Sin data migration destructiva.