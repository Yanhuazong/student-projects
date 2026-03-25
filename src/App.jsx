import { useEffect, useState } from 'react';
import { Link, Navigate, Route, Routes, useLocation } from 'react-router-dom';
import ProtectedRoute from './components/ProtectedRoute';
import { useAuth } from './contexts/AuthContext';
import DashboardPage from './pages/DashboardPage';
import HomePage from './pages/HomePage';
import LoginPage from './pages/LoginPage';
import ProjectPage from './pages/ProjectPage';
import { apiRequest } from './utils/api';
import { buildClassPath, buildClassRoute, getActiveClassSlug, getDefaultClassSlug, getFixedClassSlug } from './utils/classRouting';

function AppHeader() {
  const { user, logout } = useAuth();
  const location = useLocation();
  const [logoText, setLogoText] = useState('Student Projects');
  const activeClassSlug = getActiveClassSlug(location.pathname);

  useEffect(() => {
    let cancelled = false;

    async function loadSettings() {
      try {
        const response = await apiRequest('/settings/public');
        if (!cancelled && response.settings?.site_logo_text) {
          setLogoText(response.settings.site_logo_text);
        }
      } catch {
        // Keep default logo text if settings endpoint is unavailable.
      }
    }

    loadSettings();

    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <header className="site-header">
      <div className="site-header__inner">
        <Link to={buildClassPath('/', activeClassSlug)} className="site-logo">
          {logoText}
        </Link>
        <nav className="site-nav">
          <Link to={buildClassPath('/', activeClassSlug)}>Projects</Link>
          {user ? (
            <Link to={buildClassPath('/dashboard', activeClassSlug)}>Dashboard</Link>
          ) : (
            <Link to={buildClassPath('/login', activeClassSlug)}>Login</Link>
          )}
          {user ? (
            <button type="button" className="ghost-button" onClick={logout}>
              Sign out
            </button>
          ) : null}
        </nav>
      </div>
    </header>
  );
}

function AppFooter() {
  const year = new Date().getFullYear();

  return (
    <footer className="site-footer">
      <div className="site-footer__inner">
        <p>&copy; {year} Student Projects</p>
        <p>
          Questions or interests? Contact: <a href="mailto:zong6@purdue.edu">zong6@purdue.edu</a>
        </p>
      </div>
    </footer>
  );
}

export default function App() {
  const location = useLocation();
  const fixedClassSlug = getFixedClassSlug();
  const activeClassSlug = getActiveClassSlug(location.pathname);
  const defaultClassSlug = getDefaultClassSlug();

  return (
    <div className="app-shell">
      <AppHeader />
      <Routes>
        {!fixedClassSlug ? <Route path="/" element={<Navigate to={`/${defaultClassSlug}`} replace />} /> : null}
        <Route path={buildClassRoute('/')} element={<HomePage />} />
        <Route path={buildClassRoute('/projects/:slug')} element={<ProjectPage />} />
        <Route path={buildClassRoute('/login')} element={<LoginPage />} />
        <Route
          path={buildClassRoute('/dashboard')}
          element={
            <ProtectedRoute>
              <DashboardPage />
            </ProtectedRoute>
          }
        />
        <Route path="*" element={<Navigate to={buildClassPath('/', activeClassSlug)} replace />} />
      </Routes>
      <AppFooter />
    </div>
  );
}
