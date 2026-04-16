import { appendClassSlug } from './classRouting';

const API_BASE = import.meta.env.VITE_API_BASE || '/api';
const UPLOAD_API_BASE = import.meta.env.VITE_UPLOAD_API_BASE || API_BASE;
const UPLOAD_IMAGE_URL = import.meta.env.VITE_UPLOAD_IMAGE_URL || '';

function normalizeBase(base) {
  return String(base || '').replace(/\/$/, '');
}

function apiAppBasePath() {
  const normalized = normalizeBase(API_BASE);

  if (normalized.endsWith('/api/public/index.php')) {
    return normalized.slice(0, -('/api/public/index.php'.length));
  }

  if (normalized.endsWith('/api')) {
    return normalized.slice(0, -('/api'.length));
  }

  return '';
}

export function resolveImageUrl(imageUrl) {
  if (!imageUrl) return '';
  if (imageUrl.startsWith('http://') || imageUrl.startsWith('https://')) return imageUrl;
  if (imageUrl.startsWith('/uploads/')) {
    return `${apiAppBasePath()}${imageUrl}`;
  }
  return imageUrl;
}

function buildRequestUrl(base, path) {
  return `${normalizeBase(base)}${path}`;
}

async function sendApiRequest(base, path, options = {}) {
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

  const response = await fetch(buildRequestUrl(base, requestPath), {
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

export async function apiRequest(path, options = {}) {
  return sendApiRequest(API_BASE, path, options);
}

export async function uploadApiRequest(path, options = {}) {
  const { body, token, ...rest } = options;

  if (path === '/dashboard/uploads/image' && UPLOAD_IMAGE_URL) {
    if (!(body instanceof FormData)) {
      throw new Error('Upload endpoint requires multipart form data.');
    }

    if (token) {
      body.set('auth_token', token);
    }

    const uploadUrl = appendClassSlug(UPLOAD_IMAGE_URL, rest.classSlug);
    return sendApiRequest('', uploadUrl, {
      ...rest,
      includeClassSlug: false,
      body,
      token: undefined,
    });
  }

  if (body instanceof FormData && token) {
    body.set('auth_token', token);
    return sendApiRequest(UPLOAD_API_BASE, path, {
      ...rest,
      body,
      token: undefined,
    });
  }

  return sendApiRequest(UPLOAD_API_BASE, path, options);
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
