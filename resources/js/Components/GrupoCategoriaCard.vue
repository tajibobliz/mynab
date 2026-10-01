<script setup>
import { computed } from 'vue';
import { AlertCircle } from 'lucide-vue-next';
import { grupoCategoriaIconMap } from '@/grupoCategoriaIconMap';

const props = defineProps({
    grupo: {
        type: Object,
        required: true,
    },
});

const IconComponent = computed(() => grupoCategoriaIconMap[props.grupo.icono] ?? AlertCircle);
const headerBg = computed(() => `${props.grupo.color}1a`); // ~10% opacity
const badgeBg = computed(() => `${props.grupo.color}26`); // ~15% opacity
</script>

<template>
    <div
        class="rounded-lg border border-border bg-surface overflow-hidden"
        style="border-left-width: 4px"
        :style="{ borderLeftColor: grupo.color }"
    >
        <div class="flex items-center gap-3 p-4" :style="{ backgroundColor: headerBg }">
            <span class="flex items-center justify-center size-9 rounded-lg shrink-0" :style="{ backgroundColor: badgeBg }">
                <component :is="IconComponent" class="size-5" :style="{ color: grupo.color }" />
            </span>

            <h3 class="font-semibold text-text flex-1 truncate">{{ grupo.nombre }}</h3>

            <div v-if="$slots.actions" class="flex items-center gap-3 text-sm shrink-0">
                <slot name="actions" />
            </div>
        </div>

        <div class="p-4">
            <slot name="categorias" />
        </div>
    </div>
</template>
