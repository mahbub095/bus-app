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
          {authMode === 'register' ? 'Create Account' : 
           authMode === 'forgot' ? 'Forgot Password' : 
           authMode === 'reset' ? 'Reset Password' : 'Customer Login'}
        </h2>
        <p style={{ color: 'var(--text-secondary)', fontSize: '13px', marginBottom: '20px' }}>
          {authMode === 'register'
            ? 'Register to book and manage your bus tickets online.'
            : authMode === 'forgot'
            ? 'Enter your email address to receive a 6-digit password reset code.'
            : authMode === 'reset'
            ? 'Enter the reset code sent to your email along with your new password.'
            : 'Sign in to purchase tickets and cancel your own bookings.'}
        </p>

        {(authMode === 'login' || authMode === 'register') && (
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
        )}

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
                value={authForm.name || ''}
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
              value={authForm.email || ''}
              onChange={(e) => setAuthForm(prev => ({ ...prev, email: e.target.value }))}
              required
              readOnly={authMode === 'reset'}
              style={authMode === 'reset' ? { opacity: 0.7, cursor: 'not-allowed' } : {}}
            />
          </div>

          {authMode === 'reset' && (
            <div className="input-group">
              <label>Reset Code</label>
              <input
                type="text"
                className="coupon-input"
                placeholder="6-digit code"
                value={authForm.code || ''}
                onChange={(e) => setAuthForm(prev => ({ ...prev, code: e.target.value }))}
                required
                maxLength={6}
              />
            </div>
          )}

          {(authMode === 'login' || authMode === 'register' || authMode === 'reset') && (
            <div className="input-group">
              <label>{authMode === 'reset' ? 'New Password' : 'Password'}</label>
              <input
                type="password"
                className="coupon-input"
                value={authForm.password || ''}
                onChange={(e) => setAuthForm(prev => ({ ...prev, password: e.target.value }))}
                required
                minLength={6}
              />
            </div>
          )}

          {authMode === 'login' && (
            <div style={{ textAlign: 'right', marginTop: '-6px' }}>
              <span 
                style={{ fontSize: '12px', color: 'var(--primary)', cursor: 'pointer', fontWeight: '500' }}
                onClick={() => setAuthMode('forgot')}
              >
                Forgot Password?
              </span>
            </div>
          )}

          {(authMode === 'register' || authMode === 'reset') && (
            <div className="input-group">
              <label>{authMode === 'reset' ? 'Confirm New Password' : 'Confirm Password'}</label>
              <input
                type="password"
                className="coupon-input"
                value={authForm.password_confirmation || ''}
                onChange={(e) => setAuthForm(prev => ({ ...prev, password_confirmation: e.target.value }))}
                required
                minLength={6}
              />
            </div>
          )}

          <button className="btn btn-primary w-full" type="submit" disabled={isAuthLoading}>
            {isAuthLoading ? 'Please wait...' : 
             authMode === 'register' ? 'Create Account' : 
             authMode === 'forgot' ? 'Send Reset Code' : 
             authMode === 'reset' ? 'Update Password' : 'Sign In'}
          </button>

          {(authMode === 'forgot' || authMode === 'reset') && (
            <div style={{ textAlign: 'center', marginTop: '6px' }}>
              <span 
                style={{ fontSize: '13px', color: 'var(--text-secondary)', cursor: 'pointer' }}
                onClick={() => setAuthMode('login')}
              >
                &larr; Back to Login
              </span>
            </div>
          )}
        </form>
      </div>
    </div>
  );
}
