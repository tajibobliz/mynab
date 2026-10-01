## Why

Las transacciones del Change 7 responden a 3 preguntas:
- **Cuánto** → monto (centavos)
- **Dónde** → cuenta (Change 4)
- **Quién / A quién** → beneficiario (payee)

Sin beneficiarios, cada transacción se vuelve un texto libre inconsistente ("alquiler sept", "alq septiembre", "pago alquiler mes 9") y los reportes agrupados por payee (quién recibe más dinero mío, con quién más gasto) se vuelven imposibles.

El docente aprobó explícitamente los beneficiarios como entidad separada en Fase 1. El escenario oficial de María incluye beneficiarios de 3 tipos funcionales:
- **Fijos recurrentes**: Dueño del alquiler, SIM Entel, Netflix, Spotify
- **Variables**: Hipermaxi (supermercado), Mi barbero
- **Fuentes de ingreso**: Empresa X (sueldo), Cliente freelance A

Este change vive dentro del contexto del presupuesto activo (mismo patrón que Cuentas y Categorías): María en "Personal" ve sus beneficiarios; en "Freelance USD" ve otros.

## What Changes

- Tabla `beneficiarios` por presupuesto: nombre, notas opcionales, timestamps, soft delete
- CRUD completo `/beneficiarios` con Index (lista alfabética), Create, Edit, Destroy
- Vista integrada al presupuesto activo (mismo patrón que Cuentas del Change 4)
- Nombre único case-insensitive dentro del presupuesto
- Notas opcionales (text nullable) para contexto adicional
- NavLink "Beneficiarios" en AppLayout
- Nueva prop `beneficiariosDelPresupuestoActivo` en `HandleInertiaRequests` para dropdowns futuros (Change 7)
- Seeder de María con 8 beneficiarios del escenario oficial

## Capabilities

### New Capabilities
- `beneficiarios`: payees por presupuesto con nombre único, notas opcionales, soft delete.

### Modified Capabilities
- Ninguna.

## Impact

- Change 7 (`gestion-transacciones`) usará `beneficiario_id` como FK en la tabla `transacciones`.
- Nueva prop compartida en Inertia para dropdowns futuros.
- Sin cambios de esquema en tablas existentes.