import { useState } from 'react';
import { useAuth } from '../contexts/AuthContext';

const defaultForm = {
  currentPassword: '',
  newPassword: '',
  confirmPassword: '',
};

export default function AccountPage() {
  const { changePassword } = useAuth();
  const [form, setForm] = useState(defaultForm);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit(event) {
    event.preventDefault();
    setMessage('');
    setError('');

    if (form.newPassword !== form.confirmPassword) {
      setError('New passwords do not match.');
      return;
    }

    setSubmitting(true);

    try {
      await changePassword(form.currentPassword, form.newPassword);
      setMessage('Password updated.');
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
        <p className="eyebrow">Account security</p>
        <h1>Change your password</h1>
        <form className="stack-form" onSubmit={handleSubmit}>
          <label>
            Current password
            <input
              type="password"
              autoComplete="current-password"
              value={form.currentPassword}
              onChange={(event) => setForm({ ...form, currentPassword: event.target.value })}
              required
            />
          </label>
          <label>
            New password
            <input
              type="password"
              autoComplete="new-password"
              value={form.newPassword}
              onChange={(event) => setForm({ ...form, newPassword: event.target.value })}
              required
            />
          </label>
          <label>
            Confirm new password
            <input
              type="password"
              autoComplete="new-password"
              value={form.confirmPassword}
              onChange={(event) => setForm({ ...form, confirmPassword: event.target.value })}
              required
            />
          </label>
          <button type="submit" className="primary-button" disabled={submitting}>
            {submitting ? 'Updating password...' : 'Update password'}
          </button>
        </form>
      </section>

      {message ? <div className="success-banner auth-error">{message}</div> : null}
      {error ? <div className="error-banner auth-error">{error}</div> : null}
    </main>
  );
}
