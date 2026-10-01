import {
    AlertCircle,
    Book,
    Car,
    Coffee,
    Dumbbell,
    Fuel,
    Gift,
    Home,
    Music,
    PiggyBank,
    Scissors,
    Shirt,
    ShoppingCart,
    Sparkles,
    Target,
    TrendingUp,
    Tv,
    Umbrella,
    Utensils,
    Wifi,
} from 'lucide-vue-next';

// Mapa string (config('mynab.iconos_grupo_categoria'), PascalCase = nombre
// EXACTO del export de lucide-vue-next) -> componente Vue. Distinto de
// lucideIconMap.js (claves kebab-case de iconos_presupuesto) a propósito:
// mismo patrón que tipoCuentaIconMap.js (Change 4), un pool de iconos nuevo
// no reutiliza la convención de nombres del pool anterior.
export const grupoCategoriaIconMap = {
    AlertCircle,
    Home,
    Car,
    ShoppingCart,
    Shirt,
    Scissors,
    Gift,
    Sparkles,
    Music,
    Tv,
    PiggyBank,
    TrendingUp,
    Target,
    Umbrella,
    Coffee,
    Utensils,
    Wifi,
    Fuel,
    Book,
    Dumbbell,
};
