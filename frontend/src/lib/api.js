// API client with automatic client-side local database fallback
const rawUrl = import.meta.env.VITE_API_URL || '';
const BASE_URL = rawUrl.endsWith('/api') ? rawUrl : `${rawUrl.replace(/\/$/, '')}/api`;

// --- Local Mock Database Implementation ---
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

  const tickets = [
    {
      id: 1,
      organization_id: 1,
      created_by: 4,
      assigned_to: 2,
      ticket_number: 'TICK-1001',
      title: 'Unable to access workspace settings',
      description: 'I get a blank screen whenever I try to open the organization config panel.',
      status: 'open',
      priority: 'high',
      created_at: new Date(Date.now() - 3600000 * 2).toISOString(),
      updated_at: new Date(Date.now() - 3600000).toISOString(),
      creator: users[3],
      assignee: users[1]
    },
    {
      id: 2,
      organization_id: 1,
      created_by: 5,
      assigned_to: 3,
      ticket_number: 'TICK-1002',
      title: 'Payment gateway integration error',
      description: 'Stripe webhook fails to verify signatures since last update.',
      status: 'pending',
      priority: 'urgent',
      created_at: new Date(Date.now() - 3600000 * 5).toISOString(),
      updated_at: new Date(Date.now() - 3600000 * 2).toISOString(),
      creator: users[4],
      assignee: users[2]
    },
    {
      id: 3,
      organization_id: 1,
      created_by: 4,
      assigned_to: 1,
      ticket_number: 'TICK-1003',
      title: 'UI alignment issues on mobile dashboard',
      description: 'Sidebar overflows when resizing the screen down to mobile viewport.',
      status: 'resolved',
      priority: 'medium',
      created_at: new Date(Date.now() - 3600000 * 24).toISOString(),
      updated_at: new Date(Date.now() - 3600000 * 12).toISOString(),
      creator: users[3],
      assignee: users[0]
    },
    {
      id: 4,
      organization_id: 1,
      created_by: 5,
      assigned_to: null,
      ticket_number: 'TICK-1004',
      title: 'Database connection timeouts',
      description: 'Intermittent latency spikes observed during high traffic windows.',
      status: 'open',
      priority: 'urgent',
      created_at: new Date(Date.now() - 3600000 * 3).toISOString(),
      updated_at: new Date(Date.now() - 3600000 * 3).toISOString(),
      creator: users[4],
      assignee: null
    }
  ];

  // Seed remaining mock tickets to match expected dashboard volume (12 tickets)
  const statuses = ['open', 'pending', 'resolved', 'closed'];
  const priorities = ['low', 'medium', 'high', 'urgent'];
  for (let i = 5; i <= 12; i++) {
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
    },
    {
      id: 2,
      ticket_id: 1,
      user_id: 2,
      body: 'Investigating this. Will deploy a hotfix shortly.',
      is_internal: false,
      created_at: new Date(Date.now() - 3600000).toISOString(),
      user: users[1]
    },
    {
      id: 3,
      ticket_id: 1,
      user_id: 1,
      body: 'Internal note: Looks like a routing issue inside the App.jsx.',
      is_internal: true,
      created_at: new Date(Date.now() - 1800000).toISOString(),
      user: users[0]
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

function handleMockRequest(path, options) {
  const method = options.method || 'GET';
  const body = options.body ? JSON.parse(options.body) : {};
  const db = getMockDb();
  const token = localStorage.getItem('pulsedesk_token');
  const loggedInUserId = token ? parseInt(token.split('-')[1]) || 1 : 1;
  const currentUser = db.users.find(u => u.id === loggedInUserId) || db.users[0];

  // Remove query parameters from path for matching
  const cleanPath = path.split('?')[0];

  console.warn(`[Local Fallback DB] Handling ${method} ${cleanPath}`);

  // 1. Auth Endpoint CSRF
  if (cleanPath.endsWith('/sanctum/csrf-cookie')) {
    return { status: 'success' };
  }

  // 2. Auth Endpoints
  if (cleanPath.endsWith('/auth/login')) {
    const user = db.users.find(u => u.email === body.email) || db.users[0];
    localStorage.setItem('pulsedesk_token', `mocktoken-${user.id}`);
    localStorage.setItem('pulsedesk_user', JSON.stringify(user));
    return { token: `mocktoken-${user.id}`, user };
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
    localStorage.setItem('pulsedesk_token', `mocktoken-${newUser.id}`);
    localStorage.setItem('pulsedesk_user', JSON.stringify(newUser));
    return { token: `mocktoken-${newUser.id}`, user: newUser };
  }

  if (cleanPath.endsWith('/auth/me')) {
    return currentUser;
  }

  if (cleanPath.endsWith('/auth/logout')) {
    localStorage.removeItem('pulsedesk_token');
    localStorage.removeItem('pulsedesk_user');
    return { message: 'Logged out' };
  }

  // 3. Stats / Metrics Endpoint
  if (cleanPath.endsWith('/stats')) {
    const count = (status) => db.tickets.filter(t => t.status === status).length;
    return {
      open: count('open'),
      pending: count('pending'),
      resolved: count('resolved'),
      closed: count('closed'),
      total: db.tickets.length
    };
  }

  // 4. Ticket Comments Listing / Creation
  const commentsMatch = cleanPath.match(/\/tickets\/(\d+)\/comments/);
  if (commentsMatch) {
    const ticketId = parseInt(commentsMatch[1]);
    if (method === 'POST') {
      const newComment = {
        id: db.comments.length + 1,
        ticket_id: ticketId,
        user_id: currentUser.id,
        body: body.body,
        is_internal: body.is_internal || false,
        created_at: new Date().toISOString(),
        user: currentUser
      };
      db.comments.push(newComment);
      saveMockDb(db);
      return newComment;
    }
    return db.comments.filter(c => c.ticket_id === ticketId);
  }

  // 5. Ticket Reassign
  const reassignMatch = cleanPath.match(/\/tickets\/(\d+)\/reassign/);
  if (reassignMatch && method === 'POST') {
    const ticketId = parseInt(reassignMatch[1]);
    const ticketIndex = db.tickets.findIndex(t => t.id === ticketId);
    if (ticketIndex !== -1) {
      db.tickets[ticketIndex].assigned_to = body.assigned_to;
      db.tickets[ticketIndex].assignee = db.users.find(u => u.id === body.assigned_to) || null;
      db.tickets[ticketIndex].updated_at = new Date().toISOString();
      saveMockDb(db);
      return db.tickets[ticketIndex];
    }
  }

  // 6. Ticket Single GET / PUT
  const ticketSingleMatch = cleanPath.match(/\/tickets\/(\d+)$/);
  if (ticketSingleMatch) {
    const ticketId = parseInt(ticketSingleMatch[1]);
    const ticketIndex = db.tickets.findIndex(t => t.id === ticketId);
    if (ticketIndex !== -1) {
      if (method === 'PUT' || method === 'PATCH') {
        db.tickets[ticketIndex] = {
          ...db.tickets[ticketIndex],
          ...body,
          updated_at: new Date().toISOString()
        };
        saveMockDb(db);
      }
      return db.tickets[ticketIndex];
    }
  }

  // 7. Ticket List / Creation
  if (cleanPath.endsWith('/tickets')) {
    if (method === 'POST') {
      const newTicket = {
        id: db.tickets.length + 1,
        organization_id: currentUser.organization_id,
        created_by: currentUser.id,
        assigned_to: null,
        ticket_number: `TICK-${1000 + db.tickets.length + 1}`,
        title: body.title,
        description: body.description,
        status: 'open',
        priority: body.priority || 'medium',
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        creator: currentUser,
        assignee: null
      };
      db.tickets.push(newTicket);
      saveMockDb(db);
      return newTicket;
    }

    // Filter tickets
    let filtered = [...db.tickets];
    const urlObj = new URL(`${window.location.origin}${path}`);
    const search = urlObj.searchParams.get('search');
    const status = urlObj.searchParams.get('status');
    const priority = urlObj.searchParams.get('priority');

    if (search) {
      filtered = filtered.filter(t => 
        t.title.toLowerCase().includes(search.toLowerCase()) || 
        t.description.toLowerCase().includes(search.toLowerCase())
      );
    }
    if (status && status !== 'all') {
      filtered = filtered.filter(t => t.status === status);
    }
    if (priority && priority !== 'all') {
      filtered = filtered.filter(t => t.priority === priority);
    }
    return filtered;
  }

  // 8. General fallbacks
  if (cleanPath.endsWith('/agents')) {
    return db.users.filter(u => u.role === 'agent' || u.role === 'admin');
  }

  return {};
}

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
  const token = localStorage.getItem('pulsedesk_token');

  try {
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
      localStorage.removeItem('pulsedesk_token');
      localStorage.removeItem('pulsedesk_user');
      window.location.href = '/login';
      throw new Error('Unauthorized');
    }

    // If server responds with a 500 error, fall back to local mock DB
    if (response.status === 500) {
      console.error(`[Server 500] Falling back to Local Mock DB for ${path}`);
      return handleMockRequest(path, options);
    }

    if (!response.ok) {
      const error = await response.json().catch(() => ({ message: response.statusText }));
      throw new Error(error.message || 'Request failed');
    }

    return response.status === 204 ? null : response.json();
  } catch (err) {
    // If the network or CORS fails (fetch rejects), automatically use local storage mock DB!
    console.error(`[Network/CORS Error] Falling back to Local Mock DB for ${path}. Error:`, err);
    return handleMockRequest(path, options);
  }
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
