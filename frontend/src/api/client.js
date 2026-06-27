import axios from "axios";

/**
 * PulseDesk API client
 *
 * Uses Laravel Sanctum SPA cookie authentication:
 *   1. `ensureCsrf()` calls /sanctum/csrf-cookie which sets the XSRF-TOKEN cookie.
 *   2. Axios automatically reads that cookie and sends it back as the
 *      `X-XSRF-TOKEN` header on every subsequent request (withXSRFToken: true).
 *   3. The Laravel session cookie authenticates the user.
 *
 * If a bearer token is present in storage (e.g. issued from /api/tokens),
 * it is attached as a fallback Authorization header.
 *
 * NOTE: For SPA cookie auth to work, the frontend and backend must share
 * the same second-level domain (e.g. pulsedesk.test / api.pulsedesk.test)
 * and `SANCTUM_STATEFUL_DOMAINS` must be configured in the Laravel env.
 */

const BASE_URL = import.meta.env.VITE_API_URL || "http://localhost:8000";

const STORAGE_KEYS = {
  user: "pulsedesk_user",
  token: "pulsedesk_token",
};

const client = axios.create({
  baseURL: BASE_URL,
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    "Content-Type": "application/json",
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
  },
});

// -------------------------------------------------------------
// CSRF bootstrap — Sanctum double-submit cookie pattern
// -------------------------------------------------------------
let csrfPromise = null;

export function ensureCsrf() {
  if (!csrfPromise) {
    csrfPromise = client.get("/sanctum/csrf-cookie").catch((err) => {
      csrfPromise = null;
      throw err;
    });
  }
  return csrfPromise;
}

// -------------------------------------------------------------
// Auth helpers (used by AuthContext)
// -------------------------------------------------------------
export function getStoredUser() {
  try {
    const raw = localStorage.getItem(STORAGE_KEYS.user);
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

export function getStoredToken() {
  return localStorage.getItem(STORAGE_KEYS.token);
}

export function persistUser(user, token = null) {
  localStorage.setItem(STORAGE_KEYS.user, JSON.stringify(user));
  if (token) localStorage.setItem(STORAGE_KEYS.token, token);
}

export function clearStoredAuth() {
  localStorage.removeItem(STORAGE_KEYS.user);
  localStorage.removeItem(STORAGE_KEYS.token);
}

// -------------------------------------------------------------
// Request interceptor: attach organization + bearer headers
// -------------------------------------------------------------
client.interceptors.request.use((config) => {
  const user = getStoredUser();
  if (user?.organization_id) {
    // Sent for traceability/logging only — the backend ALWAYS re-derives
    // organization_id from the authenticated user (never trusts the header).
    config.headers["X-Organization-ID"] = user.organization_id;
  }

  const token = getStoredToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

// -------------------------------------------------------------
// Response interceptor: auto-clear auth state on 401
// -------------------------------------------------------------
client.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      clearStoredAuth();
      const path = window.location.pathname;
      if (!["/login", "/register"].includes(path)) {
        window.location.href = "/login";
      }
    }
    return Promise.reject(error);
  }
);

export default client;
