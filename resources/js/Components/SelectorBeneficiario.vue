<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

// El elemento raíz es un <div> (necesario para posicionar la lista
// desplegable), no el <input> en sí -- a diferencia de TextInput/Textarea,
// el fallthrough automático de atributos pondría `id` (y cualquier otro
// atributo no declarado) en el <div> equivocado, rompiendo la asociación
// con <label for="...">. Se declara `id` explícito y se desactiva el
// fallthrough para controlarlo a mano.
defineOptions({ inheritAttrs: false });

const props = defineProps({
    modelValue: {
        type: [Number, String],
        default: null,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    placeholder: {
        type: String,
        default: 'Buscar beneficiario...',
    },
    id: {
        type: String,
        default: undefined,
    },
});

const emit = defineEmits(['update:modelValue']);

// Fuente de verdad: beneficiariosDelPresupuestoActivo del share (Change 6).
// Autocompletar hecho a mano (sin librería): el array ya viene cargado
// completo desde el share, filtrar en un computed local es más simple y
// rápido que cualquier componente de autocompletado con fetch remoto.
const beneficiarios = computed(() => usePage().props.beneficiariosDelPresupuestoActivo);
const seleccionado = computed(() => beneficiarios.value.find((b) => b.id === props.modelValue) ?? null);

const busqueda = ref('');
const abierto = ref(false);

const filtrados = computed(() => {
    const termino = busqueda.value.trim().toLowerCase();

    return termino === ''
        ? beneficiarios.value
        : beneficiarios.value.filter((b) => b.nombre.toLowerCase().includes(termino));
});

const seleccionar = (beneficiario) => {
    emit('update:modelValue', beneficiario.id);
    busqueda.value = '';
    abierto.value = false;
};

// Idempotente a propósito: @click también llama a esto (ver nota abajo), y
// si ya está abierto un click para reposicionar el cursor no debe borrar
// una búsqueda en curso.
const alEnfocar = () => {
    if (abierto.value) {
        return;
    }
    busqueda.value = '';
    abierto.value = true;
};

// Delay corto para que un click en una opción (mousedown.prevent ya evita el
// blur ahí, pero el link "Crear nuevo..." sí navega y no lo intercepta)
// registre antes de que el blur cierre la lista.
const alPerderFoco = () => {
    setTimeout(() => { abierto.value = false; }, 150);
};
</script>

<template>
    <div class="relative">
        <input
            :id="id"
            :value="abierto ? busqueda : (seleccionado?.nombre ?? '')"
            type="text"
            :disabled="disabled"
            :placeholder="placeholder"
            class="w-full border-gray-300 focus:border-accent-primary focus:ring-accent-primary rounded-md shadow-sm dark:bg-base dark:border-border dark:text-text dark:placeholder-text-secondary disabled:opacity-50 disabled:cursor-not-allowed transition duration-150 ease-in-out"
            @focus="alEnfocar"
            @click="alEnfocar"
            @input="busqueda = $event.target.value"
            @blur="alPerderFoco"
        >

        <ul
            v-if="abierto"
            class="absolute z-10 mt-1 w-full max-h-56 overflow-auto rounded-md border border-gray-200 dark:border-border bg-white dark:bg-surface-elevated shadow-lg"
        >
            <li v-if="filtrados.length === 0" class="px-3 py-2 text-sm text-text-secondary">
                Sin resultados
            </li>
            <li v-for="beneficiario in filtrados" :key="beneficiario.id">
                <button
                    type="button"
                    class="w-full text-start px-3 py-2 text-sm text-gray-700 dark:text-text hover:bg-gray-100 dark:hover:bg-border transition duration-150"
                    @mousedown.prevent="seleccionar(beneficiario)"
                >
                    {{ beneficiario.nombre }}
                </button>
            </li>
            <li class="border-t border-gray-200 dark:border-border">
                <Link
                    :href="route('beneficiarios.create')"
                    class="block px-3 py-2 text-sm text-accent-primary hover:text-green-400 transition duration-150"
                >
                    + Crear nuevo...
                </Link>
            </li>
        </ul>
    </div>
</template>
