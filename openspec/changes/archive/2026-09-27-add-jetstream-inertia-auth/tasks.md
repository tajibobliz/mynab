## 1. Configuración de estilo visual

- [x] 1.1 Configurar paleta Neo-YNAB dark en `tailwind.config.js` (bg base #0a0a0a, superficies #141414/#1c1c1c, bordes #262626, texto #fafafa/#a3a3a3, acento verde #22c55e, violeta #8b5cf6, semánticos success/warning/danger/info)
- [x] 1.2 Habilitar `darkMode: 'class'` en `tailwind.config.js`
- [x] 1.3 Importar fuentes Inter y JetBrains Mono desde Google Fonts en `resources/css/app.css`
- [x] 1.4 Configurar `font-sans` = Inter y `font-mono` = JetBrains Mono en `tailwind.config.js`

## 2. Layout global

- [x] 2.1 Modificar `resources/js/Layouts/AppLayout.vue` para aplicar fondo base `bg-[#0a0a0a]` y clase `dark` por defecto en el `<html>`
- [x] 2.2 Personalizar el navbar del `AppLayout.vue` con la paleta Neo-YNAB dark
- [x] 2.3 Crear componente `resources/js/Components/ThemeToggle.vue` con iconos Lucide (Sun/Moon) y persistencia en `localStorage` con clave `mynab-theme`
- [x] 2.4 Integrar `ThemeToggle` en el navbar del `AppLayout.vue`
- [x] 2.5 Agregar script inline en el `<head>` del blade principal para leer `mynab-theme` de localStorage antes del render y evitar flash

## 3. Vistas de autenticación

- [x] 3.1 Personalizar `resources/js/Pages/Auth/Login.vue` con estilo Neo-YNAB dark (tarjeta central `bg-[#141414]`, borde `#262626`, título "MyNAB" en verde `#22c55e`, botón primario verde)
- [x] 3.2 Personalizar `resources/js/Pages/Auth/Register.vue` con el mismo estilo que Login
- [x] 3.3 Personalizar `resources/js/Pages/Auth/ForgotPassword.vue` con el mismo estilo
- [x] 3.4 Personalizar `resources/js/Pages/Auth/ResetPassword.vue` con el mismo estilo

## 3b. Restilado dark del scaffolding Jetstream

Requerido por `ui-base` ("Paleta Neo-YNAB dark aplicada" en todas las páginas): Profile y API comparten componentes con Auth. Solo clases dark; los textos en inglés no se traducen.

- [x] 3b.1 Secciones y bordes: `FormSection`, `ActionSection`, `SectionTitle`, `SectionBorder`, `ActionMessage`
- [x] 3b.2 Botones y modales: `SecondaryButton`, `DangerButton`, `Modal`, `DialogModal`, `ConfirmationModal`
- [x] 3b.3 Páginas Profile y API: `Profile/Show` + 4 parciales, `API/Index` + `ApiTokenManager`
- [x] 3b.4 Páginas legales y verificación: `TermsOfService`, `PrivacyPolicy`, `Auth/VerifyEmail`

## 4. Dashboard placeholder

- [x] 4.1 Personalizar `resources/js/Pages/Dashboard.vue` con mensaje de bienvenida "Bienvenido a MyNAB, {nombre}"
- [x] 4.2 Agregar nota temporal indicando que aquí vivirá el dashboard del presupuesto activo
- [x] 4.3 Aplicar estilo Neo-YNAB dark al dashboard

## 5. Tests

- [x] 5.1 Test Feature: usuario puede registrarse exitosamente con datos válidos
- [x] 5.2 Test Feature: usuario NO puede registrarse con email duplicado
- [x] 5.3 Test Feature: usuario NO puede registrarse con contraseña de menos de 8 caracteres
- [x] 5.4 Test Feature: usuario puede iniciar sesión con credenciales válidas
- [x] 5.5 Test Feature: usuario NO puede iniciar sesión con credenciales inválidas
- [x] 5.6 Test Feature: usuario autenticado accede a `/dashboard`
- [x] 5.7 Test Feature: usuario no autenticado es redirigido a `/login` al intentar acceder a `/dashboard`
- [x] 5.8 Test Feature: usuario puede cerrar sesión desde el navbar

## 6. Verificación manual

- [x] 6.1 Correr `npm run dev` y `php artisan serve`
- [x] 6.2 Abrir http://localhost:8000, registrarse como "María Rojas" y verificar que llega a `/dashboard` con estilo dark
- [x] 6.3 Verificar el toggle de tema: cambiar a light, recargar, verificar persistencia
- [x] 6.4 Verificar responsive en móvil (chrome devtools) — layout no debe romperse
- [x] 6.5 Cerrar sesión y verificar redirección a `/`