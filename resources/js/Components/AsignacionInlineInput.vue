<script setup>
import { nextTick, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { centavosDesdeMonto, formatearMonto } from '@/utils/formatoMoneda';

const props = defineProps({
    categoriaId: {
        type: Number,
        required: true,
    },
    año: {
        type: Number,
        required: true,
    },
    mes: {
        type: Number,
        required: true,
    },
    montoCentavos: {
        type: Number,
        required: true,
    },
    // Objeto {simbolo, decimales}, no solo el código: formatearMonto() y
    // centavosDesdeMonto() necesitan decimales (y simbolo el primero), un
    // string de código por sí solo no alcanza para ninguna de las dos.
    moneda: {
        type: Object,
        required: true,
    },
});

const editando = ref(false);
const guardando = ref(false);
const valorLocal = ref('');
const inputRef = ref(null);

// Si el padre recibe una vista nueva (tras guardar o tras navegar de mes),
// sincroniza el valor local — evita mostrar un valor viejo si el usuario
// vuelve a entrar en modo edición.
watch(() => props.montoCentavos, () => {
    if (! editando.value) {
        return;
    }
    valorLocal.value = String(props.montoCentavos / 10 ** props.moneda.decimales);
});

const entrarEdicion = async () => {
    valorLocal.value = String(props.montoCentavos / 10 ** props.moneda.decimales);
    editando.value = true;
    await nextTick();
    inputRef.value?.focus();
    inputRef.value?.select();
};

const cancelar = () => {
    editando.value = false;
};

const guardar = () => {
    // Guard contra doble submit: Enter dispara guardar() y, dependiendo del
    // navegador, el blur subsiguiente del input también — sin esto, el
    // segundo guardar() reentraría con guardando ya en curso.
    if (guardando.value) {
        return;
    }

    const nuevoMontoCentavos = centavosDesdeMonto(valorLocal.value, props.moneda.decimales);

    if (nuevoMontoCentavos === props.montoCentavos) {
        editando.value = false;
        return;
    }

    guardando.value = true;

    router.post(route('asignaciones.store'), {
        categoria_id: props.categoriaId,
        año: props.año,
        mes: props.mes,
        monto_centavos: nuevoMontoCentavos,
    }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            guardando.value = false;
            editando.value = false;
        },
    });
};
</script>

<template>
    <button
        v-if="! editando"
        type="button"
        class="w-full text-right font-mono text-sm text-text hover:text-accent-primary transition duration-150 px-2 py-1 rounded"
        @click="entrarEdicion"
    >
        {{ formatearMonto(montoCentavos, moneda) }}
    </button>

    <input
        v-else
        ref="inputRef"
        v-model="valorLocal"
        type="number"
        step="0.01"
        :disabled="guardando"
        class="w-full text-right font-mono text-sm rounded-md border-border bg-surface-elevated text-text focus:border-accent-primary focus:ring-accent-primary disabled:opacity-50"
        @blur="guardar"
        @keydown.enter="guardar"
        @keydown.escape="cancelar"
    >
</template>
