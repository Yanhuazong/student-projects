import { appendClassSlug } from './classRouting';

const API_BASE = import.meta.env.VITE_API_BASE || '/api';

export async function apiRequest(path, options = {}) {
  const {
    body,
    token,
    headers,
    classSlug,
    includeClassSlug = true,
    ...rest
  } = options;
  const isFormData = body instanceof FormData;
  const requestPath = includeClassSlug ? appendClassSlug(path, classSlug) : path;

  const response = await fetch(`${API_BASE}${requestPath}`, {
    ...rest,
    headers: {
      ...(!isFormData ? { 'Content-Type': 'application/json' } : {}),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...headers,
    },
    body: body ? (isFormData ? body : JSON.stringify(body)) : undefined,
  });

  const data = await response.json().catch(() => ({}));

  if (!response.ok) {
    throw new Error(data.error || 'Request failed.');
  }

  return data;
}

export function getDeviceToken() {
  const storageKey = 'student-projects-device-token';
  const existing = localStorage.getItem(storageKey);

  if (existing) {
    return existing;
  }

  const token = globalThis.crypto?.randomUUID?.() || `device-${Date.now()}-${Math.random().toString(16).slice(2)}`;
  localStorage.setItem(storageKey, token);
  return token;
}
