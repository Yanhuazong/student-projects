import { createContext, useContext, useEffect, useState } from 'react';
import { apiRequest } from '../utils/api';

const AuthContext = createContext(null);

const STORAGE_KEY = 'student-projects-auth';

function readStoredToken() {
  const raw = localStorage.getItem(STORAGE_KEY);

  if (!raw || raw === 'null' || raw === 'undefined') {
    localStorage.removeItem(STORAGE_KEY);
    return '';
  }

  // Token format in this app is "base64Payload.signature".
  if (!raw.includes('.')) {
    localStorage.removeItem(STORAGE_KEY);
    return '';
  }

  return raw;
}

function decodeTokenPayload(token) {
  try {
    const encodedPayload = token.split('.')[0] || '';
    const base64 = encodedPayload.replace(/-/g, '+').replace(/_/g, '/');
    const padded = base64 + '='.repeat((4 - (base64.length % 4)) % 4);
    const payloadText = atob(padded);
    const payload = JSON.parse(payloadText);

    if (!payload || typeof payload !== 'object') {
      return null;
    }

    if (!payload.exp || Number(payload.exp) < Date.now() / 1000) {
      return null;
    }

    if (!payload.user_id || !payload.email || !payload.role) {
      return null;
    }

    return {
      id: Number(payload.user_id),
      class_id: payload.class_id === null || payload.class_id === undefined ? null : Number(payload.class_id),
      name: payload.name || '',
      email: payload.email,
      role: payload.role,
    };
  } catch {
    return null;
  }
}

export function AuthProvider({ children }) {
  const [token, setToken] = useState(() => readStoredToken());
  const [user, setUser] = useState(null);
  const [authLoading, setAuthLoading] = useState(Boolean(readStoredToken()));

  useEffect(() => {
    if (!token) {
      setUser(null);
      setAuthLoading(false);
      return;
    }

    const hydratedUser = decodeTokenPayload(token);

    if (!hydratedUser) {
      localStorage.removeItem(STORAGE_KEY);
      setToken('');
      setUser(null);
      setAuthLoading(false);
      return;
    }

    setUser(hydratedUser);
    setAuthLoading(false);
  }, [token]);

  function setSession(nextToken, nextUser) {
    localStorage.setItem(STORAGE_KEY, nextToken);
    setToken(nextToken);
    setUser(nextUser);
  }

  async function login(email, password) {
    const response = await apiRequest('/auth/login', {
      method: 'POST',
      body: { email, password },
    });

    setSession(response.token, response.user);
  }

  async function bootstrapAdmin(payload) {
    const response = await apiRequest('/auth/bootstrap-admin', {
      method: 'POST',
      body: payload,
    });

    setSession(response.token, response.user);
  }

  function logout() {
    localStorage.removeItem(STORAGE_KEY);
    setToken('');
    setUser(null);
  }

  return (
    <AuthContext.Provider
      value={{
        authLoading,
        bootstrapAdmin,
        login,
        logout,
        token,
        user,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  return useContext(AuthContext);
}
