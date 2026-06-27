import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from "react";
import client, {
  clearStoredAuth,
  ensureCsrf,
  getStoredUser,
  persistUser,
} from "../api/client";

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // ---------------------------------------------------------
  // Boot — restore from storage, then verify against server
  // ---------------------------------------------------------
  useEffect(() => {
    const stored = getStoredUser();
    if (stored) setUser(stored);

    client
      .get("/api/auth/me")
      .then(({ data }) => {
        const me = data?.data ?? data;
        setUser(me);
        persistUser(me);
      })
      .catch(() => {
        setUser(null);
        clearStoredAuth();
      })
      .finally(() => setLoading(false));
  }, []);

  // ---------------------------------------------------------
  // Actions
  // ---------------------------------------------------------
  const login = useCallback(async (credentials) => {
    setError(null);
    await ensureCsrf();
    const { data } = await client.post("/api/auth/login", credentials);
    const me = data?.data ?? data?.user ?? data;
    setUser(me);
    persistUser(me, data?.token ?? null);
    return me;
  }, []);

  const register = useCallback(async (payload) => {
    setError(null);
    await ensureCsrf();
    const { data } = await client.post("/api/auth/register", payload);
    const me = data?.data ?? data?.user ?? data;
    setUser(me);
    persistUser(me, data?.token ?? null);
    return me;
  }, []);

  const logout = useCallback(async () => {
    try {
      await client.post("/api/auth/logout");
    } catch {
      // Ignore network errors on logout — clear local state regardless.
    } finally {
      setUser(null);
      clearStoredAuth();
    }
  }, []);

  const refresh = useCallback(async () => {
    const { data } = await client.get("/api/auth/me");
    const me = data?.data ?? data;
    setUser(me);
    persistUser(me);
    return me;
  }, []);

  const clearError = useCallback(() => setError(null), []);

  // ---------------------------------------------------------
  // Derived helpers
  // ---------------------------------------------------------
  const organizationId = user?.organization_id ?? null;
  const isAuthenticated = Boolean(user);
  const isOwner = user?.role === "owner" || user?.is_owner === true;

  const value = useMemo(
    () => ({
      user,
      loading,
      error,
      setError,
      clearError,
      login,
      register,
      logout,
      refresh,
      organizationId,
      isAuthenticated,
      isOwner,
    }),
    [
      user,
      loading,
      error,
      clearError,
      login,
      register,
      logout,
      refresh,
      organizationId,
      isAuthenticated,
      isOwner,
    ]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) {
    throw new Error("useAuth must be used within an <AuthProvider>");
  }
  return ctx;
}

export default AuthContext;
