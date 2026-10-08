import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const apiTarget = env.VITE_API_BASE_URL?.replace(/\/api\/?$/, '') || 'http://localhost:8000'

  // Fallback backend locations — when the explicit VITE_API_BASE_URL target is offline
  // (e.g. user forgot to start `php artisan serve`), we ALSO proxy these
  // additional candidate URLs so that Laragon / Apache-based setups "just work" in
  // dev mode without requiring a hardcoded URL override.
  const laragonOriginCandidates = [
    'http://localhost',
    'http://127.0.0.1',
  ];

  return {
    plugins: [react()],
    server: {
      host: true,      // allow connections from local network (phone testing)
      port: 5173,
      proxy: {
        // Primary /api → the explicit or default backend
        '/api': {
          target: apiTarget,
          changeOrigin: true,
          configure: (proxy) => {
            proxy.on('error', (err, req, res) => {
              // When primary target is unreachable, don't crash the Vite
              // server — let the frontend's fallback-probe logic take over
              // (it'll try the same-origin window.location.origin/api path).
              try {
                if (res && !res.headersSent) {
                  res.writeHead(502, { 'Content-Type': 'application/json' });
                  res.end(JSON.stringify({ error: 'Backend proxy target unreachable: ' + apiTarget }));
                }
              } catch (_) { /* ignore */ }
            });
          },
        },

        // Uploads proxy — mirrors Laravel's storage /uploads so uploaded logos/favicons in dev.
        '/uploads': {
          target: apiTarget,
          changeOrigin: true,
        },

        // ── Laragon sub-directory fallback routes ──────────────────
        // Covers the common `c:\laragon\www\bus-app\backend\public` layout
        // where the backend lives at a URL path `/bus-app/backend/public` instead.
        // Without these, the frontend's "auto fallback to same-origin /api" works,
        // then hits these entries mirror the same paths so Vite's dev server can proxy
        // without requiring `php artisan serve`.
        '/backend/public/api': {
          target: laragonOriginCandidates[0],
          changeOrigin: true,
          rewrite: (p) => p.replace(/^\/backend\/public/, ''),
        },
        '/bus-app/backend/public/api': {
          target: laragonOriginCandidates[0],
          changeOrigin: true,
          rewrite: (p) => p.replace(/^\/bus-app\/backend\/public/, ''),
        },
        '/backend/public/uploads': {
          target: laragonOriginCandidates[0],
          changeOrigin: true,
          rewrite: (p) => p.replace(/^\/backend\/public/, ''),
        },
      },
    },
  };
});
