<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Wallet } from 'lucide-vue-next';
import { lucideIconMap } from '@/lucideIconMap';

const props = defineProps({
    presupuesto: {
        type: Object,
        required: true,
    },
    variant: {
        type: String,
        default: 'default', // 'default' (Index) | 'selector' (dropdown navbar)
    },
    // El card no conoce nombres de ruta; quien lo use decide a dónde va el
    // título (normalmente route('presupuestos.show', presupuesto.id)).
    href: {
        type: String,
        default: null,
    },
});

const IconComponent = computed(() => lucideIconMap[props.presupuesto.icono] ?? Wallet);
const badgeBg = computed(() => `${props.presupuesto.color}26`); // ~15% opacity
</script>

<template>
    <div v-if="variant === 'selector'" class="flex items-center gap-2 min-w-0">
        <span
            class="flex items-center justify-center size-6 rounded-full shrink-0"
            :style="{ backgroundColor: badgeBg }"
        >
            <component :is="IconComponent" class="size-3.5" :style="{ color: presupuesto.color }" />
        </span>
        <span class="truncate">
            {{ presupuesto.nombre }}
            <span v-if="presupuesto.moneda_base_codigo" class="font-mono text-xs text-text-secondary">
                · {{ presupuesto.moneda_base_codigo }}
            </span>
        </span>
    </div>

    <div v-else class="rounded-lg border border-border bg-surface p-5 flex flex-col gap-3">
        <div class="flex items-start gap-3">
            <span
                class="flex items-center justify-center size-10 rounded-lg shrink-0"
                :style="{ backgroundColor: badgeBg }"
            >
                <component :is="IconComponent" class="size-5" :style="{ color: presupuesto.color }" />
            </span>

            <div class="min-w-0 flex-1">
                <h3 class="font-semibold text-text truncate">
                    <Link v-if="href" :href="href" class="hover:text-accent-primary transition duration-150">
                        {{ presupuesto.nombre }}
                    </Link>
                    <template v-else>{{ presupuesto.nombre }}</template>
                </h3>
                <p v-if="presupuesto.descripcion" class="text-sm text-text-secondary line-clamp-2 mt-0.5">
                    {{ presupuesto.descripcion }}
                </p>
            </div>
        </div>

        <div v-if="$slots.actions" class="flex items-center gap-3 pt-3 border-t border-border text-sm">
            <slot name="actions" />
        </div>
    </div>
</template>
