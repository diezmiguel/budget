import { defineStore } from 'pinia';
import api, { ensureCsrf } from '../lib/axios';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        loading: false,
        initialized: false,
    }),
    getters: {
        isAuthenticated: (state) => state.user !== null,
        isAdmin: (state) => state.user?.role === 'admin',
        roleLabel: (state) => (state.user?.role === 'admin' ? 'Administrador' : 'Gestor'),
    },
    actions: {
        async fetchUser() {
            try {
                const { data } = await api.get('/api/me');
                this.user = data.user;
            } catch {
                this.user = null;
            } finally {
                this.initialized = true;
            }
        },
        async login(credentials) {
            this.loading = true;
            try {
                await ensureCsrf();
                const { data } = await api.post('/api/login', credentials);
                this.user = data.user;
                return data.user;
            } finally {
                this.loading = false;
            }
        },
        async logout() {
            try {
                await api.post('/api/logout');
            } finally {
                this.user = null;
            }
        },
    },
});
