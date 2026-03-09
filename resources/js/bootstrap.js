import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
// Send session cookies with every API request (needed for auth:sanctum,web)
window.axios.defaults.withCredentials = true;

function getCookie(name) {
    const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : null;
}

window.axios.interceptors.request.use((config) => {
    const token = getCookie('XSRF-TOKEN');
    if (token) config.headers['X-XSRF-TOKEN'] = token;
    return config;
}, (error) => Promise.reject(error));
