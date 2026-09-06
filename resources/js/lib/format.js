const currencyFormatter = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
});

export function formatCurrency(value) {
    const number = Number(value ?? 0);
    return currencyFormatter.format(Number.isFinite(number) ? number : 0);
}

export function formatDate(value) {
    if (!value) return '—';
    const date = typeof value === 'string' ? new Date(value + 'T00:00:00') : new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return new Intl.DateTimeFormat('pt-BR').format(date);
}

export function formatMonth(ym) {
    if (!ym) return '';
    const [year, month] = ym.split('-');
    const date = new Date(Number(year), Number(month) - 1, 1);
    return new Intl.DateTimeFormat('pt-BR', { month: 'short', year: '2-digit' }).format(date);
}

// Today as YYYY-MM-DD (local).
export function today() {
    const d = new Date();
    const offset = d.getTimezoneOffset();
    const local = new Date(d.getTime() - offset * 60000);
    return local.toISOString().slice(0, 10);
}
