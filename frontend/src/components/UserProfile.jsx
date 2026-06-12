import React from 'react';

export default function UserProfile({
  authUser,
  openAuthModal,
  setActiveTab,
  profileForm,
  setProfileForm,
  handleProfilePasswordSubmit,
  isUpdatingProfile
}) {
  return (
    <div className="container" style={{ flexGrow: 1, padding: '40px 0' }}>
      <div className="cancel-card">
        <h2 className="cancel-title">My Profile</h2>
        {!authUser ? (
          <div style={{ textAlign: 'center', padding: '30px 0' }}>
            <p style={{ color: 'var(--text-secondary)', marginBottom: '16px' }}>
              Please log in to manage your account password.
            </p>
            <button
              className="btn btn-primary"
              onClick={() => openAuthModal('login', () => setActiveTab('profile'))}
            >
              Login to Continue
            </button>
          </div>
        ) : (
          <div style={{ maxWidth: '560px', margin: '0 auto' }}>
            <p style={{ color: 'var(--text-secondary)', marginBottom: '18px' }}>
              Signed in as <strong style={{ color: '#fff' }}>{authUser.email}</strong>
            </p>
            <form onSubmit={handleProfilePasswordSubmit} className="booking-form-fields">
              <div className="input-group">
                <label>Current Password</label>
                <input
                  type="password"
                  className="coupon-input"
                  value={profileForm.current_password}
                  onChange={(e) =>
                    setProfileForm((prev) => ({ ...prev, current_password: e.target.value }))
                  }
                  required
                  minLength={6}
                  autoComplete="current-password"
                />
              </div>
              <div className="input-group">
                <label>New Password</label>
                <input
                  type="password"
                  className="coupon-input"
                  value={profileForm.password}
                  onChange={(e) => setProfileForm((prev) => ({ ...prev, password: e.target.value }))}
                  required
                  minLength={6}
                  autoComplete="new-password"
                />
              </div>
              <div className="input-group">
                <label>Confirm New Password</label>
                <input
                  type="password"
                  className="coupon-input"
                  value={profileForm.password_confirmation}
                  onChange={(e) =>
                    setProfileForm((prev) => ({ ...prev, password_confirmation: e.target.value }))
                  }
                  required
                  minLength={6}
                  autoComplete="new-password"
                />
              </div>
              <button className="btn btn-primary" type="submit" disabled={isUpdatingProfile}>
                {isUpdatingProfile ? 'Updating Password...' : 'Update Password'}
              </button>
            </form>
          </div>
        )}
      </div>
    </div>
  );
}
