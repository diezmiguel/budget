import axios from 'axios';

const api = axios.create({
    baseURL: '/',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
    },
});

// Ensure the CSRF cookie is set before state-changing requests.
export async function ensureCsrf() {
    await api.get('/sanctum/csrf-cookie');
}

export default api;
