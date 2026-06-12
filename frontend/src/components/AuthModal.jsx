import React from 'react';

export default function AuthModal({
  showAuthModal,
  closeAuthModal,
  authMode,
  setAuthMode,
  authForm,
  setAuthForm,
  handleAuthSubmit,
  isAuthLoading
}) {
  if (!showAuthModal) return null;

  return (
    <div className="modal-overlay" onClick={closeAuthModal}>
      <div className="auth-modal" onClick={(e) => e.stopPropagation()}>
        <button className="modal-close-btn" type="button" onClick={closeAuthModal}>
          &times;
        </button>
        <h2 style={{ fontFamily: 'var(--font-display)', marginBottom: '8px' }}>
          {authMode === 'register' ? 'Create Account' : 'Customer Login'}
        </h2>
        <p style={{ color: 'var(--text-secondary)', fontSize: '13px', marginBottom: '20px' }}>
          {authMode === 'register'
            ? 'Register to book and manage your bus tickets online.'
            : 'Sign in to purchase tickets and cancel your own bookings.'}
        </p>

        <div className="auth-tabs">
          <div
            className={`auth-tab ${authMode === 'login' ? 'active' : ''}`}
            onClick={() => setAuthMode('login')}
          >
            Login
          </div>
          <div
            className={`auth-tab ${authMode === 'register' ? 'active' : ''}`}
            onClick={() => setAuthMode('register')}
          >
            Register
          </div>
        </div>

        <form
          onSubmit={handleAuthSubmit}
          style={{ display: 'flex', flexDirection: 'column', gap: '14px', textAlign: 'left' }}
        >
          {authMode === 'register' && (
            <div className="input-group">
              <label>Full Name</label>
              <input
                type="text"
                className="coupon-input"
                value={authForm.name}
                onChange={(e) => setAuthForm(prev => ({ ...prev, name: e.target.value }))}
                required
              />
            </div>
          )}
          <div className="input-group">
            <label>Email Address</label>
            <input
              type="email"
              className="coupon-input"
              value={authForm.email}
              onChange={(e) => setAuthForm(prev => ({ ...prev, email: e.target.value }))}
              required
            />
          </div>
          <div className="input-group">
            <label>Password</label>
            <input
              type="password"
              className="coupon-input"
              value={authForm.password}
              onChange={(e) => setAuthForm(prev => ({ ...prev, password: e.target.value }))}
              required
              minLength={6}
            />
          </div>
          {authMode === 'register' && (
            <div className="input-group">
              <label>Confirm Password</label>
              <input
                type="password"
                className="coupon-input"
                value={authForm.password_confirmation}
                onChange={(e) => setAuthForm(prev => ({ ...prev, password_confirmation: e.target.value }))}
                required
                minLength={6}
              />
            </div>
          )}
          <button className="btn btn-primary w-full" type="submit" disabled={isAuthLoading}>
            {isAuthLoading ? 'Please wait...' : (authMode === 'register' ? 'Create Account' : 'Sign In')}
          </button>
        </form>
      </div>
    </div>
  );
}
