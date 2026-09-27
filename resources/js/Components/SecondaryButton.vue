<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button :type="type" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-accent-primary focus:ring-offset-2 dark:bg-surface-elevated dark:border-border dark:text-text dark:hover:bg-border dark:focus:ring-offset-surface disabled:opacity-25 transition ease-in-out duration-150">
        <slot />
    </button>
</template>
