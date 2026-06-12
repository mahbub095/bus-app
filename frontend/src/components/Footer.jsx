import React from 'react';

export default function Footer({
  siteSettings,
  setActiveTab,
  setBookingSuccess,
  authUser,
  openAuthModal
}) {
  return (
    <footer className="app-footer">
      <div className="container">
        <div className="footer-logo">
          {siteSettings?.footer?.company_name || 'SonyaBus Enterprise'}
        </div>
        <ul className="footer-links">
          {siteSettings?.footer?.links && Array.isArray(siteSettings.footer.links) ? (
            siteSettings.footer.links.map((link, idx) => (
              <li
                key={idx}
                onClick={() => {
                  if (link.url) {
                    window.open(link.url, '_blank');
                  } else if (link.tab) {
                    if (link.tab === 'home') {
                      setActiveTab('home');
                      setBookingSuccess(null);
                    } else if (link.tab === 'profile') {
                      authUser
                        ? setActiveTab('profile')
                        : openAuthModal('login', () => setActiveTab('profile'));
                    } else {
                      setActiveTab(link.tab);
                    }
                  }
                }}
                style={{ cursor: 'pointer' }}
              >
                {link.label}
              </li>
            ))
          ) : (
            <>
              <li
                onClick={() => {
                  setActiveTab('home');
                  setBookingSuccess(null);
                }}
                style={{ cursor: 'pointer' }}
              >
                Search Buses
              </li>
              <li onClick={() => setActiveTab('cancel')} style={{ cursor: 'pointer' }}>
                My Tickets
              </li>
              <li onClick={() => setActiveTab('offers')} style={{ cursor: 'pointer' }}>
                Special Promotions
              </li>
              <li
                onClick={() =>
                  authUser ? setActiveTab('profile') : openAuthModal('login', () => setActiveTab('profile'))
                }
                style={{ cursor: 'pointer' }}
              >
                My Profile
              </li>
            </>
          )}
        </ul>
        <p>
          {siteSettings?.footer?.copyright ||
            '© 2026 SonyaBus Enterprise Ltd. All rights reserved. Built with React + Laravel.'}
        </p>
      </div>
    </footer>
  );
}
