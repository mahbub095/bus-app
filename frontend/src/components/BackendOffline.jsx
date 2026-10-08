import React from 'react';

/**
 * Shown when the Laravel backend is unreachable (network error on /api/site-settings).
 * Gives the user a clear message and a retry button instead of a blank/broken page.
 */
export default function BackendOffline({ onRetry }) {
  return (
    <div className="backend-offline-page">
      <div className="backend-offline-card">
        <div className="backend-offline-icon">⚡</div>
        <h1 className="backend-offline-title">Cannot Connect to Server</h1>
        <p className="backend-offline-desc">
          Unable to reach the booking server. Please make sure the backend is running
          and try again.
        </p>
        <div className="backend-offline-hint">
          <code>php artisan serve</code>
          <span>— then refresh this page</span>
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
          padding: 48px 40px;
          max-width: 440px;
          width: 100%;
          text-align: center;
          box-shadow: 0 8px 32px rgba(0,0,0,.08);
        }
        .backend-offline-icon {
          font-size: 52px;
          margin-bottom: 20px;
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
          margin-bottom: 24px;
        }
        .backend-offline-hint {
          display: inline-flex;
          align-items: center;
          gap: 10px;
          background: var(--bg-main, #f8fafc);
          border: 1px solid var(--border-color, #e2e8f0);
          border-radius: 8px;
          padding: 10px 16px;
          margin-bottom: 28px;
          font-size: 13px;
          color: var(--text-secondary, #64748b);
        }
        .backend-offline-hint code {
          font-family: 'Fira Code', Consolas, monospace;
          font-size: 13px;
          color: var(--primary, #6366f1);
          font-weight: 600;
        }
        .backend-offline-retry {
          width: 100%;
          padding: 12px 20px;
          font-size: 14px;
        }
      `}</style>
    </div>
  );
}
