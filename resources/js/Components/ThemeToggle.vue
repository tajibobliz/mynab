<script>
import { ref } from 'vue';

// Estado compartido entre todas las instancias (navbar desktop + menú responsive).
const isDark = ref(document.documentElement.classList.contains('dark'));
</script>

<script setup>
import { Moon, Sun } from 'lucide-vue-next';

const STORAGE_KEY = 'mynab-theme';

const toggle = () => {
    isDark.value = !isDark.value;
    document.documentElement.classList.toggle('dark', isDark.value);

    try {
        localStorage.setItem(STORAGE_KEY, isDark.value ? 'dark' : 'light');
    } catch (e) {
        // Sin localStorage (modo privado): el tema solo dura mientras la pestaña esté abierta.
    }
};
</script>

<template>
    <button
        type="button"
        class="inline-flex items-center justify-center p-2 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100 dark:text-text-secondary dark:hover:text-text dark:hover:bg-surface-elevated focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-primary transition duration-150 ease-in-out"
        :aria-label="isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'"
        :title="isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'"
        @click="toggle"
    >
        <Sun v-if="isDark" class="size-5" />
        <Moon v-else class="size-5" />
    </button>
</template>
