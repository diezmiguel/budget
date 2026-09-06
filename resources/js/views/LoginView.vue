<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const router = useRouter();

const form = reactive({ email: '', password: '', remember: false });
const error = ref('');

async function submit() {
    error.value = '';
    try {
        await auth.login(form);
        router.push({ name: 'dashboard' });
    } catch (e) {
        const data = e?.response?.data;
        error.value = data?.errors?.email?.[0] || data?.message || 'Não foi possível iniciar sessão.';
    }
}
</script>

<template>
    <div class="flex min-h-full items-center justify-center bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 p-4">
        <div class="w-full max-w-md">
            <div class="mb-8 text-center">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-500 text-2xl font-bold text-white">O</div>
                <h1 class="text-2xl font-semibold text-white">Orçamento</h1>
                <p class="mt-1 text-sm text-slate-400">Gestão de despesas e contas do apartamento</p>
            </div>

            <div class="rounded-2xl bg-white p-8 shadow-2xl">
                <h2 class="mb-6 text-lg font-semibold text-slate-800">Entrar na sua conta</h2>

                <form class="space-y-4" @submit.prevent="submit">
                    <div v-if="error" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ error }}
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">E-mail</label>
                        <input
                            v-model="form.email"
                            type="email"
                            required
                            autocomplete="email"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none"
                            placeholder="voce@exemplo.com"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Palavra-passe</label>
                        <input
                            v-model="form.password"
                            type="password"
                            required
                            autocomplete="current-password"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none"
                            placeholder="••••••••"
                        />
                    </div>

                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input v-model="form.remember" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                        Manter sessão iniciada
                    </label>

                    <button
                        type="submit"
                        :disabled="auth.loading"
                        class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-60"
                    >
                        {{ auth.loading ? 'A entrar...' : 'Entrar' }}
                    </button>
                </form>

                <p class="mt-6 text-center text-xs text-slate-400">
                    As contas são criadas apenas pelo administrador.
                </p>
            </div>
        </div>
    </div>
</template>
