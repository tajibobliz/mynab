<script setup>
import { lucideIconMap } from '@/lucideIconMap';

// `iconos`: lista de strings a mostrar. `iconMap`: diccionario string ->
// componente Lucide para resolverlos. Antes este componente leía
// usePage().props.iconosPresupuesto y lucideIconMap directo, hardcodeado a
// un único pool. Generalizado para Change 5: GruposCategorias usa un pool
// distinto (iconosGrupoCategoria, PascalCase) con su propio mapa
// (grupoCategoriaIconMap.js, ver nota en ese archivo) — pasar lucideIconMap
// ahí resolvería todo a `undefined` en silencio (claves kebab-case vs
// PascalCase). `iconMap` por defecto es lucideIconMap para no romper los
// callers existentes (Presupuestos) que no lo pasan explícitamente.
defineProps({
    modelValue: {
        type: String,
        required: true,
    },
    iconos: {
        type: Array,
        required: true,
    },
    iconMap: {
        type: Object,
        default: () => lucideIconMap,
    },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="grid grid-cols-5 sm:grid-cols-8 gap-2" role="radiogroup" aria-label="Icono">
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
            <component :is="iconMap[icono]" class="size-5" />
        </button>
    </div>
</template>
