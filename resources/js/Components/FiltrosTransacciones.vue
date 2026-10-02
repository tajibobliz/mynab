<script setup>
import { reactive, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import SelectorBeneficiario from '@/Components/SelectorBeneficiario.vue';
import SelectorCategoria from '@/Components/SelectorCategoria.vue';
import SelectorCuenta from '@/Components/SelectorCuenta.vue';

const props = defineProps({
    // { tipo, cuenta, categoria, beneficiario, desde, hasta }
    modelValue: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['update:modelValue']);

const TIPOS = [
    { value: 'outflow', label: 'Gasto' },
    { value: 'inflow', label: 'Ingreso' },
    { value: 'transfer', label: 'Transferencia' },
];

const filtros = reactive({ ...props.modelValue });
const cuentas = usePage().props.cuentasDelPresupuestoActivo;

// Debounce de 300ms: evita un dispatch (y su correspondiente visita Inertia)
// por cada tecla en los inputs de fecha o cada cambio rápido de selects.
let temporizador = null;
watch(filtros, () => {
    clearTimeout(temporizador);
    temporizador = setTimeout(() => {
        emit('update:modelValue', { ...filtros });
    }, 300);
}, { deep: true });

const limpiar = () => {
    clearTimeout(temporizador);
    filtros.tipo = '';
    filtros.cuenta = '';
    filtros.categoria = '';
    filtros.beneficiario = null;
    filtros.desde = '';
    filtros.hasta = '';
    emit('update:modelValue', { ...filtros });
};
</script>

<template>
    <div class="space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <select
                v-model="filtros.tipo"
                class="w-full border-gray-300 focus:border-accent-primary focus:ring-accent-primary rounded-md shadow-sm dark:bg-base dark:border-border dark:text-text transition duration-150 ease-in-out"
            >
                <option value="">Todos los tipos</option>
                <option v-for="tipo in TIPOS" :key="tipo.value" :value="tipo.value">{{ tipo.label }}</option>
            </select>

            <SelectorCuenta v-model="filtros.cuenta" :cuentas="cuentas" placeholder="Todas las cuentas" />
            <SelectorCategoria v-model="filtros.categoria" placeholder="Todas las categorías" />
            <SelectorBeneficiario v-model="filtros.beneficiario" placeholder="Todos los beneficiarios" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <input
                v-model="filtros.desde"
                type="date"
                class="w-full border-gray-300 focus:border-accent-primary focus:ring-accent-primary rounded-md shadow-sm dark:bg-base dark:border-border dark:text-text transition duration-150 ease-in-out"
            >
            <input
                v-model="filtros.hasta"
                type="date"
                class="w-full border-gray-300 focus:border-accent-primary focus:ring-accent-primary rounded-md shadow-sm dark:bg-base dark:border-border dark:text-text transition duration-150 ease-in-out"
            >
            <button
                type="button"
                class="px-4 py-2 text-sm text-text-secondary hover:text-text border border-gray-300 dark:border-border rounded-md transition duration-150 ease-in-out"
                @click="limpiar"
            >
                Limpiar filtros
            </button>
        </div>
    </div>
</template>
