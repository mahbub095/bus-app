import React, { useState } from 'react';

export default function UserProfile({
  authUser,
  authToken,
  clearAuth,
  openAuthModal,
  setActiveTab,
  showToast,
  API_BASE,
  setAuthUser,
  AUTH_USER_KEY
}) {
  const [profileForm, setProfileForm] = useState({
    current_password: '',
    password: '',
    password_confirmation: ''
  });
  const [isUpdatingProfile, setIsUpdatingProfile] = useState(false);

  const requireAuth = (returnAction) => {
    if (authUser && authToken) {
      return true;
    }
    openAuthModal('login', returnAction);
    return false;
  };

  const handleProfilePasswordSubmit = async (e) => {
    e.preventDefault();
    if (!requireAuth(() => setActiveTab('profile'))) return;

    if (profileForm.password !== profileForm.password_confirmation) {
      showToast('New password and confirmation do not match.', 'error');
      return;
    }

    setIsUpdatingProfile(true);
    try {
      const headers = {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      };
      if (authToken) {
        headers['Authorization'] = `Bearer ${authToken}`;
      }

      const res = await fetch(`${API_BASE}/auth/password`, {
        method: 'POST',
        headers,
        body: JSON.stringify(profileForm)
      });
      const data = await res.json();
      if (res.status === 401) {
        clearAuth();
        showToast('Session expired. Please log in again.', 'error');
        openAuthModal('login', () => setActiveTab('profile'));
        return;
      }
      if (res.ok) {
        if (data.user) {
          setAuthUser(data.user);
          localStorage.setItem(AUTH_USER_KEY, JSON.stringify(data.user));
        }
        setProfileForm({ current_password: '', password: '', password_confirmation: '' });
        showToast(data.message || 'Password updated successfully.', 'success');
      } else {
        const msg = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Failed to update password.');
        showToast(msg, 'error');
      }
    } catch (err) {
      showToast('Network error while updating password.', 'error');
    } finally {
      setIsUpdatingProfile(false);
    }
  };

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
