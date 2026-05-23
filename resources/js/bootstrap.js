import axios from 'axios';
window.axios = axios;
import './echo';

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

window.axios.interceptors.response.use(
	response => response,
	error => {
		const status = error?.response?.status;

		if (status === 401 || status === 419) {
			const supportedLocales = ['en', 'de', 'fr', 'ja'];
			const segments = window.location.pathname.split('/').filter(Boolean);
			const localePrefix = supportedLocales.includes(segments[0]) ? `/${segments[0]}` : '';

			window.location.assign(`${localePrefix}/auth/login`);
		}

		return Promise.reject(error);
	}
);
