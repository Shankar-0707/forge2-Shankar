const BASE_URL = import.meta.env.VITE_API_URL || '/api';

function buildQueryString(params = {}) {
  const entries = Object.entries(params).filter(
    ([, v]) => v !== null && v !== undefined && v !== ''
  );
  if (entries.length === 0) return '';
  return '?' + entries
    .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`)
    .join('&');
}

async function request(path, options = {}) {
  const token = localStorage.getItem('auth_token');

  const response = await fetch(`${BASE_URL}${path}`, {
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...options.headers,
    },
    ...options,
  });

  if (response.status === 401) {
    localStorage.removeItem('auth_token');
    window.location.href = '/login';
    throw new Error('Unauthorized');
  }

  if (!response.ok) {
    const error = await response.json().catch(() => ({ message: response.statusText }));
    throw new Error(error.message || 'Request failed');
  }

  return response.status === 204 ? null : response.json();
}

export const api = {
  get(path, params = {}) {
    return request(path + buildQueryString(params), { method: 'GET' });
  },
  post(path, data = {}) {
    return request(path, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },
  put(path, data = {}) {
    return request(path, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  },
  patch(path, data = {}) {
    return request(path, {
      method: 'PATCH',
      body: JSON.stringify(data),
    });
  },
  delete(path) {
    return request(path, { method: 'DELETE' });
  },
};
