import { useEffect, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { apiRequest } from '../utils/api';
import { buildClassPath, getActiveClassSlug } from '../utils/classRouting';

export default function ForgotPasswordPage() {
  const location = useLocation();
  const activeClassSlug = getActiveClassSlug(location.pathname);
  const [requestForm, setRequestForm] = useState({ email: '' });
  const [resetForm, setResetForm] = useState({ token: '', email: '', resetCode: '', newPassword: '', confirmPassword: '' });
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [requesting, setRequesting] = useState(false);
  const [resetting, setResetting] = useState(false);

  useEffect(() => {
    const params = new URLSearchParams(location.search || '');
    const tokenFromUrl = (params.get('token') || '').trim();

    if (!tokenFromUrl) {
      return;
    }

    setResetForm((current) => ({
      ...current,
      token: tokenFromUrl,
    }));
  }, [location.search]);

  async function handleRequestSubmit(event) {
    event.preventDefault();
    setError('');
    setMessage('');
    setRequesting(true);

    try {
      const response = await apiRequest('/auth/forgot-password', {
        method: 'POST',
        body: {
          email: requestForm.email,
        },
      });

      setMessage(response.message || 'If the account exists, a reset token has been created.');

      if (response.reset_token) {
        setResetForm((current) => ({
          ...current,
          email: requestForm.email,
          token: response.reset_token,
        }));
      }
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setRequesting(false);
    }
  }

  async function handleResetSubmit(event) {
    event.preventDefault();
    setError('');
    setMessage('');

    if (resetForm.newPassword.length < 8) {
      setError('New password must be at least 8 characters long.');
      return;
    }

    if (resetForm.newPassword !== resetForm.confirmPassword) {
      setError('New password and confirmation do not match.');
      return;
    }

    setResetting(true);

    try {
      const response = await apiRequest('/auth/reset-password', {
        method: 'POST',
        body: {
          token: resetForm.token,
          email: resetForm.email,
          reset_code: resetForm.resetCode,
          new_password: resetForm.newPassword,
        },
      });

      setMessage(response.message || 'Password has been reset. You can now sign in.');
      setResetForm({ token: '', email: '', resetCode: '', newPassword: '', confirmPassword: '' });
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setResetting(false);
    }
  }

  return (
    <main className="page-shell auth-layout">
      <section className="auth-panel">
        <p className="eyebrow">Password recovery</p>
        <h1>Forgot your password?</h1>
        <p>Set a new password using either a reset token or your class reset code.</p>
        <p>
          Short-term option: use your class reset code from your instructor/admin.
          You can still use token links if email delivery is enabled.
        </p>

        <form className="stack-form" onSubmit={handleRequestSubmit}>
          <label>
            Account email
            <input
              type="email"
              autoComplete="email"
              value={requestForm.email}
              onChange={(event) => setRequestForm({ email: event.target.value })}
              required
            />
          </label>
          <button type="submit" className="primary-button" disabled={requesting}>
            {requesting ? 'Requesting token...' : 'Request reset token'}
          </button>
        </form>

        <form className="stack-form" onSubmit={handleResetSubmit}>
          <label>
            Account email
            <input
              type="email"
              autoComplete="email"
              value={resetForm.email}
              onChange={(event) => setResetForm({ ...resetForm, email: event.target.value })}
              required
            />
          </label>
          <label>
            Class reset code (optional if using token)
            <input
              type="text"
              value={resetForm.resetCode}
              onChange={(event) => setResetForm({ ...resetForm, resetCode: event.target.value })}
            />
          </label>
          <label>
            Reset token (optional if using class reset code)
            <input
              type="text"
              value={resetForm.token}
              onChange={(event) => setResetForm({ ...resetForm, token: event.target.value })}
            />
          </label>
          <label>
            New password
            <input
              type="password"
              autoComplete="new-password"
              value={resetForm.newPassword}
              onChange={(event) => setResetForm({ ...resetForm, newPassword: event.target.value })}
              required
            />
          </label>
          <label>
            Confirm new password
            <input
              type="password"
              autoComplete="new-password"
              value={resetForm.confirmPassword}
              onChange={(event) => setResetForm({ ...resetForm, confirmPassword: event.target.value })}
              required
            />
          </label>
          <button type="submit" className="secondary-button" disabled={resetting}>
            {resetting ? 'Resetting password...' : 'Set new password'}
          </button>
        </form>

        {message ? <div className="success-banner auth-error">{message}</div> : null}
        {error ? <div className="error-banner auth-error">{error}</div> : null}

        <p>
          Back to <Link to={buildClassPath('/login', activeClassSlug)}>Sign in</Link>
        </p>
      </section>
    </main>
  );
}
