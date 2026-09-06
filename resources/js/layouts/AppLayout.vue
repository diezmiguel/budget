<script setup>
import { ref } from 'vue';
import { RouterView, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const router = useRouter();
const mobileOpen = ref(false);

const nav = [
    { name: 'dashboard', label: 'Painel', icon: 'M3 12l9-9 9 9M4.5 10.5V21h5v-6h5v6h5V10.5' },
    { name: 'expenses', label: 'Despesas', icon: 'M3 6h18M3 12h18M3 18h12' },
    { name: 'bills', label: 'Contas a pagar', icon: 'M9 7h6m-6 4h6m-6 4h4M5 3h14a1 1 0 011 1v16l-3-2-3 2-3-2-3 2V4a1 1 0 011-1z' },
    { name: 'categories', label: 'Categorias', icon: 'M4 6h16M4 12h16M4 18h16' },
];

async function handleLogout() {
    await auth.logout();
    router.push({ name: 'login' });
}
</script>

<template>
    <div class="flex h-full">
        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-30 w-64 transform bg-slate-900 text-slate-200 transition-transform duration-200 lg:static lg:translate-x-0"
            :class="mobileOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex h-16 items-center gap-2 px-6 text-white">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-500 font-bold">O</div>
                <span class="text-lg font-semibold">Orçamento</span>
            </div>

            <nav class="mt-4 space-y-1 px-3">
                <RouterLink
                    v-for="item in nav"
                    :key="item.name"
                    :to="{ name: item.name }"
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition hover:bg-slate-800"
                    active-class="bg-indigo-600 text-white hover:bg-indigo-600"
                    @click="mobileOpen = false"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                    </svg>
                    {{ item.label }}
                </RouterLink>

                <RouterLink
                    v-if="auth.isAdmin"
                    :to="{ name: 'users' }"
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition hover:bg-slate-800"
                    active-class="bg-indigo-600 text-white hover:bg-indigo-600"
                    @click="mobileOpen = false"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-1a4 4 0 00-4-4h-1m-6 5H2v-1a4 4 0 014-4h4a4 4 0 014 4v1zm-2-9a3 3 0 11-6 0 3 3 0 016 0zm7-3a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Utilizadores
                </RouterLink>
            </nav>
        </aside>

        <div
            v-if="mobileOpen"
            class="fixed inset-0 z-20 bg-black/40 lg:hidden"
            @click="mobileOpen = false"
        />

        <!-- Main -->
        <div class="flex flex-1 flex-col overflow-hidden">
            <header class="flex h-16 items-center justify-between border-b border-slate-200 bg-white px-4 lg:px-8">
                <button class="rounded-md p-2 text-slate-600 hover:bg-slate-100 lg:hidden" @click="mobileOpen = true">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <div class="hidden lg:block"></div>

                <div class="flex items-center gap-4">
                    <div class="text-right">
                        <p class="text-sm font-semibold text-slate-800">{{ auth.user?.name }}</p>
                        <p class="text-xs text-slate-500">{{ auth.roleLabel }}</p>
                    </div>
                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-indigo-100 font-semibold text-indigo-700">
                        {{ auth.user?.name?.charAt(0)?.toUpperCase() }}
                    </div>
                    <button
                        class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
                        @click="handleLogout"
                    >
                        Sair
                    </button>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto p-4 lg:p-8">
                <RouterView />
            </main>
        </div>
    </div>
</template>
