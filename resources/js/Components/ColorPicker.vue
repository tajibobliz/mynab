<script setup>
import { computed } from 'vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    modelValue: {
        type: String,
        required: true,
    },
});

const emit = defineEmits(['update:modelValue']);

const PALETA = [
    '#22c55e', // verde
    '#8b5cf6', // violeta
    '#3b82f6', // azul
    '#ec4899', // rosa
    '#f59e0b', // ámbar
    '#06b6d4', // cyan
    '#ef4444', // rojo
    '#64748b', // gris
];

const hexValido = computed(() => /^#[0-9a-fA-F]{6}$/.test(props.modelValue));
const esSeleccionado = (color) => props.modelValue.toLowerCase() === color;
</script>

<template>
    <div class="space-y-3">
        <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Color del presupuesto">
            <button
                v-for="color in PALETA"
                :key="color"
                type="button"
                role="radio"
                :aria-checked="esSeleccionado(color)"
                :aria-label="color"
                class="size-8 rounded-full border-2 transition duration-150"
                :class="esSeleccionado(color) ? 'border-text scale-110' : 'border-transparent hover:scale-105'"
                :style="{ backgroundColor: color }"
                @click="emit('update:modelValue', color)"
            />
        </div>

        <div class="flex items-center gap-2">
            <span
                class="size-8 rounded-full border border-gray-300 dark:border-border shrink-0"
                :style="{ backgroundColor: hexValido ? modelValue : 'transparent' }"
            />
            <TextInput
                :model-value="modelValue"
                maxlength="7"
                placeholder="#22c55e"
                class="w-28 font-mono text-sm"
                @update:model-value="emit('update:modelValue', $event)"
            />
        </div>
    </div>
</template>
