<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import MontoCalculadora from '@/Components/MontoCalculadora.vue';
import SelectorCategoria from '@/Components/SelectorCategoria.vue';
import TextInput from '@/Components/TextInput.vue';
import { formatearMonto } from '@/utils/formatoMoneda';

const props = defineProps({
    modelValue: {
        type: Array,
        required: true,
    },
    montoTotalCentavos: {
        type: Number,
        default: 0,
    },
    monedaCodigo: {
        type: String,
        required: true,
    },
});

const emit = defineEmits(['update:modelValue']);

const MINIMO_LINEAS = 2;

const actualizarLinea = (index, campo, valor) => {
    const nuevas = props.modelValue.map((linea, i) => (i === index ? { ...linea, [campo]: valor } : linea));
    emit('update:modelValue', nuevas);
};

const agregarLinea = () => {
    emit('update:modelValue', [...props.modelValue, { categoria_id: '', monto_centavos: null, notas: '' }]);
};

const eliminarLinea = (index) => {
    if (props.modelValue.length <= MINIMO_LINEAS) {
        return;
    }
    emit('update:modelValue', props.modelValue.filter((_, i) => i !== index));
};

const moneda = computed(() => usePage().props.monedasActivas.find((m) => m.codigo === props.monedaCodigo) ?? null);

const sumaLineas = computed(() => props.modelValue.reduce((acc, linea) => acc + (linea.monto_centavos || 0), 0));
const cuadra = computed(() => sumaLineas.value === props.montoTotalCentavos);
const diferencia = computed(() => Math.abs(props.montoTotalCentavos - sumaLineas.value));

const formatear = (centavos) => (moneda.value ? formatearMonto(centavos, moneda.value) : String(centavos));
</script>

<template>
    <div class="space-y-3">
        <div
            v-for="(linea, index) in modelValue"
            :key="index"
            class="p-3 rounded-md border border-gray-200 dark:border-border space-y-2"
        >
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-text-secondary uppercase tracking-wide">Línea {{ index + 1 }}</span>
                <button
                    type="button"
                    class="text-xs text-status-danger hover:text-red-400 disabled:opacity-30 disabled:cursor-not-allowed transition duration-150"
                    :disabled="modelValue.length <= MINIMO_LINEAS"
                    @click="eliminarLinea(index)"
                >
                    Eliminar
                </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <SelectorCategoria
                    :model-value="linea.categoria_id"
                    placeholder="Categoría"
                    @update:model-value="actualizarLinea(index, 'categoria_id', $event)"
                />
                <MontoCalculadora
                    :model-value="linea.monto_centavos"
                    :moneda-codigo="monedaCodigo"
                    placeholder="Monto de la línea"
                    @update:model-value="actualizarLinea(index, 'monto_centavos', $event)"
                />
            </div>

            <TextInput
                :model-value="linea.notas"
                type="text"
                placeholder="Nota (opcional)"
                class="w-full text-sm"
                @update:model-value="actualizarLinea(index, 'notas', $event)"
            />
        </div>

        <button
            type="button"
            class="inline-flex items-center gap-1.5 text-sm text-accent-primary hover:text-green-400 font-medium transition duration-150"
            @click="agregarLinea"
        >
            <Plus class="size-4" />
            Agregar línea
        </button>

        <p class="text-sm font-mono transition duration-150" :class="cuadra ? 'text-status-success' : 'text-status-danger'">
            Suma: {{ formatear(sumaLineas) }} | Total: {{ formatear(montoTotalCentavos) }}
            <span v-if="cuadra">✓</span>
            <span v-else>✗ Diferencia {{ formatear(diferencia) }}</span>
        </p>
    </div>
</template>
