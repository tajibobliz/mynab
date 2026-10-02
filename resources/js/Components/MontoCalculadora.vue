<script setup>
import { computed, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { evaluarExpresion } from '@/utils/evaluarExpresion';
import { formatearMonto } from '@/utils/formatoMoneda';

const props = defineProps({
    modelValue: {
        type: Number,
        default: null,
    },
    monedaCodigo: {
        type: String,
        required: true,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    placeholder: {
        type: String,
        default: '0.00 o 50+30*2',
    },
    id: {
        type: String,
        default: undefined,
    },
});

const emit = defineEmits(['update:modelValue', 'update:valido']);

const moneda = computed(() => usePage().props.monedasActivas.find((m) => m.codigo === props.monedaCodigo) ?? null);
const decimales = computed(() => moneda.value?.decimales ?? 2);

const expresion = ref('');
const invalida = ref(false);
const valorCalculado = ref(null); // en unidades (ej. 110.00), no en centavos

onMounted(() => {
    if (props.modelValue === null || props.modelValue === undefined) {
        return;
    }

    const unidades = props.modelValue / 10 ** decimales.value;
    expresion.value = unidades.toFixed(decimales.value);
    valorCalculado.value = unidades;
});

const evaluar = () => {
    if (expresion.value.trim() === '') {
        invalida.value = false;
        valorCalculado.value = null;
        emit('update:modelValue', null);
        emit('update:valido', true);
        return;
    }

    const resultado = evaluarExpresion(expresion.value);

    if (!resultado.ok) {
        invalida.value = true;
        valorCalculado.value = null;
        emit('update:valido', false);
        return;
    }

    invalida.value = false;
    valorCalculado.value = resultado.valor;
    emit('update:modelValue', Math.round(resultado.valor * 10 ** decimales.value));
    emit('update:valido', true);
};

const previewFormateado = computed(() => {
    if (valorCalculado.value === null || ! moneda.value) {
        return null;
    }

    return formatearMonto(Math.round(valorCalculado.value * 10 ** decimales.value), moneda.value);
});
</script>

<template>
    <div>
        <input
            :id="id"
            v-model="expresion"
            type="text"
            :disabled="disabled"
            :placeholder="placeholder"
            class="w-full rounded-md shadow-sm font-mono dark:bg-base dark:text-text dark:placeholder-text-secondary disabled:opacity-50 disabled:cursor-not-allowed transition duration-150 ease-in-out"
            :class="invalida
                ? 'border-status-danger focus:border-status-danger focus:ring-status-danger'
                : 'border-gray-300 dark:border-border focus:border-accent-primary focus:ring-accent-primary'"
            @blur="evaluar"
        >

        <p v-if="invalida" class="mt-1 text-sm text-status-danger transition duration-150 ease-in-out">
            Expresión inválida
        </p>
        <p v-else-if="previewFormateado" class="mt-1 text-sm text-text-secondary font-mono transition duration-150 ease-in-out">
            = {{ previewFormateado }}
        </p>
    </div>
</template>
