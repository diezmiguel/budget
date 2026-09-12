<script setup>
defineProps({
    title: { type: String, default: '' },
    show: { type: Boolean, default: false },
});
const emit = defineEmits(['close']);
</script>

<template>
    <Transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0"
        leave-active-class="transition duration-100 ease-in"
        leave-to-class="opacity-0"
    >
        <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50" @click="emit('close')" />
            <div class="relative flex max-h-[90vh] w-full max-w-lg flex-col rounded-2xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-100 p-6 pb-4">
                    <h3 class="text-lg font-semibold text-slate-800">{{ title }}</h3>
                    <button class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" @click="emit('close')">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="overflow-y-auto p-6 pt-4">
                    <slot />
                </div>
            </div>
        </div>
    </Transition>
</template>
