import React from 'react';

/**
 * Shown when the Laravel backend is unreachable (network error on /api/site-settings).
 * Enhanced version:
 *   - Lists every candidate URL that was automatically tried.
 *   - Shows the VITE_API_BASE_URL env value that was used as primary.
 *   - Gives TWO setup paths (php artisan serve for dev, OR Laragon/Apache for shared).
 *   - One-click retry button.
 */
export default function BackendOffline({ onRetry, candidates = [], envUrl = '' }) {
  const setupPaths = [
    {
      cmd: 'php artisan serve',
      when: 'Dev environment — run in the backend/ folder',
      url: envUrl || 'http://localhost:8000 (default)',
    },
    {
      cmd: 'Laragon / WAMP / XAMPP',
      when: 'Shared hosting or local Apache server — put files in www/',
      url: window.location.origin + '/api',
    },
  ];

  return (
    <div className="backend-offline-page">
      <div className="backend-offline-card">
        <div className="backend-offline-icon">⚡</div>
        <h1 className="backend-offline-title">Cannot Connect to Server</h1>
        <p className="backend-offline-desc">
          Unable to reach the booking server. We automatically tried several common
          backend URLs — please check your setup below and try again.
        </p>

        {/* Candidate URLs tried */}
        {candidates && candidates.length > 0 && (
          <div style={{ textAlign: 'left', marginBottom: 22 }}>
            <div style={{ fontWeight: 700, fontSize: 13, color: '#475569', marginBottom: 8 }}>
              Tried these backend URLs:
            </div>
            <div style={{
              background: '#f8fafc',
              border: '1px solid #e2e8f0',
              borderRadius: 8,
              padding: '10px 14px',
              fontSize: 12,
              fontFamily: "'Fira Code', Consolas, monospace",
              color: '#334155',
              maxHeight: 140,
              overflowY: 'auto',
            }}>
              {candidates.map((u, i) => (
                <div key={i} style={{ padding: '3px 0', color: '#ef4444' }}>
                  ✗ {u}
                </div>
              ))}
            </div>
          </div>
        )}

        {envUrl && (
          <div style={{
            fontSize: 12,
            color: '#64748b',
            background: '#eff6ff',
            border: '1px solid #bfdbfe',
            borderRadius: 8,
            padding: '8px 12px',
            marginBottom: 18,
          }}>
            <b>Config:</b> frontend/.env → <code style={{ color: '#1d4ed8' }}>VITE_API_BASE_URL</code> =
            <span style={{ fontFamily: "'Fira Code', Consolas, monospace", marginLeft: 6 }}>{envUrl}</span>
          </div>
        )}

        <div style={{ display: 'flex', flexDirection: 'column', gap: 10, marginBottom: 24 }}>
          {setupPaths.map((s, i) => (
            <div key={i} style={{
              display: 'flex',
              alignItems: 'flex-start',
              gap: 12,
              padding: '12px 14px',
              background: '#f8fafc',
              border: '1px solid #e2e8f0',
              borderRadius: 8,
              textAlign: 'left',
            }}>
              <div style={{ flex: '0 0 auto' }}>
                <div style={{
                  background: '#6366f1',
                  color: '#fff',
                  fontWeight: 700,
                  borderRadius: 6,
                  padding: '4px 8px',
                  fontSize: 11,
                }}>Option {i + 1}</div>
              </div>
              <div style={{ flex: 1 }}>
                <code style={{
                  fontFamily: "'Fira Code', Consolas, monospace",
                  fontSize: 13,
                  fontWeight: 600,
                  color: '#4f46e5',
                  background: '#eef2ff',
                  padding: '2px 6px',
                  borderRadius: 4,
                }}>{s.cmd}</code>
                <div style={{ fontSize: 12, color: '#64748b', marginTop: 4, lineHeight: 1.5 }}>
                  <div>▸ {s.when}</div>
                  <div>▸ URL: <span style={{ fontFamily: "'Fira Code', Consolas, monospace", color: '#334155' }}>{s.url}</span></div>
                </div>
              </div>
            </div>
          ))}
        </div>

        <button
          className="btn btn-primary backend-offline-retry"
          onClick={onRetry}
        >
          ↻ Retry Connection
        </button>
      </div>

      <style>{`
        .backend-offline-page {
          min-height: 100vh;
          display: flex;
          align-items: center;
          justify-content: center;
          background: var(--bg-main, #f8fafc);
          padding: 24px;
        }
        .backend-offline-card {
          background: var(--bg-card, #fff);
          border: 1px solid var(--border-color, #e2e8f0);
          border-radius: 16px;
          padding: 40px 36px;
          max-width: 520px;
          width: 100%;
          text-align: center;
          box-shadow: 0 8px 32px rgba(0,0,0,.08);
        }
        .backend-offline-icon {
          font-size: 52px;
          margin-bottom: 16px;
          filter: grayscale(0.3);
        }
        .backend-offline-title {
          font-size: 22px;
          font-weight: 800;
          color: var(--text-primary, #1e293b);
          margin-bottom: 12px;
          font-family: var(--font-display, sans-serif);
        }
        .backend-offline-desc {
          font-size: 14px;
          color: var(--text-secondary, #64748b);
          line-height: 1.6;
          margin-bottom: 20px;
        }
        .backend-offline-retry {
          width: 100%;
          padding: 12px 20px;
          font-size: 14px;
          font-weight: 600;
        }
      `}</style>
    </div>
  );
}
