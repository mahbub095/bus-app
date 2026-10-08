import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const apiTarget = env.VITE_API_BASE_URL?.replace(/\/api\/?$/, '') || 'http://localhost:8000'

  return {
    plugins: [react()],
    server: {
      proxy: {
        // During dev, proxy /api requests to the Laravel backend so CORS
        // and "port 8000" errors never appear in the browser.
        '/api': {
          target: apiTarget,
          changeOrigin: true,
          // Keep /api prefix — Laravel routes start with /api
        },
      },
    },
  }
})
