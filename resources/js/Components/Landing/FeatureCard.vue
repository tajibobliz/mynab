<script setup>
defineProps({
    icon: {
        type: [Object, Function],
        required: true,
    },
    titulo: {
        type: String,
        required: true,
    },
    descripcion: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <div class="p-6 rounded-xl bg-surface border border-border/50 hover:border-status-success/50 hover:scale-[1.02] transition-all duration-200">
        <span class="inline-flex items-center justify-center size-12 rounded-full bg-status-success/15 mb-5">
            <component :is="icon" class="size-6 text-status-success" />
        </span>

        <h3 class="text-lg font-semibold text-text mb-2">{{ titulo }}</h3>
        <p class="text-text-secondary leading-relaxed">{{ descripcion }}</p>
    </div>
</template>
