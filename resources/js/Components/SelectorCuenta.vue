<script setup>
import { computed } from 'vue';
import { formatearMonto } from '@/utils/formatoMoneda';

const props = defineProps({
    modelValue: {
        type: [Number, String],
        default: '',
    },
    // Array explícito (no leído del share) para poder reusar el mismo
    // componente en cuenta_id y cuenta_destino_id con distinto excluirId,
    // sin que cada instancia repita su propia lógica de filtrado del share.
    cuentas: {
        type: Array,
        required: true,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    placeholder: {
        type: String,
        default: 'Selecciona una cuenta',
    },
    excluirId: {
        type: [Number, String],
        default: null,
    },
});

defineEmits(['update:modelValue']);

const SECCIONES = [
    { tipo: 'banco', label: 'Bancarias' },
    { tipo: 'efectivo', label: 'Efectivo' },
    { tipo: 'wallet', label: 'Wallets' },
];

const cuentasVisibles = computed(() => props.cuentas.filter((c) => c.id !== props.excluirId));

const grupos = computed(() => SECCIONES
    .map((seccion) => ({ ...seccion, cuentas: cuentasVisibles.value.filter((c) => c.tipo === seccion.tipo) }))
    .filter((seccion) => seccion.cuentas.length > 0));
</script>

<template>
    <select
        :value="modelValue"
        :disabled="disabled"
        class="w-full border-gray-300 focus:border-accent-primary focus:ring-accent-primary rounded-md shadow-sm dark:bg-base dark:border-border dark:text-text disabled:opacity-50 disabled:cursor-not-allowed transition duration-150 ease-in-out"
        @change="$emit('update:modelValue', $event.target.value === '' ? '' : Number($event.target.value))"
    >
        <!--
            Sin `disabled`: este option debe seguir siendo seleccionable para
            poder "volver" a él (ej. FiltrosTransacciones.vue necesita poder
            limpiar un filtro individual de vuelta a "todas"). El caso
            required (Create/Edit) lo sigue cubriendo la validación del
            backend. OJO: Number('') === 0 en JS, no '' -- el @change de
            arriba evita a propósito ese caso o este option emitiría 0 en
            vez de "sin selección" en cuanto dejara de estar disabled.
        -->
        <option value="">{{ placeholder }}</option>
        <optgroup v-for="grupo in grupos" :key="grupo.tipo" :label="grupo.label">
            <option v-for="cuenta in grupo.cuentas" :key="cuenta.id" :value="cuenta.id">
                {{ cuenta.nombre }} — {{ formatearMonto(cuenta.saldo_actual_centavos, cuenta.moneda) }}
            </option>
        </optgroup>
    </select>
</template>
