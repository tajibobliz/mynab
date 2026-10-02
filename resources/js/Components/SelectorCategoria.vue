<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

defineProps({
    modelValue: {
        type: [Number, String],
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    placeholder: {
        type: String,
        default: 'Selecciona una categoría',
    },
});

defineEmits(['update:modelValue']);

// Fuente de verdad: gruposCategoriasDelPresupuestoActivo del share (Change 5),
// mismo patrón que MonedaSelector.vue con monedasActivas. <optgroup> hace que
// los grupos sean headers NO seleccionables de forma nativa, sin lógica extra.
const grupos = computed(() => usePage().props.gruposCategoriasDelPresupuestoActivo);
</script>

<template>
    <select
        :value="modelValue"
        :disabled="disabled"
        class="w-full border-gray-300 focus:border-accent-primary focus:ring-accent-primary rounded-md shadow-sm dark:bg-base dark:border-border dark:text-text disabled:opacity-50 disabled:cursor-not-allowed transition duration-150 ease-in-out"
        @change="$emit('update:modelValue', $event.target.value === '' ? '' : Number($event.target.value))"
    >
        <!-- Sin `disabled`: ver nota en SelectorCuenta.vue (reutilización en
             FiltrosTransacciones.vue + bug de Number('')===0). -->
        <option value="">{{ placeholder }}</option>
        <optgroup v-for="grupo in grupos" :key="grupo.id" :label="grupo.nombre">
            <option v-for="categoria in grupo.categorias" :key="categoria.id" :value="categoria.id">
                {{ categoria.nombre }}
            </option>
        </optgroup>
    </select>
</template>
