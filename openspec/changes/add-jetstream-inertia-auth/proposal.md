## Why

MyNAB es un sistema de presupuesto personal donde cada usuario gestiona sus propios presupuestos, cuentas y transacciones. Necesita autenticación desde el día 1 para asociar todos los recursos futuros a un `user_id` y proteger la información financiera de cada usuario. Sin auth no se puede construir ninguna otra funcionalidad del sistema.

## What Changes

- Instalar y configurar Laravel Jetstream con stack Inertia + Vue (ya instalado en setup base)
- Definir el layout global `AppLayout.vue` con estilo Neo-YNAB dark
- Configurar la paleta de colores Neo-YNAB dark en Tailwind
- Instalar y configurar fuentes Inter (UI) y JetBrains Mono (montos)
- Agregar componente `ThemeToggle` con persistencia en localStorage
- Personalizar vistas de auth (Login, Register, ForgotPassword, ResetPassword) con estilo del proyecto
- Dashboard placeholder con mensaje de bienvenida

## Capabilities

### New Capabilities
- `auth`: Registro, login, logout y protección de rutas de usuarios del sistema.
- `ui-base`: Layout global con dark mode por defecto, toggle de tema con persistencia, y paleta Neo-YNAB dark aplicada.

### Modified Capabilities
<!-- Ninguna, es la primera capacidad del proyecto -->

## Impact

- Todas las tablas futuras del dominio (presupuestos, cuentas, categorías, transacciones, etc.) dependerán del `users.id` como foreign key raíz.
- El `AppLayout.vue` queda definido aquí y se reutiliza en todas las páginas autenticadas del sistema.
- La paleta y las fuentes definidas aquí son la base visual de todo el proyecto.
- Dependencias nuevas: `lucide-vue-next` (iconos), Google Fonts (Inter + JetBrains Mono).