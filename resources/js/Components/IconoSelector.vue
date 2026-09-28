<script setup>
import { usePage } from '@inertiajs/vue3';
import { lucideIconMap } from '@/lucideIconMap';

defineProps({
    modelValue: {
        type: String,
        required: true,
    },
});

defineEmits(['update:modelValue']);

// Fuente de verdad: config('mynab.iconos_presupuesto'), compartida por
// HandleInertiaRequests. Si el backend agrega/quita un icono, este selector
// se ajusta solo (siempre que el icono ya tenga su import en lucideIconMap).
const iconos = usePage().props.iconosPresupuesto;
</script>

<template>
    <div class="grid grid-cols-5 sm:grid-cols-8 gap-2" role="radiogroup" aria-label="Icono del presupuesto">
        <button
            v-for="icono in iconos"
            :key="icono"
            type="button"
            role="radio"
            :aria-checked="modelValue === icono"
            :aria-label="icono"
            class="flex items-center justify-center aspect-square rounded-lg border transition duration-150"
            :class="modelValue === icono
                ? 'border-accent-primary bg-accent-primary/10 text-accent-primary'
                : 'border-gray-300 bg-white text-gray-500 hover:text-gray-700 dark:border-border dark:bg-surface dark:text-text-secondary dark:hover:text-text'"
            @click="$emit('update:modelValue', icono)"
        >
            <component :is="lucideIconMap[icono]" class="size-5" />
        </button>
    </div>
</template>
