<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

const props = defineProps({
    año: {
        type: Number,
        required: true,
    },
    mes: {
        type: Number,
        required: true,
    },
});

const NOMBRES_MES = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
];

const etiqueta = computed(() => `${NOMBRES_MES[props.mes - 1]} ${props.año}`);

const navegar = (añoDestino, mesDestino) => {
    router.get('/presupuesto-mensual', { año: añoDestino, mes: mesDestino }, { preserveScroll: true });
};

const anterior = () => {
    const mesDestino = props.mes === 1 ? 12 : props.mes - 1;
    const añoDestino = props.mes === 1 ? props.año - 1 : props.año;
    navegar(añoDestino, mesDestino);
};

const siguiente = () => {
    const mesDestino = props.mes === 12 ? 1 : props.mes + 1;
    const añoDestino = props.mes === 12 ? props.año + 1 : props.año;
    navegar(añoDestino, mesDestino);
};
</script>

<template>
    <div class="flex items-center justify-center gap-4">
        <button
            type="button"
            class="flex items-center justify-center size-9 rounded-lg border border-border bg-surface text-text-secondary hover:text-text hover:bg-surface-elevated transition duration-150"
            aria-label="Mes anterior"
            @click="anterior"
        >
            <ChevronLeft class="size-4" />
        </button>

        <span class="min-w-40 text-center font-semibold text-text">{{ etiqueta }}</span>

        <button
            type="button"
            class="flex items-center justify-center size-9 rounded-lg border border-border bg-surface text-text-secondary hover:text-text hover:bg-surface-elevated transition duration-150"
            aria-label="Mes siguiente"
            @click="siguiente"
        >
            <ChevronRight class="size-4" />
        </button>
    </div>
</template>
