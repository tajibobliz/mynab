<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Iconos de presupuesto
    |--------------------------------------------------------------------------
    |
    | Whitelist de iconos Lucide permitidos para personalizar un presupuesto.
    | Backend (StorePresupuestoRequest/UpdatePresupuestoRequest) y frontend
    | (IconoSelector.vue, vía prop compartida de Inertia) leen de aquí, para
    | no mantener la lista duplicada en dos lenguajes.
    |
    */

    'iconos_presupuesto' => [
        'wallet',
        'briefcase',
        'plane',
        'gift',
        'heart',
        'home',
        'car',
        'graduation-cap',
        'utensils',
        'shopping-cart',
        'piggy-bank',
        'credit-card',
        'coins',
        'dollar-sign',
        'trending-up',
        'target',
        'book',
        'dumbbell',
        'music',
        'film',
    ],

    /*
    |--------------------------------------------------------------------------
    | Iconos de grupo de categorías
    |--------------------------------------------------------------------------
    |
    | Whitelist de iconos Lucide para GrupoCategoria. A diferencia de
    | iconos_presupuesto (kebab-case), estos valores son el nombre EXACTO del
    | export de lucide-vue-next (PascalCase) — mismo patrón ya usado por
    | TipoCuenta::opciones() en Change 4 ('Landmark', 'Banknote', 'Wallet').
    | El frontend los resuelve con grupoCategoriaIconMap.js (no
    | lucideIconMap.js, que es kebab-case y de iconos_presupuesto).
    |
    */

    'iconos_grupo_categoria' => [
        'AlertCircle',
        'Home',
        'Car',
        'ShoppingCart',
        'Shirt',
        'Scissors',
        'Gift',
        'Sparkles',
        'Music',
        'Tv',
        'PiggyBank',
        'TrendingUp',
        'Target',
        'Umbrella',
        'Coffee',
        'Utensils',
        'Wifi',
        'Fuel',
        'Book',
        'Dumbbell',
    ],

];
