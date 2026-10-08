import { useState, useEffect, useRef } from 'react';
import './App.css';
import { handleZiniPayRedirect } from './zinipay';

// Subcomponents
import Toast from './components/Toast';
import Navbar from './components/Navbar';
import AuthModal from './components/AuthModal';
import VerificationStatus from './components/VerificationStatus';
import OffersList from './components/OffersList';
import UserProfile from './components/UserProfile';
import MyTickets from './components/MyTickets';
import Footer from './components/Footer';
import BookingPortal from './components/BookingPortal';
import Maintenance from './components/Maintenance';
import PaymentFailed from './components/PaymentFailed';
import BackendOffline from './components/BackendOffline';

const AUTH_TOKEN_KEY = 'sonyabus_auth_token';
const AUTH_USER_KEY = 'sonyabus_auth_user';
const API_BASE_CACHE_KEY = 'sonyabus_api_base';

/**
 * Ordered list of API base URLs to try. The first URL that returns a
 * successful network response wins and is cached for future visits.
 *
 * Priority order:
 *   1. Explicit env-configured URL (VITE_API_BASE_URL) — respected 1st
 *   2. Same-origin relative `/api` — works for Laragon/Apache/Nginx
 *      deployments where backend & frontend share a domain (e.g.
 *      backend served from / and /api routes, or frontend built into
 *      the backend's public folder).
 *   3. Common local backend URLs — covers the most frequent dev setups:
 *        - http://localhost:8000/api  (php artisan serve default)
 *        - http://127.0.0.1:8000/api
 *        - /backend/public/api        (subdirectory Laragon-style layout
 *                                      c:\laragon\www\bus-app\backend\public)
 *        - /bus-app/backend/public/api (exact Laragon www-root layout)
 */
const buildApiBaseCandidates = () => {
  const origin = typeof window !== 'undefined' ? window.location.origin : '';
  const candidates = [];

  const explicit = import.meta.env.VITE_API_BASE_URL;
  if (explicit && String(explicit).trim() !== '') {
    candidates.push(String(explicit).replace(/\/$/, ''));
  }

  candidates.push(`${origin}/api`);
  candidates.push('http://localhost:8000/api');
  candidates.push('http://127.0.0.1:8000/api');
  candidates.push(`${origin}/backend/public/api`);
  candidates.push(`${origin}/bus-app/backend/public/api`);

  // De-duplicate while preserving order
  const seen = new Set();
  return candidates.filter(u => {
    const clean = u.toLowerCase();
    if (seen.has(clean)) return false;
    seen.add(clean);
    return true;
  });
};

/**
 * Attempt a HEAD / GET probe against `/site-settings` endpoint using
 * each candidate base URL in order. Return the first working base URL.
 */
const probeApiBase = async (candidates) => {
  for (const base of candidates) {
    try {
      const probeUrl = `${base.replace(/\/$/, '')}/site-settings`;
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), 3500);
      const res = await fetch(probeUrl, {
        method: 'GET',
        headers: { Accept: 'application/json' },
        signal: controller.signal,
        credentials: 'omit',
        mode: 'cors',
      });
      clearTimeout(timeoutId);

      // Any HTTP response (even 4xx/5xx) means the server is reachable.
      // Network-layer errors throw (and are caught below).
      return base;
    } catch (err) {
      // try next candidate
    }
  }
  return null;
};

function App() {
  // Navigation & View Tabs
  const [activeTab, setActiveTab] = useState('home'); // home, cancel, offers, profile

  // Toast Notification State
  const [toast, setToast] = useState({ show: false, message: '', type: 'success' });
  const toastTimeoutRef = useRef(null);

  // Booking & Verification success states (shared for navbar/footer integration)
  const [bookingSuccess, setBookingSuccess] = useState(null);
  const [verificationStatus, setVerificationStatus] = useState(null);
  const [paymentFailed, setPaymentFailed] = useState(null);

  // Auth States
  const [authUser, setAuthUser] = useState(null);
  const [authToken, setAuthToken] = useState(null);
  const [showAuthModal, setShowAuthModal] = useState(false);
  const [authMode, setAuthMode] = useState('login');
  const [authForm, setAuthForm] = useState({
    name: '',
    email: '',
    password: '',
    password_confirmation: ''
  });
  const [isAuthLoading, setIsAuthLoading] = useState(false);
  const [authReturnAction, setAuthReturnAction] = useState(null);
  const [devResetCode, setDevResetCode] = useState(null);

  // Site Settings (fetched from admin backend)
  const [siteSettings, setSiteSettings] = useState(null);
  // true when /api/site-settings network request fails (backend is down)
  const [backendOffline, setBackendOffline] = useState(false);
  // true when backend returns installed=false → install wizard not complete
  const [installNotComplete, setInstallNotComplete] = useState(false);
  const [installRedirectUrl, setInstallRedirectUrl] = useState(null);

  // Resolved API base URL — starts null, populated after probing
  const [apiBase, setApiBase] = useState(null);
  const [probingBackend, setProbingBackend] = useState(true);
  const [triedCandidates, setTriedCandidates] = useState([]);

  // Show Toast Helper (durationMs defaults to 4.5s; booking success uses 1s)
  const showToast = (message, type = 'success', durationMs = 4500) => {
    if (toastTimeoutRef.current) {
      clearTimeout(toastTimeoutRef.current);
    }
    setToast({ show: true, message, type });
    toastTimeoutRef.current = setTimeout(() => {
      setToast({ show: false, message: '', type: 'success' });
      toastTimeoutRef.current = null;
    }, durationMs);
  };

  useEffect(() => () => {
    if (toastTimeoutRef.current) {
      clearTimeout(toastTimeoutRef.current);
    }
  }, []);

  const authHeaders = (extra = {}) => {
    const headers = { 'Accept': 'application/json', ...extra };
    if (authToken) {
      headers['Authorization'] = `Bearer ${authToken}`;
    }
    return headers;
  };

  const persistAuth = (user, token) => {
    setAuthUser(user);
    setAuthToken(token);
    localStorage.setItem(AUTH_USER_KEY, JSON.stringify(user));
    localStorage.setItem(AUTH_TOKEN_KEY, token);
  };

  const clearAuth = () => {
    setAuthUser(null);
    setAuthToken(null);
    localStorage.removeItem(AUTH_USER_KEY);
    localStorage.removeItem(AUTH_TOKEN_KEY);
  };

  const openAuthModal = (mode = 'login', returnAction = null) => {
    setAuthMode(mode);
    setAuthReturnAction(returnAction);
    setShowAuthModal(true);
  };

  const closeAuthModal = () => {
    setShowAuthModal(false);
    setAuthReturnAction(null);
    setDevResetCode(null);
    setAuthForm({ name: '', email: '', password: '', password_confirmation: '' });
  };

  // ─── Authenticated API Wrappers ─────────────────────────────────────

  const handleAuthSubmit = async (e) => {
    e.preventDefault();
    if (!apiBase) return;
    setIsAuthLoading(true);

    if (authMode === 'forgot') {
      try {
        const res = await fetch(`${apiBase}/auth/forgot-password`, {
          method: 'POST',
          headers: authHeaders({ 'Content-Type': 'application/json' }),
          body: JSON.stringify({ email: authForm.email })
        });
        const data = await res.json();
        if (res.ok) {
          showToast(data.message || 'Reset code sent to your email.', 'success');
          setDevResetCode(data.code ?? null);
          setAuthMode('reset');
          setAuthForm(prev => ({ ...prev, password: '', password_confirmation: '', code: '' }));
        } else {
          const msg = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Request failed.');
          showToast(msg, 'error');
        }
      } catch (err) {
        showToast('Network error.', 'error');
      } finally {
        setIsAuthLoading(false);
      }
      return;
    }

    if (authMode === 'reset') {
      try {
        const res = await fetch(`${apiBase}/auth/reset-password`, {
          method: 'POST',
          headers: authHeaders({ 'Content-Type': 'application/json' }),
          body: JSON.stringify({
            email: authForm.email,
            code: authForm.code,
            password: authForm.password,
            password_confirmation: authForm.password_confirmation
          })
        });
        const data = await res.json();
        if (res.ok) {
          showToast(data.message || 'Password reset successfully.', 'success');
          setDevResetCode(null);
          setAuthMode('login');
          setAuthForm(prev => ({ ...prev, password: '', password_confirmation: '', code: '' }));
        } else {
          const msg = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Reset failed.');
          showToast(msg, 'error');
        }
      } catch (err) {
        showToast('Network error.', 'error');
      } finally {
        setIsAuthLoading(false);
      }
      return;
    }

    const endpoint = authMode === 'register' ? '/auth/register' : '/auth/login';
    const body = authMode === 'register' ? authForm : { email: authForm.email, password: authForm.password };

    try {
      const res = await fetch(`${apiBase}${endpoint}`, {
        method: 'POST',
        headers: authHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify(body)
      });
      const data = await res.json();

      if (res.ok) {
        persistAuth(data.user, data.token);
        showToast(data.message || 'Welcome!', 'success');
        const returnAction = authReturnAction;
        closeAuthModal();
        if (typeof returnAction === 'function') returnAction();
      } else {
        const msg = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Authentication failed.');
        showToast(msg, 'error');
      }
    } catch (err) {
      showToast('Network error during authentication.', 'error');
    } finally {
      setIsAuthLoading(false);
    }
  };

  const handleLogout = async () => {
    try {
      if (authToken && apiBase) {
        await fetch(`${apiBase}/auth/logout`, {
          method: 'POST',
          headers: authHeaders()
        });
      }
    } catch (err) {
      // ignore network errors on logout
    }
    clearAuth();
    showToast('Logged out successfully.', 'success');
  };

  // ─── Site Settings Fetch ────────────────────────────────────────────

  const fetchSiteSettings = async () => {
    if (!apiBase) return;
    try {
      const res = await fetch(`${apiBase}/site-settings`);
      if (res.ok) {
        const data = await res.json();
        setSiteSettings(data);
        setBackendOffline(false);
        setInstallNotComplete(false);
      } else if (res.status === 403) {
        // Install / license gate response — parse for known payloads
        try {
          const data = await res.json();
          if (data.installed === false) {
            // Backend is reachable but install wizard not yet run
            setInstallNotComplete(true);
            setInstallRedirectUrl(data.redirect || null);
            setBackendOffline(false);
            return;
          }
          // License-gated response → treat as backend available but
          // backendOffline flag doesn't apply; handled at route level.
        } catch (_) { /* JSON parse failed, fall through */ }
        setBackendOffline(true);
      } else {
        setBackendOffline(true);
      }
    } catch (err) {
      setBackendOffline(true);
    }
  };

  // ─── Probe API base on first mount ──────────────────────────────────

  useEffect(() => {
    let cancelled = false;

    const runProbe = async () => {
      // 1) cached hit (fast path)
      const cached = localStorage.getItem(API_BASE_CACHE_KEY);
      if (cached) {
        // Verify cached URL is still reachable before trusting
        const ok = await probeApiBase([cached]);
        if (ok && !cancelled) {
          setApiBase(ok);
          localStorage.setItem(API_BASE_CACHE_KEY, ok);
          setProbingBackend(false);
          return;
        }
        // cache stale → clean up and fall through to full probe
        localStorage.removeItem(API_BASE_CACHE_KEY);
      }

      // 2) full probe through candidate list
      const candidates = buildApiBaseCandidates();
      setTriedCandidates(candidates);
      const resolved = await probeApiBase(candidates);
      if (cancelled) return;

      if (resolved) {
        localStorage.setItem(API_BASE_CACHE_KEY, resolved);
        setApiBase(resolved);
        setBackendOffline(false);
      } else {
        setBackendOffline(true);
      }
      setProbingBackend(false);
    };

    runProbe();
    return () => { cancelled = true; };
  }, []);

  // ─── Fetch site settings as soon as apiBase becomes available ──────

  useEffect(() => {
    if (!apiBase) return;

    fetchSiteSettings();

    // Poll every 30 s so logo/branding changes propagate live
    const settingsInterval = setInterval(fetchSiteSettings, 30_000);

    // Auth restore only happens after apiBase is resolved
    const savedToken = localStorage.getItem(AUTH_TOKEN_KEY);
    const savedUser = localStorage.getItem(AUTH_USER_KEY);
    if (savedToken && savedUser) {
      setAuthToken(savedToken);
      try {
        setAuthUser(JSON.parse(savedUser));
      } catch (err) {
        clearAuth();
      }
    }

    // Process ZiniPay redirection parameters
    handleZiniPayRedirect({
      apiBase,
      setVerificationStatus,
      setBookingSuccess,
      setPaymentFailed,
      showToast
    });

    return () => clearInterval(settingsInterval);
  }, [apiBase]);

  // Re-fetch /auth/me to confirm a restored token is still valid
  useEffect(() => {
    if (!authToken || !apiBase) return;

    fetch(`${apiBase}/auth/me`, {
      headers: { Accept: 'application/json', Authorization: `Bearer ${authToken}` }
    })
      .then(res => (res.ok ? res.json() : Promise.reject()))
      .then(data => {
        setAuthUser(data.user);
        localStorage.setItem(AUTH_USER_KEY, JSON.stringify(data.user));
      })
      .catch(() => clearAuth());
  }, [authToken, apiBase]);

  // Dynamic document title, favicon, and SEO meta tags
  useEffect(() => {
    if (!siteSettings || !apiBase) return;

    if (siteSettings.site_title) {
      document.title = siteSettings.site_title;
    }

    if (siteSettings.favicon_url) {
      const backendOrigin = apiBase.replace(/\/api\/?$/, '');

      const rawUrl = siteSettings.favicon_url;
      const absoluteUrl = rawUrl.startsWith('http')
        ? rawUrl
        : `${backendOrigin}${rawUrl}`;

      const isUploadedFile = rawUrl.startsWith('/uploads/');
      const faviconHref = isUploadedFile
        ? `${absoluteUrl}?v=${Date.now()}`
        : absoluteUrl;

      document.querySelectorAll("link[rel~='icon'], link[rel~='shortcut']").forEach(el => el.remove());

      const link = document.createElement('link');
      link.rel = 'icon';
      const ext = rawUrl.split('.').pop().toLowerCase();
      const mime = { svg: 'image/svg+xml', ico: 'image/x-icon', png: 'image/png',
                     jpg: 'image/jpeg', jpeg: 'image/jpeg', gif: 'image/gif', webp: 'image/webp' };
      if (mime[ext]) link.type = mime[ext];
      link.href = faviconHref;
      document.head.appendChild(link);
    }

    const seo = siteSettings.seo;
    if (seo) {
      const setMeta = (name, content, property = false) => {
        if (!content) return;
        const attr = property ? 'property' : 'name';
        let tag = document.querySelector(`meta[${attr}="${name}"]`);
        if (!tag) {
          tag = document.createElement('meta');
          tag.setAttribute(attr, name);
          document.head.appendChild(tag);
        }
        tag.setAttribute('content', content);
      };

      setMeta('description', seo.meta_description);
      setMeta('keywords', seo.meta_keywords);
      setMeta('og:title', seo.og_title, true);
      setMeta('og:description', seo.og_description, true);
      setMeta('og:image', seo.og_image, true);
      setMeta('og:type', 'website', true);

      if (seo.google_analytics_id && !document.getElementById('ga-script')) {
        const script1 = document.createElement('script');
        script1.id = 'ga-script';
        script1.async = true;
        script1.src = `https://www.googletagmanager.com/gtag/js?id=${seo.google_analytics_id}`;
        document.head.appendChild(script1);

        const script2 = document.createElement('script');
        script2.textContent = `
          window.dataLayer = window.dataLayer || [];
          function gtag(){dataLayer.push(arguments);}
          gtag('js', new Date());
          gtag('config', '${seo.google_analytics_id}');
        `;
        document.head.appendChild(script2);
      }
    }
  }, [siteSettings, apiBase]);

  const retryBackendConnection = async () => {
    setProbingBackend(true);
    localStorage.removeItem(API_BASE_CACHE_KEY);
    const candidates = buildApiBaseCandidates();
    setTriedCandidates(candidates);
    const resolved = await probeApiBase(candidates);
    if (resolved) {
      localStorage.setItem(API_BASE_CACHE_KEY, resolved);
      setApiBase(resolved);
      setBackendOffline(false);
    } else {
      setBackendOffline(true);
    }
    setProbingBackend(false);
  };

  // Still probing for backend — show simple loading state
  if (probingBackend) {
    return (
      <div className="backend-offline-page">
        <div className="backend-offline-card" style={{ textAlign: 'center' }}>
          <div className="backend-offline-icon">🔄</div>
          <h1 className="backend-offline-title">Connecting to server…</h1>
          <p className="backend-offline-desc">
            Detecting backend location. This takes a few seconds.
          </p>
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
          .backend-offline-icon { font-size: 52px; margin-bottom: 20px; animation: pulse 1.4s infinite; }
          @keyframes pulse { 0%,100% { opacity: 1 } 50% { opacity: .4 } }
          .backend-offline-title { font-size: 22px; font-weight: 800; color: #1e293b; margin-bottom: 12px; }
          .backend-offline-desc { font-size: 14px; color: #64748b; line-height: 1.6; }
        `}</style>
      </div>
    );
  }

  // Install wizard NOT COMPLETED YET — backend is reachable but install
  // wizard Step 4 (Finalize) has never been run. All routes are locked by
  // the server; show a call-to-action with a direct link to /install.
  if (installNotComplete) {
    const backendOrigin = apiBase ? apiBase.replace(/\/api\/?$/, '') : window.location.origin;
    const installUrl = installRedirectUrl || `${backendOrigin}/install`;
    return (
      <div className="backend-offline-page">
        <div className="backend-offline-card">
          <div className="backend-offline-icon">🧩</div>
          <h1 className="backend-offline-title">Installation Not Complete</h1>
          <p className="backend-offline-desc">
            The SonyaBus booking engine has been detected, but the 4-step
            install wizard has not been completed yet. You must finish the
            installation process before the website, admin panel, or any
            other routes will work.
          </p>
          <div style={{
            background: '#fff7ed',
            border: '1px solid #fdba74',
            borderRadius: 8,
            padding: '10px 14px',
            margin: '8px 0 22px 0',
            fontSize: 13,
            color: '#9a3412',
            textAlign: 'left',
            lineHeight: 1.6,
          }}>
            <div style={{ fontWeight: 700, marginBottom: 4 }}>🔒 All routes locked:</div>
            <div>• Customer frontend booking ❌</div>
            <div>• Admin panel (<code>/admin</code>) ❌</div>
            <div>• Authentication &amp; API ❌</div>
            <div style={{ marginTop: 4, fontWeight: 600 }}>• Install wizard (<a href={installUrl} target="_blank" rel="noreferrer" style={{ color: '#c2410c', textDecoration: 'underline' }}>/install</a>) — ✅ Only this works right now</div>
          </div>
          <a
            href={installUrl}
            target="_blank"
            rel="noreferrer"
            className="btn btn-primary backend-offline-retry"
            style={{
              display: 'inline-block',
              textDecoration: 'none',
              textAlign: 'center',
            }}
          >
            🚀 Start Install Wizard
          </a>
          <button
            className="btn btn-secondary"
            onClick={retryBackendConnection}
            style={{ marginTop: 10, width: '100%', fontSize: 13, padding: '10px 18px' }}
          >
            ↻ Refresh Status (after install finishes)
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
            max-width: 520px;
            width: 100%;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0,0,0,.08);
          }
          .backend-offline-icon { font-size: 52px; margin-bottom: 18px; }
          .backend-offline-title { font-size: 22px; font-weight: 800; color: #1e293b; margin-bottom: 12px; }
          .backend-offline-desc { font-size: 14px; color: #64748b; line-height: 1.6; margin-bottom: 18px; }
          .backend-offline-retry {
            width: 100%;
            padding: 12px 20px;
            font-size: 14px;
            font-weight: 600;
            display: block;
          }
          .btn-primary {
            background: linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%);
            color: #fff;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all .15s ease;
          }
          .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 8px 22px rgba(99,102,241,.35); }
          .btn-secondary {
            background: #fff;
            color: #475569;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            cursor: pointer;
            transition: all .15s ease;
          }
          .btn-secondary:hover { background: #f8fafc; }
          code {
            background: #f1f5f9;
            padding: 1px 6px;
            border-radius: 4px;
            font-family: "Fira Code", Consolas, monospace;
            font-size: 12px;
            color: #0f172a;
          }
        `}</style>
      </div>
    );
  }

  // Backend unreachable
  if (backendOffline) {
    return (
      <BackendOffline
        onRetry={retryBackendConnection}
        candidates={triedCandidates}
        envUrl={import.meta.env.VITE_API_BASE_URL || ''}
      />
    );
  }

  // Maintenance Mode Page
  if (siteSettings?.maintenance?.enabled) {
    return <Maintenance siteSettings={siteSettings} />;
  }

  return (
    <>
      {/* Toast Notification */}
      <Toast toast={toast} />

      {/* Header navbar */}
      <Navbar
        activeTab={activeTab}
        setActiveTab={setActiveTab}
        setBookingSuccess={setBookingSuccess}
        setPaymentFailed={setPaymentFailed}
        authUser={authUser}
        handleLogout={handleLogout}
        openAuthModal={openAuthModal}
        siteSettings={siteSettings}
      />

      {/* Content */}
      <main style={{ flexGrow: 1, display: 'flex', flexDirection: 'column' }}>
        <VerificationStatus
          verificationStatus={verificationStatus}
          setVerificationStatus={setVerificationStatus}
          setBookingSuccess={setBookingSuccess}
          API_BASE={apiBase}
        />

        {activeTab === 'home' && (
          paymentFailed ? (
            <PaymentFailed
              paymentFailed={paymentFailed}
              setPaymentFailed={setPaymentFailed}
              setActiveTab={setActiveTab}
            />
          ) : (
            <BookingPortal
              bookingSuccess={bookingSuccess}
              setBookingSuccess={setBookingSuccess}
              verificationStatus={verificationStatus}
              setVerificationStatus={setVerificationStatus}
              authUser={authUser}
              authToken={authToken}
              clearAuth={clearAuth}
              openAuthModal={openAuthModal}
              showToast={showToast}
              API_BASE={apiBase}
            />
          )
        )}

        {activeTab === 'cancel' && (
          <MyTickets
            authUser={authUser}
            authToken={authToken}
            clearAuth={clearAuth}
            openAuthModal={openAuthModal}
            setActiveTab={setActiveTab}
            showToast={showToast}
            API_BASE={apiBase}
          />
        )}

        {activeTab === 'offers' && (
          <OffersList
            API_BASE={apiBase}
            showToast={showToast}
          />
        )}

        {activeTab === 'profile' && (
          <UserProfile
            authUser={authUser}
            authToken={authToken}
            clearAuth={clearAuth}
            openAuthModal={openAuthModal}
            setActiveTab={setActiveTab}
            showToast={showToast}
            API_BASE={apiBase}
            setAuthUser={setAuthUser}
            AUTH_USER_KEY={AUTH_USER_KEY}
          />
        )}
      </main>

      {/* Auth Modal */}
      <AuthModal
        showAuthModal={showAuthModal}
        closeAuthModal={closeAuthModal}
        authMode={authMode}
        setAuthMode={setAuthMode}
        authForm={authForm}
        setAuthForm={setAuthForm}
        handleAuthSubmit={handleAuthSubmit}
        isAuthLoading={isAuthLoading}
        devResetCode={devResetCode}
      />

      {/* Footer */}
      <Footer
        siteSettings={siteSettings}
        setActiveTab={setActiveTab}
        setBookingSuccess={setBookingSuccess}
        authUser={authUser}
        openAuthModal={openAuthModal}
      />
    </>
  );
}

export default App;
