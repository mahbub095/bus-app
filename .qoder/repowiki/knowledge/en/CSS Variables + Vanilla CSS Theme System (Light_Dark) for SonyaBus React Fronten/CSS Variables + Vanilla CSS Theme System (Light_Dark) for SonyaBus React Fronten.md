---
kind: frontend_style
name: CSS Variables + Vanilla CSS Theme System (Light/Dark) for SonyaBus React Frontend
category: frontend_style
scope:
    - '**'
source_files:
    - frontend/src/theme-variables.css
    - frontend/src/index.css
    - frontend/src/App.css
    - frontend/src/main.jsx
    - frontend/package.json
    - frontend/vite.config.js
---

## What system/approach is used

The SonyaBus customer-facing frontend (`frontend/`) is a **React 19 + Vite** SPA that uses **vanilla CSS** exclusively — no CSS-in-JS, no Tailwind, no component library. Styling is organized around:

- A single global stylesheet `src/index.css` (~2180 lines) that contains all layout, component, and utility styles.
- A dedicated design-token file `src/theme-variables.css` that defines CSS custom properties for colors, typography, spacing, shadows, and radii under `:root` and `[data-theme="dark"]`.
- Per-component JSX files in `src/components/` that apply class names matching the selectors in `index.css`.
- Google Fonts loaded via `@import` at the top of `index.css` (`Inter` for body text, `Outfit` for display headings).

There is no build-time CSS processor beyond Vite's default CSS handling; there are no SCSS/Sass/Less files, no PostCSS plugins, and no Tailwind config.

## Key files and packages

- `frontend/src/theme-variables.css` — Design tokens: color palette (`--primary`, `--accent`, `--gold`, `--success`, `--danger`, `--warning`), backgrounds per surface (`--bg-main`, `--bg-card`, `--bg-input`, `--bg-header`, `--bg-seat-map`, …), borders, text roles (`--text-primary`, `--text-secondary`, `--text-muted`, `--text-inverse`), gradients, shadows (`--shadow-sm/md/lg/neon`), border radii (`--border-radius/sm/lg`), transitions, and fonts (`--font-sans`, `--font-display`).
- `frontend/src/index.css` — All UI styles: reset, scrollbar, `.container`, header/navbar, search banner, feature cards, bus result rows, seat map blueprint, booking panel, payment toggle, coupon field, etc.
- `frontend/src/App.css` — Empty placeholder to avoid conflicts with `index.css`.
- `frontend/src/main.jsx` — Imports `theme-variables.css` before any other styles so variables are available globally.
- `frontend/package.json` — Dependencies are only `react` and `react-dom`; dev deps are `vite`, `@vitejs/plugin-react`, and ESLint. No styling-related dependencies.
- `frontend/vite.config.js` — Dev server proxy configuration only; no CSS transforms or plugin hooks.

## Architecture and conventions

### Design tokens
All visual values go through CSS custom properties defined in `theme-variables.css`. Components never hardcode brand colors directly — they reference `var(--primary)`, `var(--bg-card)`, `var(--border-color)`, `var(--shadow-md)`, etc. This centralizes the palette and makes theming possible by swapping variable values.

### Light / Dark theme
Two complete token sets exist:
- `:root, [data-theme="light"]` — light mode defaults.
- `[data-theme="dark"]` — dark mode overrides.

Theme switching is driven by toggling the `data-theme` attribute on the document root. The two palettes share the same variable names but swap concrete values (e.g. `--bg-main` goes from `#F4F6FB` to `#0B0B14`; `--text-primary` from `#0F172A` to `#F3F4F6`; `--shadow-*` become opaque black shadows in dark mode). There is also a `color-scheme` declaration per theme for native form controls.

### Typography
- Body text uses `--font-sans` = `'Inter'` (weights 300–800).
- Display/headings use `--font-display` = `'Outfit'` (weights 400–800).
- Fonts are loaded via a single Google Fonts `@import` at the top of `index.css`.

### Layout approach
- A fixed `.container` class enforces `max-width: 1200px` with `margin: 0 auto` and `padding: 0 24px`.
- Grid-based layouts are used for the search form (4-column → 2-column → 1-column via media queries at 900px and 600px), feature cards (`auto-fit, minmax(280px, 1fr)`), bus result rows, and seat selection grid (2-column → 1-column at 900px).
- Flexbox is used for navbar, buttons, badges, and small inline components.

### Component class naming
Class names follow a flat BEM-like convention without a preprocessor: `.app-header`, `.navbar`, `.logo`, `.nav-link`, `.btn`, `.btn-primary`, `.btn-secondary`, `.btn-danger`, `.search-banner`, `.search-card`, `.bus-card`, `.seat`, `.ticket-booking-panel`, `.payment-toggle-group`, etc. State variants are expressed as additional classes (e.g. `.seat.selected`, `.seat.status-available`, `.seat.status-booked_m`, `.payment-toggle.active`).

### Responsive strategy
Responsive breakpoints are written as plain `@media (max-width: ...)` rules inside `index.css` rather than using a breakpoint system or CSS framework. Observed breakpoints are 900px and 600px.

### Animations and effects
- A shared `--transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1)` variable drives hover/focus transitions.
- Custom keyframes include `slideDown` for the expandable seats section and `pulse-live` for the live seat-map indicator.
- Backdrop blur is applied to the sticky header (`backdrop-filter: blur(12px)`).
- Gradients are used for primary buttons (`linear-gradient(135deg, var(--primary), var(--accent))`), hero background glows, title text (`background-clip: text`), offer cards, and maintenance page titles.

### Inline styles caveat
While most styling is in CSS, some JSX components (notably `SeatMap.jsx` and `Navbar.jsx`) still use inline `style={{...}}` objects for dynamic sizing, positioning, and state-driven colors. These inline styles bypass the design-token system and should be migrated to CSS classes for consistency.

### Admin dashboard styling (separate concern)
The Laravel admin side (`backend/resources/views/admin/partials/`) has its own Blade partials (`fonts.blade.php`, `styles.blade.php`, `theme-init.blade.php`, `theme-variables.blade.php`, `theme-toggle.blade.php`) which are separate from the React frontend's CSS system.

## Conventions and constraints observed

- **No CSS framework**: Tailwind, Bootstrap, Material UI, Chakra, Ant Design, styled-components, Emotion, Sass, Less, and CSS modules are not used in the frontend.
- **Single source of truth for tokens**: Colors, fonts, radii, shadows, and transitions are declared in `theme-variables.css` and consumed via `var(--name)` throughout `index.css`.
- **Dark mode is opt-in via attribute**: The app reads/writes `data-theme` on the document root; components do not detect theme themselves.
- **Fonts are imported once**: `Inter` and `Outfit` are loaded via one Google Fonts `@import` at the top of `index.css`.
- **Component styles live in `index.css`**, not alongside JSX files — there is no co-located CSS module or CSS-in-JS per component.
- **Responsive breakpoints are ad-hoc media queries** at 900px and 600px, not derived from a design-system token.
- **Brand palette**: Primary `#6366F1` (indigo), accent `#8B5CF6` (purple), gold `#D97706` (amber), success `#059669`, danger `#DC2626`, muted slate tones for text/borders.
- **Border radius scale**: `--border-radius-sm: 8px`, `--border-radius: 12px`, `--border-radius-lg: 20px`.
- **Shadow scale**: `--shadow-sm`, `--shadow-md`, `--shadow-lg`, plus a branded `--shadow-neon` glow.