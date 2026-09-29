import { Banknote, Landmark, Wallet } from 'lucide-vue-next';

// Mapa string (TipoCuenta::opciones()['icono'], PascalCase igual al nombre
// del export de lucide-vue-next) -> componente Vue. Distinto de
// lucideIconMap.js (claves kebab-case de config('mynab.iconos_presupuesto'))
// porque el enum de tipo de cuenta expone el nombre Lucide literal, no un
// slug propio del proyecto.
export const tipoCuentaIconMap = {
    Landmark,
    Banknote,
    Wallet,
};

// Mismo mapeo pero por el value crudo del enum ('banco'/'efectivo'/'wallet'),
// para componentes que reciben una `Cuenta` ya serializada (el cast a
// BackedEnum se serializa como el string `value`, no como `{value, label,
// icono}`) y no tienen a mano el array de `tiposCuenta` del controller.
export const tipoCuentaIconPorValor = {
    banco: Landmark,
    efectivo: Banknote,
    wallet: Wallet,
};
