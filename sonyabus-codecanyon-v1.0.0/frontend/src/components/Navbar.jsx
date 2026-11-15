import React from 'react';

export default function Navbar({
  activeTab,
  setActiveTab,
  setBookingSuccess,
  setPaymentFailed,
  authUser,
  handleLogout,
  openAuthModal,
  siteSettings,
}) {
  // Logo: image if uploaded, otherwise text fallback from site settings
  const backendOrigin = (import.meta.env.VITE_API_BASE_URL || '')
    .replace(/\/api\/?$/, '');

  // Build an absolute URL for the logo image so it always points to the
  // Laravel backend's /uploads/ directory — even when the React app is
  // served from a different origin (e.g. Vite dev server on :5173).
  const rawLogoUrl = siteSettings?.logo_url || null;
  const logoUrl = rawLogoUrl
    ? (rawLogoUrl.startsWith('http') ? rawLogoUrl : `${backendOrigin}${rawLogoUrl}`)
    : null;

  const logoText   = siteSettings?.logo_text || 'SonyaBus';
  const logoLetter = logoText.charAt(0).toUpperCase();
  const isDefaultText = !siteSettings?.logo_text;
  const defaultFirst  = 'Sonya';
  const defaultAccent = 'Bus';

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
          {logoUrl ? (
            /* ── Custom logo image ── */
            <img
              src={logoUrl}
              alt={logoText}
              style={{
                maxHeight: '38px',
                maxWidth: '160px',
                width: 'auto',
                objectFit: 'contain',
                display: 'block',
              }}
            />
          ) : isDefaultText ? (
            /* ── Default "SonyaBus" text logo ── */
            <>
              <div className="logo-icon">S</div>
              {defaultFirst}<span className="logo-accent">{defaultAccent}</span>
            </>
          ) : (
            /* ── Custom text logo from site settings ── */
            <>
              <div className="logo-icon">{logoLetter}</div>
              {logoText}
            </>
          )}
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
