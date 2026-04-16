import { useState } from 'react';
import { Link, Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { buildClassPath, getActiveClassSlug } from '../utils/classRouting';

const defaultForm = {
  name: '',
  email: '',
  password: '',
  confirmPassword: '',
  role: 'user',
  managerInviteCode: '',
};

export default function RegisterPage() {
  const location = useLocation();
  const activeClassSlug = getActiveClassSlug(location.pathname);
  const { register, user } = useAuth();
  const [form, setForm] = useState(defaultForm);
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  if (user) {
    return <Navigate to={buildClassPath('/', activeClassSlug)} replace />;
  }

  async function handleSubmit(event) {
    event.preventDefault();
    setError('');

    if (form.password !== form.confirmPassword) {
      setError('Passwords do not match.');
      return;
    }

    setSubmitting(true);

    try {
      await register(form.name, form.email, form.password, {
        role: form.role,
        managerInviteCode: form.managerInviteCode,
      });
      setForm(defaultForm);
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <main className="page-shell auth-layout">
      <section className="auth-panel">
        <p className="eyebrow">Class registration</p>
        <h1>Create your account for this class.</h1>
        <form className="stack-form" onSubmit={handleSubmit}>
          <label>
            Name
            <input
              type="text"
              value={form.name}
              onChange={(event) => setForm({ ...form, name: event.target.value })}
              required
            />
          </label>
          <label>
            Organizational email
            <input
              type="email"
              autoComplete="email"
              value={form.email}
              onChange={(event) => setForm({ ...form, email: event.target.value })}
              required
            />
          </label>
          <label>
            Password
            <input
              type="password"
              autoComplete="new-password"
              value={form.password}
              onChange={(event) => setForm({ ...form, password: event.target.value })}
              required
            />
          </label>
          <label>
            Confirm password
            <input
              type="password"
              autoComplete="new-password"
              value={form.confirmPassword}
              onChange={(event) => setForm({ ...form, confirmPassword: event.target.value })}
              required
            />
          </label>
          <label>
            Account role
            <select
              value={form.role}
              onChange={(event) => setForm({ ...form, role: event.target.value, managerInviteCode: event.target.value === 'manager' ? form.managerInviteCode : '' })}
            >
              <option value="user">User</option>
              <option value="manager">Manager</option>
            </select>
          </label>
          {form.role === 'manager' ? (
            <label>
              Manager invite code
              <input
                type="password"
                value={form.managerInviteCode}
                onChange={(event) => setForm({ ...form, managerInviteCode: event.target.value })}
                required
              />
            </label>
          ) : null}
          <button type="submit" className="primary-button" disabled={submitting}>
            {submitting ? 'Creating account...' : 'Create account'}
          </button>
        </form>
        <p>
          Already have an account? <Link to={buildClassPath('/login', activeClassSlug)}>Sign in</Link>
        </p>
      </section>

      {error ? <div className="error-banner auth-error">{error}</div> : null}
    </main>
  );
}
