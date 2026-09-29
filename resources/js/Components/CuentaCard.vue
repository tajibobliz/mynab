<script setup>
import { computed } from 'vue';
import { Landmark } from 'lucide-vue-next';
import { tipoCuentaIconPorValor } from '@/tipoCuentaIconMap';
import { formatearMonto } from '@/utils/formatoMoneda';

const props = defineProps({
    cuenta: {
        type: Object,
        required: true,
    },
    variant: {
        type: String,
        default: 'default', // 'default' (Index) | 'compact' (dropdowns futuros, Change 7)
    },
});

const IconComponent = computed(() => tipoCuentaIconPorValor[props.cuenta.tipo] ?? Landmark);
const saldoFormateado = computed(() => formatearMonto(props.cuenta.saldo_actual_centavos, props.cuenta.moneda));
</script>

<template>
    <div v-if="variant === 'compact'" class="flex items-center gap-2 min-w-0">
        <component :is="IconComponent" class="size-4 text-text-secondary shrink-0" />
        <span class="truncate">{{ cuenta.nombre }}</span>
        <span class="ms-auto font-mono text-sm text-text-secondary shrink-0">{{ saldoFormateado }}</span>
    </div>

    <div v-else class="rounded-lg border border-border bg-surface p-5 flex flex-col gap-3">
        <div class="flex items-start gap-3">
            <span class="flex items-center justify-center size-10 rounded-lg shrink-0 bg-accent-primary/10">
                <component :is="IconComponent" class="size-5 text-accent-primary" />
            </span>

            <div class="min-w-0 flex-1">
                <h3 class="font-semibold text-text truncate">{{ cuenta.nombre }}</h3>
                <p v-if="cuenta.numero_referencia" class="text-xs text-text-secondary truncate mt-0.5">
                    {{ cuenta.numero_referencia }}
                </p>
            </div>

            <span class="text-xs font-mono text-text-secondary shrink-0 mt-1">{{ cuenta.moneda_codigo }}</span>
        </div>

        <p class="font-mono text-xl font-semibold text-text">{{ saldoFormateado }}</p>

        <div v-if="$slots.actions" class="flex items-center gap-3 pt-3 border-t border-border text-sm">
            <slot name="actions" />
        </div>
    </div>
</template>
