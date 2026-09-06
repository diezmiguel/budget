import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const routes = [
    {
        path: '/login',
        name: 'login',
        component: () => import('../views/LoginView.vue'),
        meta: { guest: true },
    },
    {
        path: '/',
        component: () => import('../layouts/AppLayout.vue'),
        meta: { requiresAuth: true },
        children: [
            { path: '', name: 'dashboard', component: () => import('../views/DashboardView.vue') },
            { path: 'despesas', name: 'expenses', component: () => import('../views/ExpensesView.vue') },
            { path: 'contas', name: 'bills', component: () => import('../views/BillsView.vue') },
            { path: 'categorias', name: 'categories', component: () => import('../views/CategoriesView.vue') },
            {
                path: 'utilizadores',
                name: 'users',
                component: () => import('../views/UsersView.vue'),
                meta: { requiresAdmin: true },
            },
        ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    if (!auth.initialized) {
        await auth.fetchUser();
    }

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'login' };
    }

    if (to.meta.guest && auth.isAuthenticated) {
        return { name: 'dashboard' };
    }

    if (to.meta.requiresAdmin && !auth.isAdmin) {
        return { name: 'dashboard' };
    }

    return true;
});

export default router;
