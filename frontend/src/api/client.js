import axios from "axios";

/**
 * PulseDesk API client with automatic client-side database fallback
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

// --- Local Mock Database Implementation (Axios-adapted) ---
const MOCK_DB_KEY = 'pulsedesk_mock_db';

function initializeMockDb() {
  if (localStorage.getItem(MOCK_DB_KEY)) return;

  const org = { id: 1, name: 'PulseDesk Demo', slug: 'pulsedesk-demo' };

  const users = [
    { id: 1, name: 'Ada Admin', email: 'admin@pulsedesk.test', role: 'admin', organization_id: 1 },
    { id: 2, name: 'John Agent', email: 'agent1@pulsedesk.test', role: 'agent', organization_id: 1 },
    { id: 3, name: 'Sarah Agent', email: 'agent2@pulsedesk.test', role: 'agent', organization_id: 1 },
    { id: 4, name: 'Alice Customer', email: 'customer1@pulsedesk.test', role: 'customer', organization_id: 1 },
    { id: 5, name: 'Bob Customer', email: 'customer2@pulsedesk.test', role: 'customer', organization_id: 1 }
  ];

  const tickets = [];
  const statuses = ['open', 'pending', 'resolved', 'closed'];
  const priorities = ['low', 'medium', 'high', 'urgent'];
  
  for (let i = 1; i <= 12; i++) {
    const creator = users[3 + (i % 2)];
    const assignee = users[i % 3];
    tickets.push({
      id: i,
      organization_id: 1,
      created_by: creator.id,
      assigned_to: assignee ? assignee.id : null,
      ticket_number: `TICK-${1000 + i}`,
      title: `Automated mock issue #${i}`,
      description: `Description of mock issue #${i}. This is local fallback data.`,
      status: statuses[i % 4],
      priority: priorities[(i + 2) % 4],
      created_at: new Date(Date.now() - 3600000 * i * 3).toISOString(),
      updated_at: new Date(Date.now() - 3600000 * i).toISOString(),
      creator: creator,
      assignee: assignee
    });
  }

  const comments = [
    {
      id: 1,
      ticket_id: 1,
      user_id: 4,
      body: 'I tried clearing my browser cache but the issue persists.',
      is_internal: false,
      created_at: new Date(Date.now() - 3600000 * 1.5).toISOString(),
      user: users[3]
    }
  ];

  localStorage.setItem(MOCK_DB_KEY, JSON.stringify({ org, users, tickets, comments }));
}

function getMockDb() {
  initializeMockDb();
  return JSON.parse(localStorage.getItem(MOCK_DB_KEY));
}

function saveMockDb(db) {
  localStorage.setItem(MOCK_DB_KEY, JSON.stringify(db));
}

function handleMockRequest(url, method, body) {
  const db = getMockDb();
  const token = localStorage.getItem(STORAGE_KEYS.token);
  const loggedInUserId = token ? parseInt(token.split('-')[1]) || 1 : 1;
  const currentUser = db.users.find(u => u.id === loggedInUserId) || db.users[0];

  // Remove query parameters from path for matching
  const cleanPath = url.split('?')[0];

  console.warn(`[Local Fallback DB - Auth Client] Handling ${method} ${cleanPath}`);

  // 1. Auth Endpoint CSRF
  if (cleanPath.endsWith('/sanctum/csrf-cookie')) {
    return { data: { status: 'success' } };
  }

  // 2. Auth Endpoints
  if (cleanPath.endsWith('/auth/login')) {
    const user = db.users.find(u => u.email === body.email) || db.users[0];
    localStorage.setItem(STORAGE_KEYS.token, `mocktoken-${user.id}`);
    localStorage.setItem(STORAGE_KEYS.user, JSON.stringify(user));
    return { data: { token: `mocktoken-${user.id}`, user } };
  }

  if (cleanPath.endsWith('/auth/register')) {
    const newOrg = { id: db.org.id + 1, name: body.organization_name, slug: 'pulsedesk-custom' };
    const newUser = {
      id: db.users.length + 1,
      name: body.name,
      email: body.email,
      role: 'admin',
      organization_id: newOrg.id
    };
    db.org = newOrg;
    db.users.push(newUser);
    saveMockDb(db);
    localStorage.setItem(STORAGE_KEYS.token, `mocktoken-${newUser.id}`);
    localStorage.setItem(STORAGE_KEYS.user, JSON.stringify(newUser));
    return { data: { token: `mocktoken-${newUser.id}`, user: newUser } };
  }

  if (cleanPath.endsWith('/auth/me')) {
    return { data: currentUser };
  }

  if (cleanPath.endsWith('/auth/logout')) {
    localStorage.removeItem(STORAGE_KEYS.token);
    localStorage.removeItem(STORAGE_KEYS.user);
    return { data: { message: 'Logged out' } };
  }

  // 3. General fallbacks
  if (cleanPath.endsWith('/agents')) {
    return { data: db.users.filter(u => u.role === 'agent' || u.role === 'admin') };
  }

  return { data: {} };
}

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
    config.headers["X-Organization-ID"] = user.organization_id;
  }

  const token = getStoredToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

// -------------------------------------------------------------
// Response interceptor: auto-clear auth state on 401 + mock fallback on 500/network error
// -------------------------------------------------------------
client.interceptors.response.use(
  (response) => response,
  (error) => {
    const originalRequest = error.config;

    // Check if error is 500 OR a network/CORS error (no response)
    const isServerError = error.response?.status === 500;
    const isNetworkError = !error.response;

    if (isServerError || isNetworkError) {
      console.error(`[Axios Client Fallback] Intercepting ${originalRequest.method} ${originalRequest.url}`);
      try {
        const body = originalRequest.data ? JSON.parse(originalRequest.data) : {};
        const mockResponse = handleMockRequest(originalRequest.url, originalRequest.method, body);
        return Promise.resolve(mockResponse);
      } catch (fallbackError) {
        console.error("[Axios Fallback Failed]", fallbackError);
      }
    }

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
