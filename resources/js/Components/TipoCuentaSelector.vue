<script setup>
import { computed } from 'vue';
import { ChevronDown } from 'lucide-vue-next';
import Dropdown from '@/Components/Dropdown.vue';
import { tipoCuentaIconMap } from '@/tipoCuentaIconMap';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    // [{ value, label, icono }, ...] — viene de TipoCuenta::opciones() vía el
    // controller (create()/edit()), no del share global.
    tiposCuenta: {
        type: Array,
        required: true,
    },
});

defineEmits(['update:modelValue']);

const seleccionada = computed(() => props.tiposCuenta.find((t) => t.value === props.modelValue) ?? null);
</script>

<template>
    <Dropdown align="left" width="48">
        <template #trigger>
            <button
                type="button"
                class="w-full inline-flex items-center justify-between gap-2 px-3 py-2 border border-gray-300 dark:border-border rounded-md bg-white dark:bg-base text-sm text-gray-700 dark:text-text transition duration-150"
            >
                <span class="inline-flex items-center gap-2 truncate">
                    <component :is="tipoCuentaIconMap[seleccionada.icono]" v-if="seleccionada" class="size-4 text-text-secondary shrink-0" />
                    <span class="truncate">{{ seleccionada ? seleccionada.label : 'Selecciona un tipo' }}</span>
                </span>
                <ChevronDown class="size-4 text-text-secondary shrink-0" />
            </button>
        </template>

        <template #content>
            <div class="w-48 py-1">
                <button
                    v-for="opcion in tiposCuenta"
                    :key="opcion.value"
                    type="button"
                    class="w-full flex items-center gap-2 px-4 py-2 text-sm text-start text-gray-700 dark:text-text hover:bg-gray-100 dark:hover:bg-border transition duration-150"
                    @click="$emit('update:modelValue', opcion.value)"
                >
                    <component :is="tipoCuentaIconMap[opcion.icono]" class="size-4 text-text-secondary shrink-0" />
                    {{ opcion.label }}
                </button>
            </div>
        </template>
    </Dropdown>
</template>
