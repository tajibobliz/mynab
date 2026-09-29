<script setup>
import { usePage } from '@inertiajs/vue3';

defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    // Códigos a excluir del listado (p. ej. la moneda ya elegida como origen
    // no debe poder repetirse como destino).
    excluir: {
        type: Array,
        default: () => [],
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['update:modelValue']);

// Fuente de verdad: Moneda::activas(), compartida por HandleInertiaRequests
// (Grupo 11) a todas las páginas — mismo patrón que iconosPresupuesto.
const monedas = usePage().props.monedasActivas;
</script>

<template>
    <select
        :value="modelValue"
        :disabled="disabled"
        class="border-gray-300 focus:border-accent-primary focus:ring-accent-primary rounded-md shadow-sm dark:bg-base dark:border-border dark:text-text disabled:opacity-50 disabled:cursor-not-allowed transition duration-150 ease-in-out"
        @change="$emit('update:modelValue', $event.target.value)"
    >
        <option value="" disabled>Selecciona una moneda</option>
        <option
            v-for="moneda in monedas"
            :key="moneda.codigo"
            :value="moneda.codigo"
            :disabled="excluir.includes(moneda.codigo)"
        >
            {{ moneda.codigo }} ({{ moneda.simbolo }}) — {{ moneda.nombre }}
        </option>
    </select>
</template>
