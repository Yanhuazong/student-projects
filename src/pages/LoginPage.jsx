import { useEffect, useState } from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { apiRequest } from '../utils/api';
import { buildClassPath, getActiveClassSlug } from '../utils/classRouting';

const defaultBootstrapForm = {
  email: '',
  name: '',
  password: '',
};

export default function LoginPage() {
  const location = useLocation();
  const { bootstrapAdmin, login, user } = useAuth();
  const activeClassSlug = getActiveClassSlug(location.pathname);
  const [bootstrapNeeded, setBootstrapNeeded] = useState(false);
  const [loginForm, setLoginForm] = useState({ email: '', password: '' });
  const [bootstrapForm, setBootstrapForm] = useState(defaultBootstrapForm);
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    let cancelled = false;

    async function loadBootstrapStatus() {
      try {
        const response = await apiRequest('/auth/bootstrap-status');
        if (!cancelled) {
          setBootstrapNeeded(Boolean(response.needsBootstrap));
        }
      } catch (requestError) {
        if (!cancelled) {
          setError(requestError.message);
        }
      }
    }

    loadBootstrapStatus();

    return () => {
      cancelled = true;
    };
  }, []);

  if (user) {
    const fallbackPath = user.role === 'admin' || user.role === 'manager'
      ? buildClassPath('/dashboard', activeClassSlug)
      : buildClassPath('/', activeClassSlug);

    return <Navigate to={location.state?.from || fallbackPath} replace />;
  }

  async function handleLoginSubmit(event) {
    event.preventDefault();
    setSubmitting(true);
    setError('');

    try {
      await login(loginForm.email, loginForm.password);
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setSubmitting(false);
    }
  }

  async function handleBootstrapSubmit(event) {
    event.preventDefault();
    setSubmitting(true);
    setError('');

    try {
      await bootstrapAdmin(bootstrapForm);
      setBootstrapForm(defaultBootstrapForm);
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <main className="page-shell auth-layout">
      <section className="auth-panel">
        <p className="eyebrow">Account access</p>
        <h1>Sign in to vote or manage projects.</h1>
        <form className="stack-form" onSubmit={handleLoginSubmit}>
          <label>
            Email
            <input
              type="email"
              autoComplete="email"
              value={loginForm.email}
              onChange={(event) => setLoginForm({ ...loginForm, email: event.target.value })}
              required
            />
          </label>
          <label>
            Password
            <input
              type="password"
              autoComplete="current-password"
              value={loginForm.password}
              onChange={(event) => setLoginForm({ ...loginForm, password: event.target.value })}
              required
            />
          </label>
          <button type="submit" className="primary-button" disabled={submitting}>
            {submitting ? 'Signing in...' : 'Sign in'}
          </button>
        </form>
      </section>

      {bootstrapNeeded ? (
        <section className="auth-panel auth-panel--accent">
          <p className="eyebrow">First-time setup</p>
          <h2>Create the initial admin</h2>
          <form className="stack-form" onSubmit={handleBootstrapSubmit}>
            <label>
              Name
              <input
                type="text"
                value={bootstrapForm.name}
                onChange={(event) => setBootstrapForm({ ...bootstrapForm, name: event.target.value })}
                required
              />
            </label>
            <label>
              Email
              <input
                type="email"
                value={bootstrapForm.email}
                onChange={(event) => setBootstrapForm({ ...bootstrapForm, email: event.target.value })}
                required
              />
            </label>
            <label>
              Password
              <input
                type="password"
                autoComplete="new-password"
                value={bootstrapForm.password}
                onChange={(event) => setBootstrapForm({ ...bootstrapForm, password: event.target.value })}
                required
              />
            </label>
            <button type="submit" className="secondary-button" disabled={submitting}>
              {submitting ? 'Creating admin...' : 'Create admin'}
            </button>
          </form>
        </section>
      ) : null}

      {error ? <div className="error-banner auth-error">{error}</div> : null}
    </main>
  );
}
