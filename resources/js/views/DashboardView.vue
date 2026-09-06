<script setup>
import { onMounted, ref } from 'vue';
import api from '../lib/axios';
import { formatCurrency, formatDate, formatMonth } from '../lib/format';

const data = ref(null);
const loading = ref(true);
const error = ref('');

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const res = await api.get('/api/dashboard');
        data.value = res.data;
    } catch {
        error.value = 'Não foi possível carregar o painel.';
    } finally {
        loading.value = false;
    }
}

async function togglePaid(bill) {
    await api.patch(`/api/bills/${bill.id}/pay`, { paid: true });
    await load();
}

const statusStyles = {
    overdue: 'bg-red-100 text-red-700',
    pending: 'bg-amber-100 text-amber-700',
    paid: 'bg-emerald-100 text-emerald-700',
};
const statusLabels = { overdue: 'Vencida', pending: 'Pendente', paid: 'Paga' };

function trendMax() {
    const t = data.value?.expenses?.trend || [];
    return Math.max(1, ...t.map((x) => x.total));
}

onMounted(load);
</script>

<template>
    <div>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-800">Painel</h1>
            <p class="text-sm text-slate-500">Resumo das contas a pagar e das despesas do mês.</p>
        </div>

        <div v-if="loading" class="py-20 text-center text-slate-400">A carregar…</div>
        <div v-else-if="error" class="rounded-lg bg-red-50 p-4 text-red-700">{{ error }}</div>

        <div v-else class="space-y-6">
            <!-- Summary cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <p class="text-sm text-slate-500">Contas vencidas</p>
                    <p class="mt-1 text-2xl font-bold text-red-600">{{ formatCurrency(data.bills.overdue_total) }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ data.bills.overdue_count }} conta(s) em atraso</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <p class="text-sm text-slate-500">A vencer (7 dias)</p>
                    <p class="mt-1 text-2xl font-bold text-amber-600">{{ formatCurrency(data.bills.due_soon_total) }}</p>
                    <p class="mt-1 text-xs text-slate-400">Total pendente: {{ formatCurrency(data.bills.pending_total) }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <p class="text-sm text-slate-500">Despesas do mês</p>
                    <p class="mt-1 text-2xl font-bold text-slate-800">{{ formatCurrency(data.expenses.this_month) }}</p>
                    <p class="mt-1 text-xs text-slate-400">Apartamento: {{ formatCurrency(data.expenses.apartment_this_month) }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <p class="text-sm text-slate-500">Pago este mês</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-600">{{ formatCurrency(data.bills.paid_this_month) }}</p>
                    <p class="mt-1 text-xs text-slate-400">Outros gastos: {{ formatCurrency(data.expenses.other_this_month) }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Upcoming bills -->
                <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h2 class="font-semibold text-slate-800">Contas a pagar</h2>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <p v-if="!data.bills.upcoming.length" class="px-5 py-10 text-center text-sm text-slate-400">
                            Nenhuma conta pendente. 🎉
                        </p>
                        <div v-for="bill in data.bills.upcoming" :key="bill.id" class="flex items-center justify-between gap-4 px-5 py-3.5">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="inline-block h-2.5 w-2.5 flex-shrink-0 rounded-full"
                                        :style="{ backgroundColor: bill.category_color || '#94a3b8' }"
                                    />
                                    <p class="truncate font-medium text-slate-800">{{ bill.name }}</p>
                                </div>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    Vence em {{ formatDate(bill.due_date) }}
                                    <span v-if="bill.owner_label"> · {{ bill.owner_label }}</span>
                                    <span v-else-if="bill.responsible_user"> · {{ bill.responsible_user }}</span>
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statusStyles[bill.effective_status]">
                                    {{ statusLabels[bill.effective_status] }}
                                </span>
                                <span class="w-24 text-right font-semibold text-slate-800">{{ formatCurrency(bill.amount) }}</span>
                                <button
                                    class="rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-100"
                                    @click="togglePaid(bill)"
                                >
                                    Pagar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Expenses by category -->
                <div class="rounded-2xl border border-slate-200 bg-white">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h2 class="font-semibold text-slate-800">Despesas por categoria</h2>
                        <p class="text-xs text-slate-400">Mês atual</p>
                    </div>
                    <div class="space-y-3 p-5">
                        <p v-if="!data.expenses.by_category.length" class="py-6 text-center text-sm text-slate-400">
                            Sem despesas este mês.
                        </p>
                        <div v-for="cat in data.expenses.by_category" :key="cat.category">
                            <div class="mb-1 flex items-center justify-between text-sm">
                                <span class="text-slate-600">{{ cat.category }}</span>
                                <span class="font-medium text-slate-800">{{ formatCurrency(cat.total) }}</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                <div
                                    class="h-full rounded-full"
                                    :style="{
                                        width: (cat.total / (data.expenses.by_category[0]?.total || 1)) * 100 + '%',
                                        backgroundColor: cat.color,
                                    }"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Trend -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <h2 class="mb-4 font-semibold text-slate-800">Evolução das despesas (6 meses)</h2>
                <div class="flex h-48 items-end gap-3">
                    <div v-for="point in data.expenses.trend" :key="point.month" class="flex flex-1 flex-col items-center gap-2">
                        <div class="flex w-full flex-1 items-end">
                            <div
                                class="w-full rounded-t-lg bg-indigo-500 transition-all"
                                :style="{ height: (point.total / trendMax()) * 100 + '%' }"
                                :title="formatCurrency(point.total)"
                            />
                        </div>
                        <span class="text-xs text-slate-400">{{ formatMonth(point.month) }}</span>
                    </div>
                    <p v-if="!data.expenses.trend.length" class="w-full py-10 text-center text-sm text-slate-400">
                        Sem dados suficientes.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
