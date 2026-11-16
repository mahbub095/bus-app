# SonyaBus — Customer App (React 19 + Vite)

Customer-facing single page application for the SonyaBus booking system:
schedule search, gender-aware seat map, checkout, ZiniPay payment redirect and
"my tickets".

The app is a pure client of the Laravel API in `../backend`. There is no server
side rendering — deploy the generated `dist/` folder behind any static host.

---

## Requirements

- Node.js **20.19+** (Vite 8 and ESLint 10 require it)
- A running backend (see `../backend/README.md`)

## Configure

```bash
cp .env.example .env
```

```dotenv
# Laravel API root, including the /api suffix
VITE_API_BASE_URL=https://api.yourdomain.com/api

# ZiniPay public key (this value is bundled into the client)
VITE_ZINIPAY_API_KEY=your_key_here
```

> `VITE_*` variables are inlined into the production bundle. Never put server
> secrets here — only keys that are safe to expose in a browser.

## Develop

```bash
npm install
npm run dev      # http://localhost:5173
```

The dev server proxies `/api` and `/uploads` to the backend target derived from
`VITE_API_BASE_URL` (default `http://localhost:8000`). If the explicit target is
offline, `src/App.jsx` probes same-origin candidates such as
`/backend/public/api`, which covers typical Laragon/XAMPP subdirectory layouts.

## Build and deploy

```bash
npm run build    # outputs to dist/
npm run preview  # optional local check of the production build
```

Upload the contents of `dist/` to the domain (or subdomain) you set as
`APP_FRONTEND_URL` in the backend `.env`.

## Lint

```bash
npm run lint
```

## Source map

```
src/
├── App.jsx              # App shell, API base probing, tab routing
├── components/          # Search form, schedule list, seat map, checkout, tickets
├── bookingUtils.js      # Seat-code generation, fare and charge helpers
├── scheduleCache.js     # In-memory cache of schedule results
└── zinipay.js           # Payment create/verify client
```

## Troubleshooting

- **Blank page / "Backend unreachable"** — open the browser console and confirm
  `VITE_API_BASE_URL` includes `/api` and that the backend responds at
  `<backend>/api/site-settings`.
- **CORS errors** — `APP_FRONTEND_URL` in the backend `.env` must match the exact
  origin serving this app (scheme + host + port).
- **`npm run build` fails on Node 18** — upgrade to Node 20.19+.
