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

const API_BASE = 'http://localhost:8000/api';
const AUTH_TOKEN_KEY = 'sonyabus_auth_token';
const AUTH_USER_KEY = 'sonyabus_auth_user';

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

  // Site Settings (fetched from admin backend)
  const [siteSettings, setSiteSettings] = useState(null);

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
    setAuthForm({ name: '', email: '', password: '', password_confirmation: '' });
  };

  const handleAuthSubmit = async (e) => {
    e.preventDefault();
    setIsAuthLoading(true);

    const endpoint = authMode === 'register' ? '/auth/register' : '/auth/login';
    const body = authMode === 'register'
      ? authForm
      : { email: authForm.email, password: authForm.password };

    try {
      const res = await fetch(`${API_BASE}${endpoint}`, {
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
      if (authToken) {
        await fetch(`${API_BASE}/auth/logout`, {
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

  // Fetch site settings on mount
  const fetchSiteSettings = async () => {
    try {
      const res = await fetch(`${API_BASE}/site-settings`);
      if (res.ok) {
        const data = await res.json();
        setSiteSettings(data);
      }
    } catch (err) {
      // Silently fail — use defaults
    }
  };

  // Dynamic document title, favicon, and SEO meta tags
  useEffect(() => {
    if (!siteSettings) return;

    // Update document title
    if (siteSettings.site_title) {
      document.title = siteSettings.site_title;
    }

    // Update favicon
    if (siteSettings.favicon_url) {
      let link = document.querySelector("link[rel~='icon']");
      if (!link) {
        link = document.createElement('link');
        link.rel = 'icon';
        document.head.appendChild(link);
      }
      // If it's a relative URL, prepend the backend origin
      link.href = siteSettings.favicon_url.startsWith('http')
        ? siteSettings.favicon_url
        : `http://localhost:8000${siteSettings.favicon_url}`;
    }

    // Update SEO meta tags
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

      // Google Analytics
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
  }, [siteSettings]);

  useEffect(() => {
    fetchSiteSettings();

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
      apiBase: API_BASE,
      setVerificationStatus,
      setBookingSuccess,
      setPaymentFailed,
      showToast
    });
  }, []);

  useEffect(() => {
    if (!authToken) return;

    fetch(`${API_BASE}/auth/me`, {
      headers: { Accept: 'application/json', Authorization: `Bearer ${authToken}` }
    })
      .then(res => (res.ok ? res.json() : Promise.reject()))
      .then(data => {
        setAuthUser(data.user);
        localStorage.setItem(AUTH_USER_KEY, JSON.stringify(data.user));
      })
      .catch(() => clearAuth());
  }, [authToken]);

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
      />

      {/* Content */}
      <main style={{ flexGrow: 1, display: 'flex', flexDirection: 'column' }}>
        <VerificationStatus
          verificationStatus={verificationStatus}
          setVerificationStatus={setVerificationStatus}
          setBookingSuccess={setBookingSuccess}
          API_BASE={API_BASE}
        />

        {/* VIEW: HOME (BOOKING Portal) */}
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
              API_BASE={API_BASE}
            />
          )
        )}

        {/* VIEW: CANCEL TICKET */}
        {activeTab === 'cancel' && (
          <MyTickets
            authUser={authUser}
            authToken={authToken}
            clearAuth={clearAuth}
            openAuthModal={openAuthModal}
            setActiveTab={setActiveTab}
            showToast={showToast}
            API_BASE={API_BASE}
          />
        )}

        {/* VIEW: PROMOTIONS & OFFERS */}
        {activeTab === 'offers' && (
          <OffersList
            API_BASE={API_BASE}
            showToast={showToast}
          />
        )}

        {/* VIEW: PROFILE */}
        {activeTab === 'profile' && (
          <UserProfile
            authUser={authUser}
            authToken={authToken}
            clearAuth={clearAuth}
            openAuthModal={openAuthModal}
            setActiveTab={setActiveTab}
            showToast={showToast}
            API_BASE={API_BASE}
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
