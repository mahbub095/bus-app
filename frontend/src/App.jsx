import { useState, useEffect, useRef } from 'react';
import './App.css';
import { ziniPayVerifyPayment } from './zinipay';

// Subcomponents
import Toast from './components/Toast';
import Navbar from './components/Navbar';
import AuthModal from './components/AuthModal';
import VerificationStatus from './components/VerificationStatus';
import SearchForm from './components/SearchForm';
import BookingSuccess from './components/BookingSuccess';
import ScheduleList from './components/ScheduleList';
import OffersList from './components/OffersList';
import UserProfile from './components/UserProfile';
import MyTickets from './components/MyTickets';

const API_BASE = 'http://localhost:8000/api';
const AUTH_TOKEN_KEY = 'sonyabus_auth_token';
const AUTH_USER_KEY = 'sonyabus_auth_user';

function App() {
  // Navigation & View Tabs
  const [activeTab, setActiveTab] = useState('home'); // home, cancel, offers, profile

  // Search States
  const [stations, setStations] = useState([]);
  const [searchParams, setSearchParams] = useState({
    from: '',
    to: '',
    date: new Date().toISOString().split('T')[0], // Default to today
    coachType: 'All'
  });
  const [searchResults, setSearchResults] = useState([]);
  const [searchDone, setSearchDone] = useState(false);
  const [isLoading, setIsLoading] = useState(false);

  // Booking & Selection States
  const [selectedSchedule, setSelectedSchedule] = useState(null);
  const [selectedSeats, setSelectedSeats] = useState([]);
  const [promoInput, setPromoInput] = useState('');
  const [appliedPromo, setAppliedPromo] = useState(null);
  const [passengerDetails, setPassengerDetails] = useState({
    name: '',
    phone: '',
    email: '',
    paymentMethod: 'ZiniPay'
  });
  const [boardingPoint, setBoardingPoint] = useState('');
  const [droppingPoint, setDroppingPoint] = useState('');
  const [passengerGender, setPassengerGender] = useState('M');
  const [seatMapLastSync, setSeatMapLastSync] = useState(null);
  const [isBooking, setIsBooking] = useState(false);
  const [bookingSuccess, setBookingSuccess] = useState(null);
  const [verificationStatus, setVerificationStatus] = useState(null);

  // Cancellation States
  const [cancelBookings, setCancelBookings] = useState([]);
  const [isSearchingCancel, setIsSearchingCancel] = useState(false);

  // Offers States
  const [offers, setOffers] = useState([]);
  const [isLoadingOffers, setIsLoadingOffers] = useState(false);

  // Toast Notification State
  const [toast, setToast] = useState({ show: false, message: '', type: 'success' });
  const toastTimeoutRef = useRef(null);

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
  const [profileForm, setProfileForm] = useState({
    current_password: '',
    password: '',
    password_confirmation: ''
  });
  const [isUpdatingProfile, setIsUpdatingProfile] = useState(false);

  // Site Settings (fetched from admin backend)
  const [siteSettings, setSiteSettings] = useState(null);

  // Min Date constraint for safety
  const minDateStr = new Date().toISOString().split('T')[0];

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
    setPassengerDetails(prev => ({
      ...prev,
      name: prev.name || user.name,
      email: user.email
    }));
  };

  const clearAuth = () => {
    setAuthUser(null);
    setAuthToken(null);
    setCancelBookings([]);
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

  const fetchMyBookings = async () => {
    if (!authToken) return;

    setIsSearchingCancel(true);
    try {
      const res = await fetch(`${API_BASE}/bookings/mine`, {
        headers: authHeaders()
      });
      if (res.status === 401) {
        clearAuth();
        showToast('Session expired. Please log in again.', 'error');
        return;
      }
      if (res.ok) {
        const data = await res.json();
        setCancelBookings(data);
      } else {
        showToast('Failed to load your tickets.', 'error');
      }
    } catch (err) {
      showToast('Error connecting to server.', 'error');
    } finally {
      setIsSearchingCancel(false);
    }
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
        if (activeTab === 'cancel') fetchMyBookings();
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

  const handleProfilePasswordSubmit = async (e) => {
    e.preventDefault();
    if (!requireAuth(() => setActiveTab('profile'))) return;

    if (profileForm.password !== profileForm.password_confirmation) {
      showToast('New password and confirmation do not match.', 'error');
      return;
    }

    setIsUpdatingProfile(true);
    try {
      const res = await fetch(`${API_BASE}/auth/password`, {
        method: 'POST',
        headers: authHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify(profileForm)
      });
      const data = await res.json();
      if (res.status === 401) {
        clearAuth();
        showToast('Session expired. Please log in again.', 'error');
        openAuthModal('login', () => setActiveTab('profile'));
        return;
      }
      if (res.ok) {
        if (data.user) {
          setAuthUser(data.user);
          localStorage.setItem(AUTH_USER_KEY, JSON.stringify(data.user));
        }
        setProfileForm({ current_password: '', password: '', password_confirmation: '' });
        showToast(data.message || 'Password updated successfully.', 'success');
      } else {
        const msg = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Failed to update password.');
        showToast(msg, 'error');
      }
    } catch (err) {
      showToast('Network error while updating password.', 'error');
    } finally {
      setIsUpdatingProfile(false);
    }
  };

  const requireAuth = (returnAction) => {
    if (authUser && authToken) {
      return true;
    }
    openAuthModal('login', returnAction);
    return false;
  };

  // Fetch Stations on Mount
  const fetchStations = async () => {
    try {
      const res = await fetch(`${API_BASE}/stations`);
      if (res.ok) {
        const data = await res.json();
        setStations(data);
      }
    } catch (err) {
      showToast('Cannot connect to Laravel backend. Verify it is active on port 8000.', 'error');
    }
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
    fetchStations();
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
    const urlParams = new URLSearchParams(window.location.search);
    const payment = urlParams.get('payment');
    const bookingId = urlParams.get('booking_id');
    const errorMsg = urlParams.get('error');
    const invoiceId = urlParams.get('invoiceId');

    if (invoiceId) {
      const verifyZiniPayInvoice = async (invId) => {
        setVerificationStatus({
          loading: true,
          invoiceId: invId,
          success: false,
          message: '',
          transactionId: '',
          bookingId: null
        });

        try {
          const apiKey = import.meta.env.VITE_ZINIPAY_API_KEY || "90e76eb23cdf5ec69fe8820a5007b8713844626087a8fb86";
          const response = await ziniPayVerifyPayment(invId, apiKey);
          console.log("response from verify payment:", response);

          if (response.status === "COMPLETED") {
            // Call backend webhook to update status in DB securely
            const updateRes = await fetch(`${API_BASE}/payment/webhook`, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
              },
              body: JSON.stringify({
                invoice_id: invId,
                status: 'COMPLETED'
              })
            });

            if (updateRes.ok) {
              const updateData = await updateRes.json();
              const bId = updateData.booking?.id || null;

              setVerificationStatus({
                loading: false,
                invoiceId: invId,
                success: true,
                message: 'Verified successfully!',
                transactionId: response.transaction_id || response.payment_id || 'N/A',
                bookingId: bId
              });
              showToast('Payment verified successfully!', 'success');
            } else {
              setVerificationStatus({
                loading: false,
                invoiceId: invId,
                success: false,
                message: 'Payment verified, but failed to update local database.',
                transactionId: '',
                bookingId: null
              });
              showToast('Failed to update booking status.', 'error');
            }
          } else {
            setVerificationStatus({
              loading: false,
              invoiceId: invId,
              success: false,
              message: response.message || `Payment status: ${response.status}`,
              transactionId: '',
              bookingId: null
            });
            showToast('Payment verification failed.', 'error');
          }
        } catch (err) {
          console.error("ZiniPay verification failed", err);
          setVerificationStatus({
            loading: false,
            invoiceId: invId,
            success: false,
            message: 'Error communicating with ZiniPay.',
            transactionId: '',
            bookingId: null
          });
          showToast('Verification failed.', 'error');
        }
        window.history.replaceState({}, document.title, window.location.pathname);
      };

      verifyZiniPayInvoice(invoiceId);
    } else if (payment === 'success' && bookingId) {
      fetch(`${API_BASE}/bookings/public/${bookingId}`)
        .then(res => {
          if (res.ok) return res.json();
          throw new Error('Failed to load transaction receipt details.');
        })
        .then(data => {
          setBookingSuccess(data);
          showToast('Payment completed successfully via ZiniPay!', 'success');
          window.history.replaceState({}, document.title, window.location.pathname);
        })
        .catch(err => {
          showToast(err.message, 'error');
        });
    } else if (payment === 'failed' && errorMsg) {
      showToast(`Payment failed: ${decodeURIComponent(errorMsg)}`, 'error');
      window.history.replaceState({}, document.title, window.location.pathname);
    }
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
        setPassengerDetails(prev => ({
          ...prev,
          name: prev.name || data.user.name,
          email: data.user.email
        }));
      })
      .catch(() => clearAuth());
  }, [authToken]);

  useEffect(() => {
    if (activeTab === 'cancel' && authToken) {
      fetchMyBookings();
    }
  }, [activeTab, authToken]);

  // Fetch Promotions when visiting offers tab
  const fetchOffers = async () => {
    setIsLoadingOffers(true);
    try {
      const res = await fetch(`${API_BASE}/promotions`);
      if (res.ok) {
        const data = await res.json();
        setOffers(data);
      }
    } catch (err) {
      showToast('Could not fetch promotions.', 'error');
    } finally {
      setIsLoadingOffers(false);
    }
  };

  useEffect(() => {
    if (activeTab === 'offers') {
      fetchOffers();
    }
  }, [activeTab]);

  // Handle Search Submission
  const handleSearch = async (e) => {
    e.preventDefault();
    if (!searchParams.from) {
      showToast('Please select a departure station', 'error');
      return;
    }
    if (!searchParams.to) {
      showToast('Please select a destination station', 'error');
      return;
    }
    if (searchParams.from === searchParams.to) {
      showToast('Departure and arrival stations cannot be the same', 'error');
      return;
    }
    if (!searchParams.date) {
      showToast('Please select a journey date', 'error');
      return;
    }

    setIsLoading(true);
    setSelectedSchedule(null);
    setSelectedSeats([]);
    setAppliedPromo(null);
    setPromoInput('');

    try {
      const params = new URLSearchParams({
        from: searchParams.from,
        to: searchParams.to,
        date: searchParams.date,
        coach_type: searchParams.coachType
      });
      const res = await fetch(`${API_BASE}/search?${params.toString()}`);
      if (res.ok) {
        const data = await res.json();
        setSearchResults(data);
        setSearchDone(true);
        if (data.length === 0) {
          showToast('No schedules found for this query.', 'error');
        } else {
          showToast(`Found ${data.length} schedules.`, 'success');
        }
      } else {
        showToast('Search failed. Please verify your parameters.', 'error');
      }
    } catch (err) {
      showToast('Error connecting to backend server.', 'error');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    const boardingPoints = selectedSchedule?.boarding_points || [];
    const droppingPoints = selectedSchedule?.dropping_points || [];

    setBoardingPoint((prev) => {
      if (prev && boardingPoints.some((bp) => bp.value === prev)) return prev;
      return boardingPoints[0]?.value || '';
    });
    setDroppingPoint((prev) => {
      if (prev && droppingPoints.some((dp) => dp.value === prev)) return prev;
      return '';
    });
  }, [selectedSchedule?.id]);

  useEffect(() => {
    if (!searchDone || !selectedSchedule) {
      return undefined;
    }

    const refreshSeatMap = async () => {
      try {
        const params = new URLSearchParams({
          from: searchParams.from,
          to: searchParams.to,
          date: searchParams.date,
          coach_type: searchParams.coachType,
        });
        const res = await fetch(`${API_BASE}/search?${params.toString()}`);
        if (!res.ok) return;

        const fresh = await res.json();
        setSearchResults(fresh);

        const updated = fresh.find(s => s.id === selectedSchedule.id);
        if (!updated) return;

        setSelectedSchedule(updated);
        setSeatMapLastSync(new Date());
      } catch {
        // ignore transient network errors during polling
      }
    };

    refreshSeatMap();
    const timer = setInterval(refreshSeatMap, 5000);
    return () => clearInterval(timer);
  }, [searchDone, selectedSchedule?.id, searchParams.from, searchParams.to, searchParams.date, searchParams.coachType]);

  // handleSeatClick is now managed directly inside ScheduleList component

  // Handle Promo Apply
  const handleApplyPromo = async (e) => {
    e.preventDefault();
    if (!promoInput.trim()) {
      showToast('Please enter a promo code', 'error');
      return;
    }

    try {
      const res = await fetch(`${API_BASE}/promotions/check?code=${promoInput.trim()}`);
      if (res.ok) {
        const data = await res.json();
        setAppliedPromo(data);
        showToast(`Promo ${data.code} applied! BDT ${data.discount_amount} discount.`, 'success');
      } else {
        const data = await res.json();
        showToast(data.message || 'Invalid promo code.', 'error');
        setAppliedPromo(null);
      }
    } catch (err) {
      showToast('Error validating promo code.', 'error');
    }
  };

  // Confirm booking
  const handleConfirmBooking = async (e) => {
    e.preventDefault();
    if (!requireAuth()) return;
    if (selectedSeats.length === 0) {
      showToast('Please select at least one seat.', 'error');
      return;
    }
    if (!passengerDetails.name.trim()) {
      showToast('Passenger name is required.', 'error');
      return;
    }
    if (!passengerDetails.phone.trim()) {
      showToast('Passenger phone number is required.', 'error');
      return;
    }
    const cleanPhone = passengerDetails.phone.replace(/\D/g, '');
    if (cleanPhone.length < 11) {
      showToast('Passenger phone number must be at least 11 digits.', 'error');
      return;
    }
    if (!passengerDetails.email.trim()) {
      showToast('Passenger email is required.', 'error');
      return;
    }
    if (!boardingPoint || !droppingPoint) {
      showToast('Please select boarding and dropping points.', 'error');
      return;
    }

    setIsBooking(true);
    try {
      const res = await fetch(`${API_BASE}/bookings`, {
        method: 'POST',
        headers: authHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({
          schedule_id: selectedSchedule.id,
          passenger_name: passengerDetails.name,
          passenger_phone: passengerDetails.phone,
          passenger_email: passengerDetails.email,
          passenger_gender: passengerGender,
          boarding_point: boardingPoint,
          dropping_point: droppingPoint,
          seat_numbers: selectedSeats.join(','),
          payment_method: passengerDetails.paymentMethod,
          promo_code: appliedPromo ? appliedPromo.code : null
        })
      });

      const data = await res.json();
      if (res.status === 401) {
        clearAuth();
        showToast('Please log in to book tickets.', 'error');
        openAuthModal('login');
        return;
      }
      if (res.ok) {
        if (data.payment_url) {
          window.location.href = data.payment_url;
          return;
        }
        setBookingSuccess(data.booking);
        showToast('Ticket reserved successfully!', 'success', 1000);
        // Reset inputs
        setSelectedSeats([]);
        setAppliedPromo(null);
        setPromoInput('');
        setPassengerDetails({
          name: authUser?.name || '',
          phone: '',
          email: authUser?.email || '',
          paymentMethod: 'ZiniPay'
        });
        
        // Refresh matching schedules in the background
        const params = new URLSearchParams({
          from: searchParams.from,
          to: searchParams.to,
          date: searchParams.date,
          coach_type: searchParams.coachType
        });
        const refreshRes = await fetch(`${API_BASE}/search?${params.toString()}`);
        if (refreshRes.ok) {
          const freshData = await refreshRes.json();
          setSearchResults(freshData);
          setSelectedSchedule(null);
        }
      } else {
        showToast(data.message || 'Seat booking failed. Please try again.', 'error');
      }
    } catch (err) {
      showToast('Network error during booking.', 'error');
    } finally {
      setIsBooking(false);
    }
  };

  // Cancel Booking action (owner only)
  const handleCancelBooking = async (bookingId) => {
    if (!requireAuth()) return;
    if (!window.confirm('Submit cancellation request for this ticket? Admin approval is required before final cancellation.')) {
      return;
    }

    try {
      const res = await fetch(`${API_BASE}/bookings/${bookingId}/cancel`, {
        method: 'POST',
        headers: authHeaders()
      });
      const data = await res.json();
      if (res.status === 401) {
        clearAuth();
        showToast('Please log in to cancel tickets.', 'error');
        openAuthModal('login');
        return;
      }
      if (res.status === 403) {
        showToast(data.message || 'You cannot cancel this ticket.', 'error');
        return;
      }
      if (res.ok) {
        showToast(data.message || 'Cancellation request submitted successfully.', 'success');
        setCancelBookings(prev => prev.map(b => b.id === bookingId ? { ...b, status: 'CANCEL_REQUESTED' } : b));
        
        // Refresh search results
        if (searchDone) {
          const params = new URLSearchParams({
            from: searchParams.from,
            to: searchParams.to,
            date: searchParams.date,
            coach_type: searchParams.coachType
          });
          const refreshRes = await fetch(`${API_BASE}/search?${params.toString()}`);
          if (refreshRes.ok) {
            const freshData = await refreshRes.json();
            setSearchResults(freshData);
          }
        }
      } else {
        showToast(data.message || 'Failed to cancel booking.', 'error');
      }
    } catch (err) {
      showToast('Network error during cancellation.', 'error');
    }
  };

  // Copy Promo to Clipboard
  const handleCopyCode = (code) => {
    navigator.clipboard.writeText(code);
    showToast(`Coupon code "${code}" copied to clipboard!`, 'success');
  };

  // Seat map rendering helpers have been extracted to SeatMap.jsx component

  // Formatting date strings helpers
  const formatTime = (isoString) => {
    if (!isoString) return '';
    const date = new Date(isoString);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  };

  const formatDate = (isoString) => {
    if (!isoString) return '';
    const date = new Date(isoString);
    return date.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
  };

  const ensureJsPdf = async () => {
    if (window.jspdf?.jsPDF) {
      return window.jspdf.jsPDF;
    }

    await new Promise((resolve, reject) => {
      const existing = document.querySelector('script[data-jspdf="true"]');
      if (existing) {
        existing.addEventListener('load', () => resolve(), { once: true });
        existing.addEventListener('error', () => reject(new Error('Failed to load PDF library.')), { once: true });
        return;
      }

      const script = document.createElement('script');
      script.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
      script.async = true;
      script.dataset.jspdf = 'true';
      script.onload = () => resolve();
      script.onerror = () => reject(new Error('Failed to load PDF library.'));
      document.body.appendChild(script);
    });

    if (!window.jspdf?.jsPDF) {
      throw new Error('PDF library is unavailable.');
    }

    return window.jspdf.jsPDF;
  };

  const handleDownloadTicketPdf = async (booking) => {
    try {
      const JsPdfClass = await ensureJsPdf();
      const doc = new JsPdfClass();

      const lines = [
        'SonyaBus - Ticket Invoice',
        `PNR: ${booking.pnr}`,
        `Passenger: ${booking.passenger_name}`,
        `Phone: ${booking.passenger_phone}`,
        `Email: ${booking.passenger_email}`,
        `From: ${booking.schedule?.route?.from || 'N/A'}`,
        `To: ${booking.schedule?.route?.to || 'N/A'}`,
        `Bus Name: ${booking.schedule?.bus?.operator_name || 'N/A'}`,
        `Coach Type: ${booking.schedule?.bus?.coach_type || 'N/A'}`,
        `Departure Date: ${formatDate(booking.schedule?.departure_time) || 'N/A'}`,
        `Departure Time: ${formatTime(booking.schedule?.departure_time) || 'N/A'}`,
        `Seats: ${booking.seat_numbers}`,
        `Payment Method: ${booking.payment_method}`,
        `Status: ${booking.status}`,
        `Total Fare: BDT ${Number(booking.total_fare || 0).toLocaleString()}`
      ];

      doc.setFontSize(14);
      doc.text(lines[0], 14, 18);
      doc.setFontSize(11);
      doc.text(lines.slice(1), 14, 30);

      const fileName = `ticket-${booking.pnr || booking.id}.pdf`;
      doc.save(fileName);
    } catch (err) {
      showToast('Unable to download PDF right now. Please try again.', 'error');
    }
  };

  // Maintenance Mode Page
  if (siteSettings?.maintenance?.enabled) {
    return (
      <div className="maintenance-page">
        <div className="maintenance-content">
          <div className="maintenance-icon-wrap">
            <div className="maintenance-icon">🔧</div>
            <div className="maintenance-pulse"></div>
          </div>
          <h1 className="maintenance-title">Under Maintenance</h1>
          <p className="maintenance-message">
            {siteSettings.maintenance.message || 'We are currently performing scheduled maintenance. Please check back soon.'}
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

  return (
    <>
      {/* Toast Notification */}
      <Toast toast={toast} />

      {/* Header navbar */}
      <Navbar
        activeTab={activeTab}
        setActiveTab={setActiveTab}
        setBookingSuccess={setBookingSuccess}
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
          <>
            {bookingSuccess ? (
              <BookingSuccess
                bookingSuccess={bookingSuccess}
                setBookingSuccess={setBookingSuccess}
                formatDate={formatDate}
                formatTime={formatTime}
              />
            ) : (
              <>
                <SearchForm
                  stations={stations}
                  searchParams={searchParams}
                  setSearchParams={setSearchParams}
                  minDateStr={minDateStr}
                  handleSearch={handleSearch}
                  isLoading={isLoading}
                />

                <ScheduleList
                  searchDone={searchDone}
                  searchResults={searchResults}
                  selectedSchedule={selectedSchedule}
                  setSelectedSchedule={setSelectedSchedule}
                  selectedSeats={selectedSeats}
                  setSelectedSeats={setSelectedSeats}
                  setAppliedPromo={setAppliedPromo}
                  setPromoInput={setPromoInput}
                  authUser={authUser}
                  openAuthModal={openAuthModal}
                  showToast={showToast}
                  stations={stations}
                  searchParams={searchParams}
                  boardingPoint={boardingPoint}
                  setBoardingPoint={setBoardingPoint}
                  droppingPoint={droppingPoint}
                  setDroppingPoint={setDroppingPoint}
                  passengerDetails={passengerDetails}
                  setPassengerDetails={setPassengerDetails}
                  passengerGender={passengerGender}
                  setPassengerGender={setPassengerGender}
                  promoInput={promoInput}
                  appliedPromo={appliedPromo}
                  handleApplyPromo={handleApplyPromo}
                  handleConfirmBooking={handleConfirmBooking}
                  isBooking={isBooking}
                  seatMapLastSync={seatMapLastSync}
                />

                {/* Info Visual Steps */}
                {!searchDone && (
                  <section className="features-section">
                    <div className="container">
                      <div className="section-title">
                        <h2>Travel Made Simple</h2>
                        <p>Reservation takes less than 2 minutes through our state-of-the-art visual blueprint system</p>
                      </div>

                      <div className="features-grid">
                        <div className="feature-card">
                          <div className="feature-icon-wrapper">🔍</div>
                          <h3>1. Search Routes</h3>
                          <p>Select your starting city, terminal arrival destination, and your scheduled travel date.</p>
                        </div>

                        <div className="feature-card">
                          <div className="feature-icon-wrapper">🎟️</div>
                          <h3>2. Interactive Layout</h3>
                          <p>Pick your seats visually using our simulated bus blueprint grid. See booked versus empty seats.</p>
                        </div>

                        <div className="feature-card">
                          <div className="feature-icon-wrapper">🚌</div>
                          <h3>3. Board Coach</h3>
                          <p>Get instant invoice with PNR code on screen. Pay securely and head directly to boarding point.</p>
                        </div>
                      </div>
                    </div>
                  </section>
                )}

                {/* Partners Logo */}
                <section className="payment-section">
                  <div className="container">
                    <p style={{ color: 'var(--text-secondary)', fontSize: '13px', textTransform: 'uppercase', letterSpacing: '1px' }}>
                      Secure Gateway Channels & Partners
                    </p>
                    <div className="payment-list">
                      <div className="payment-logo" style={{ color: '#E2136E', fontWeight: '800', fontSize: '20px', letterSpacing: '-0.5px' }}>
                        bKash
                      </div>
                      <div className="payment-logo" style={{ color: '#F05A24', fontWeight: '800', fontSize: '20px', letterSpacing: '-0.5px' }}>
                        NAGAD
                      </div>
                      <div className="payment-logo" style={{ color: '#1B6CA8', fontWeight: '800', fontSize: '20px', letterSpacing: '-0.5px' }}>
                        Visa / MasterCard
                      </div>
                      <div className="payment-logo" style={{ color: '#888', fontWeight: '600', fontSize: '16px' }}>
                        Rocket
                      </div>
                      <div className="payment-logo" style={{ color: '#888', fontWeight: '600', fontSize: '16px' }}>
                        Upay
                      </div>
                    </div>
                  </div>
                </section>
              </>
            )}
          </>
        )}

        {/* VIEW: CANCEL TICKET */}
        {activeTab === 'cancel' && (
          <MyTickets
            authUser={authUser}
            openAuthModal={openAuthModal}
            setActiveTab={setActiveTab}
            fetchMyBookings={fetchMyBookings}
            isSearchingCancel={isSearchingCancel}
            cancelBookings={cancelBookings}
            handleCancelBooking={handleCancelBooking}
            handleDownloadTicketPdf={handleDownloadTicketPdf}
            formatDate={formatDate}
            formatTime={formatTime}
          />
        )}

        {/* VIEW: PROMOTIONS & OFFERS */}
        {activeTab === 'offers' && (
          <OffersList
            isLoadingOffers={isLoadingOffers}
            offers={offers}
            handleCopyCode={handleCopyCode}
          />
        )}

        {/* VIEW: PROFILE */}
        {activeTab === 'profile' && (
          <UserProfile
            authUser={authUser}
            openAuthModal={openAuthModal}
            setActiveTab={setActiveTab}
            profileForm={profileForm}
            setProfileForm={setProfileForm}
            handleProfilePasswordSubmit={handleProfilePasswordSubmit}
            isUpdatingProfile={isUpdatingProfile}
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
      <footer className="app-footer">
        <div className="container">
          <div className="footer-logo">{siteSettings?.footer?.company_name || 'SonyaBus Enterprise'}</div>
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
                        authUser ? setActiveTab('profile') : openAuthModal('login', () => setActiveTab('profile'));
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
                <li onClick={() => { setActiveTab('home'); setBookingSuccess(null); }} style={{ cursor: 'pointer' }}>Search Buses</li>
                <li onClick={() => setActiveTab('cancel')} style={{ cursor: 'pointer' }}>My Tickets</li>
                <li onClick={() => setActiveTab('offers')} style={{ cursor: 'pointer' }}>Special Promotions</li>
                <li onClick={() => authUser ? setActiveTab('profile') : openAuthModal('login', () => setActiveTab('profile'))} style={{ cursor: 'pointer' }}>My Profile</li>
              </>
            )}
          </ul>
          <p>{siteSettings?.footer?.copyright || '© 2026 SonyaBus Enterprise Ltd. All rights reserved. Built with React + Laravel.'}</p>
        </div>
      </footer>
    </>
  );
}

export default App;
