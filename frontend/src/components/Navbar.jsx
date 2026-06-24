import React from 'react';

export default function Navbar({
  activeTab,
  setActiveTab,
  setBookingSuccess,
  setPaymentFailed,
  authUser,
  handleLogout,
  openAuthModal
}) {
  return (
    <header className="app-header">
      <div className="container navbar">
        <div
          className="logo"
          onClick={() => {
            setActiveTab('home');
            setBookingSuccess(null);
            if (setPaymentFailed) setPaymentFailed(null);
          }}
          style={{ cursor: 'pointer' }}
        >
          <div className="logo-icon">S</div>
          Sonya<span className="logo-accent">Bus</span>
        </div>
        <ul className="nav-menu">
          <li
            className={`nav-link ${activeTab === 'home' ? 'active' : ''}`}
            onClick={() => {
              setActiveTab('home');
              setBookingSuccess(null);
              if (setPaymentFailed) setPaymentFailed(null);
            }}
          >
            Ticket Booking
          </li>
          <li
            className={`nav-link ${activeTab === 'cancel' ? 'active' : ''}`}
            onClick={() => {
              setActiveTab('cancel');
              if (setPaymentFailed) setPaymentFailed(null);
            }}
          >
            My Tickets
          </li>
          <li
            className={`nav-link ${activeTab === 'offers' ? 'active' : ''}`}
            onClick={() => {
              setActiveTab('offers');
              if (setPaymentFailed) setPaymentFailed(null);
            }}
          >
            Promotions & Offers
          </li>
          <li
            className={`nav-link ${activeTab === 'profile' ? 'active' : ''}`}
            onClick={() => {
              if (setPaymentFailed) setPaymentFailed(null);
              if (!authUser) {
                openAuthModal('login', () => setActiveTab('profile'));
                return;
              }
              setActiveTab('profile');
            }}
          >
            My Profile
          </li>
        </ul>

        <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
          {authUser ? (
            <>
              <span style={{ fontSize: '13px', color: 'var(--text-secondary)' }}>
                Hi, <strong style={{ color: 'var(--text-primary)' }}>{authUser.name}</strong>
              </span>
              <button
                className="btn btn-secondary"
                style={{ padding: '8px 14px', fontSize: '12px' }}
                onClick={handleLogout}
              >
                Logout
              </button>
            </>
          ) : (
            <>
              <button
                className="btn btn-secondary"
                style={{ padding: '8px 14px', fontSize: '12px' }}
                onClick={() => openAuthModal('login')}
              >
                Login
              </button>
              <button
                className="btn btn-primary"
                style={{ padding: '8px 14px', fontSize: '12px' }}
                onClick={() => openAuthModal('register')}
              >
                Register
              </button>
            </>
          )}
        </div>
      </div>
    </header>
  );
}
