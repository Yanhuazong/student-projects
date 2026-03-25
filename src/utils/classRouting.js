const CLASS_SLUG_PATTERN = /^cgt\d+$/i;

function normalizeClassSlug(value) {
  const normalized = String(value || '').trim().toLowerCase();
  return CLASS_SLUG_PATTERN.test(normalized) ? normalized : '';
}

function normalizePath(path) {
  if (!path || path === '/') {
    return '/';
  }

  return path.startsWith('/') ? path : `/${path}`;
}

function getBasePath() {
  const normalizedBase = normalizePath(import.meta.env.BASE_URL || '/');
  return normalizedBase.endsWith('/') && normalizedBase !== '/'
    ? normalizedBase.slice(0, -1)
    : normalizedBase;
}

function stripBasePath(pathname) {
  const normalizedPathname = normalizePath(String(pathname || '/').split('?')[0]);
  const basePath = getBasePath();

  if (basePath !== '/' && normalizedPathname.startsWith(`${basePath}/`)) {
    return normalizedPathname.slice(basePath.length) || '/';
  }

  if (normalizedPathname === basePath) {
    return '/';
  }

  return normalizedPathname;
}

export function getFixedClassSlug() {
  const segments = String(import.meta.env.BASE_URL || '/')
    .split('/')
    .filter(Boolean);

  return normalizeClassSlug(segments[segments.length - 1] || '');
}

export function getDefaultClassSlug() {
  return normalizeClassSlug(import.meta.env.VITE_DEFAULT_CLASS_SLUG) || getFixedClassSlug() || 'cgt390';
}

export function getClassSlugFromPathname(pathname) {
  const [firstSegment = ''] = stripBasePath(pathname)
    .split('/')
    .filter(Boolean);

  return normalizeClassSlug(firstSegment);
}

export function getActiveClassSlug(pathname) {
  return getClassSlugFromPathname(pathname) || getFixedClassSlug() || getDefaultClassSlug();
}

export function buildClassPath(path, classSlug) {
  const normalizedPath = normalizePath(path);
  const fixedClassSlug = getFixedClassSlug();

  if (fixedClassSlug) {
    return normalizedPath;
  }

  const resolvedClassSlug = normalizeClassSlug(classSlug) || getDefaultClassSlug();

  if (normalizedPath === '/') {
    return `/${resolvedClassSlug}`;
  }

  return `/${resolvedClassSlug}${normalizedPath}`;
}

export function buildClassRoute(path) {
  const normalizedPath = normalizePath(path);

  if (getFixedClassSlug()) {
    return normalizedPath;
  }

  if (normalizedPath === '/') {
    return '/:classSlug';
  }

  return `/:classSlug${normalizedPath}`;
}

export function appendClassSlug(path, classSlug) {
  const resolvedClassSlug = normalizeClassSlug(classSlug) || getActiveClassSlug(globalThis.location?.pathname || '/');

  if (!resolvedClassSlug) {
    return path;
  }

  const [pathname, search = ''] = String(path).split('?');
  const params = new URLSearchParams(search);

  if (!params.has('class_slug')) {
    params.set('class_slug', resolvedClassSlug);
  }

  const nextSearch = params.toString();
  return nextSearch ? `${pathname}?${nextSearch}` : pathname;
}
