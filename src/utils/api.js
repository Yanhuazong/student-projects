import { appendClassSlug } from './classRouting';

const API_BASE = import.meta.env.VITE_API_BASE || '/api';

export function resolveImageUrl(imageUrl) {
  if (!imageUrl) return '';
  if (imageUrl.startsWith('http://') || imageUrl.startsWith('https://')) return imageUrl;
  if (imageUrl.startsWith('/uploads/')) return `${API_BASE}${imageUrl}`;
  return imageUrl;
}

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

  const contentType = response.headers.get('content-type') || '';
  const isJsonResponse = contentType.includes('application/json');
  const data = isJsonResponse ? await response.json().catch(() => ({})) : {};
  const responseText = !isJsonResponse ? await response.text().catch(() => '') : '';

  if (!response.ok) {
    const plainTextMessage = responseText
      .replace(/<[^>]+>/g, ' ')
      .replace(/\s+/g, ' ')
      .trim()
      .slice(0, 220);

    const message =
      data.error ||
      data.message ||
      plainTextMessage ||
      `Request failed (HTTP ${response.status}).`;

    const detailText = data.details
      ? ` ${typeof data.details === 'string' ? data.details : JSON.stringify(data.details)}`
      : '';

    throw new Error(`${message}${detailText} (HTTP ${response.status})`);
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
