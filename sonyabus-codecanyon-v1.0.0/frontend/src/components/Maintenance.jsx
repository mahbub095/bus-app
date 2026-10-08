export default function Maintenance({ siteSettings }) {
  return (
    <div className="maintenance-page">
      <div className="maintenance-content">
        <div className="maintenance-icon-wrap">
          <div className="maintenance-icon">🔧</div>
          <div className="maintenance-pulse"></div>
        </div>
        <h1 className="maintenance-title">Under Maintenance</h1>
        <p className="maintenance-message">
          {siteSettings?.maintenance?.message || 'We are currently performing scheduled maintenance. Please check back soon.'}
        </p>
        <div className="maintenance-brand">
          <div className="logo-icon" style={{ width: 32, height: 32, fontSize: 16 }}>S</div>
          <span style={{ fontFamily: 'var(--font-display)', fontWeight: 800, fontSize: 18 }}>
            {siteSettings?.footer?.company_name || 'SonyaBus'}
          </span>
        </div>
        <div className="maintenance-footer">
          {siteSettings?.footer?.copyright || '© 2026 SonyaBus Enterprise Ltd.'}
        </div>
      </div>
    </div>
  );
}
