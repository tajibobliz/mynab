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

];
