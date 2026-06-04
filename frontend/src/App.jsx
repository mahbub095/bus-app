import { useState, useEffect, useRef } from 'react';
import './App.css';
import {
  SEAT_ROWS,
  SEAT_STATUS_LABELS,
  calcPricing,
  formatBdt,
  getSeatMap,
  isSeatSelectable,
} from './bookingUtils';

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
    paymentMethod: 'bKash'
  });
  const [boardingPoint, setBoardingPoint] = useState('');
  const [droppingPoint, setDroppingPoint] = useState('');
  const [passengerGender, setPassengerGender] = useState('M');
  const [seatMapLastSync, setSeatMapLastSync] = useState(null);
  const [isBooking, setIsBooking] = useState(false);
  const [bookingSuccess, setBookingSuccess] = useState(null);

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

  useEffect(() => {
    fetchStations();

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
        setSelectedSeats(prev => {
          const map = getSeatMap(updated);
          const next = prev.filter(seat => isSeatSelectable(map[seat]));
          if (next.length < prev.length) {
            window.alert('One or more selected seats were just booked by another user. Please choose again.');
          }
          return next;
        });
        setSeatMapLastSync(new Date());
      } catch {
        // ignore transient network errors during polling
      }
    };

    refreshSeatMap();
    const timer = setInterval(refreshSeatMap, 5000);
    return () => clearInterval(timer);
  }, [searchDone, selectedSchedule?.id, searchParams.from, searchParams.to, searchParams.date, searchParams.coachType]);

  // Handle seat click
  const handleSeatClick = (seatCode, status) => {
    if (!isSeatSelectable(status)) return;

    if (selectedSeats.includes(seatCode)) {
      setSelectedSeats(prev => prev.filter(s => s !== seatCode));
    } else {
      if (selectedSeats.length >= 4) {
        window.alert('You can select a maximum of 4 seats per booking.');
        return;
      }
      setSelectedSeats(prev => [...prev, seatCode]);
    }
  };

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
          paymentMethod: 'bKash'
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

  const renderSeatCell = (schedule, seat, seatMap) => {
    const status = seatMap[seat] || 'available';
    const isSelected = selectedSeats.includes(seat);
    const displayClass = isSelected ? 'selected' : `status-${status}`;
    const canSelect = isSeatSelectable(status);

    return (
      <div
        key={seat}
        className={`seat ${displayClass} ${canSelect || isSelected ? 'selectable' : ''}`}
        title={isSelected ? SEAT_STATUS_LABELS.selected : (SEAT_STATUS_LABELS[status] || status)}
        onClick={() => handleSeatClick(seat, status)}
      >
        {seat}
      </div>
    );
  };

  // Render Seat Grid helper
  const renderSeatMap = (schedule) => {
    const seatMap = getSeatMap(schedule);

    return (
      <div className="bus-blueprint">
        <div className="bus-head">
          <div className="driver-wheel" title="Driver Cabin">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
              <circle cx="12" cy="12" r="10" />
              <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
              <path d="M2 12h20" />
            </svg>
          </div>
          <span style={{ fontSize: '11px', color: 'var(--text-muted)', fontWeight: 'bold' }}>ENTRANCE</span>
        </div>

        <div className="bus-body-seats">
          {SEAT_ROWS.map(row => (
            <div className="seat-row" key={row}>
              <div className="seat-pair">
                {renderSeatCell(schedule, `${row}1`, seatMap)}
                {renderSeatCell(schedule, `${row}2`, seatMap)}
              </div>
              <div className="bus-aisle"></div>
              <div className="seat-pair">
                {renderSeatCell(schedule, `${row}3`, seatMap)}
                {renderSeatCell(schedule, `${row}4`, seatMap)}
              </div>
            </div>
          ))}
        </div>

        <div className="seat-legend">
          {['booked_m', 'booked_f', 'blocked', 'available', 'selected', 'sold_m', 'sold_f'].map(key => (
            <div className="legend-item" key={key}>
              <div className={`legend-dot status-${key}`}></div>
              <span>{SEAT_STATUS_LABELS[key]}</span>
            </div>
          ))}
        </div>
      </div>
    );
  };

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

  return (
    <>
      {/* Toast Notification */}
      {toast.show && (
        <div className={`toast-notification ${toast.type === 'error' ? 'error' : 'success'}`}>
          {toast.type === 'error' ? (
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
              <circle cx="12" cy="12" r="10" />
              <line x1="12" y1="8" x2="12" y2="12" />
              <line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
          ) : (
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
              <polyline points="22 4 12 14.01 9 11.01" />
            </svg>
          )}
          <span>{toast.message}</span>
        </div>
      )}

      {/* Header navbar */}
      <header className="app-header">
        <div className="container navbar">
          <div className="logo" onClick={() => { setActiveTab('home'); setBookingSuccess(null); }} style={{ cursor: 'pointer' }}>
            <div className="logo-icon">S</div>
            Sonya<span className="logo-accent">Bus</span>
          </div>
          <ul className="nav-menu">
            <li 
              className={`nav-link ${activeTab === 'home' ? 'active' : ''}`}
              onClick={() => { setActiveTab('home'); setBookingSuccess(null); }}
            >
              Ticket Booking
            </li>
            <li 
              className={`nav-link ${activeTab === 'cancel' ? 'active' : ''}`}
              onClick={() => setActiveTab('cancel')}
            >
              My Tickets
            </li>
            <li 
              className={`nav-link ${activeTab === 'offers' ? 'active' : ''}`}
              onClick={() => setActiveTab('offers')}
            >
              Promotions & Offers
            </li>
            <li
              className={`nav-link ${activeTab === 'profile' ? 'active' : ''}`}
              onClick={() => {
                if (!authUser) {
                  openAuthModal('login', () => setActiveTab('profile'));
                  return;
                }
                setActiveTab('profile');
              }}
            >
              My Profile
            </li>
          </ul>

          <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
            {authUser ? (
              <>
                <span style={{ fontSize: '13px', color: 'var(--text-secondary)' }}>
                  Hi, <strong style={{ color: '#fff' }}>{authUser.name}</strong>
                </span>
                <button className="btn btn-secondary" style={{ padding: '8px 14px', fontSize: '12px' }} onClick={handleLogout}>
                  Logout
                </button>
              </>
            ) : (
              <>
                <button className="btn btn-secondary" style={{ padding: '8px 14px', fontSize: '12px' }} onClick={() => openAuthModal('login')}>
                  Login
                </button>
                <button className="btn btn-primary" style={{ padding: '8px 14px', fontSize: '12px' }} onClick={() => openAuthModal('register')}>
                  Register
                </button>
              </>
            )}
          </div>
        </div>
      </header>

      {/* Content */}
      <main style={{ flexGrow: 1, display: 'flex', flexDirection: 'column' }}>
        
        {/* VIEW: HOME (BOOKING Portal) */}
        {activeTab === 'home' && (
          <>
            {bookingSuccess ? (
              <div className="container success-container">
                <div className="success-badge">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3">
                    <polyline points="20 6 9 17 4 12" />
                  </svg>
                  Reservation Secured Successfully!
                </div>
                
                <h2 className="banner-title" style={{ fontSize: '28px', color: '#fff' }}>Boarding Pass Invoice</h2>
                <p className="banner-subtitle" style={{ fontSize: '14px', marginBottom: '20px' }}>
                  Present this PNR code at the counter 15 minutes before departure.
                </p>

                <div className="ticket-wrapper">
                  <div className="ticket-header">
                    <div className="ticket-brand">SonyaBus Enterprise</div>
                    <div className="ticket-pnr">PNR: {bookingSuccess.pnr}</div>
                  </div>
                  
                  <div className="ticket-body">
                    <div className="ticket-row-grid">
                      <div className="ticket-field">
                        <span className="ticket-label">Passenger Name</span>
                        <span className="ticket-val">{bookingSuccess.passenger_name}</span>
                      </div>
                      <div className="ticket-field">
                        <span className="ticket-label">Contact Number</span>
                        <span className="ticket-val">{bookingSuccess.passenger_phone}</span>
                      </div>
                    </div>
                    
                    <div className="ticket-row-grid">
                      <div className="ticket-field">
                        <span className="ticket-label">From</span>
                        <span className="ticket-val">{bookingSuccess.schedule.route.from}</span>
                      </div>
                      <div className="ticket-field">
                        <span className="ticket-label">To</span>
                        <span className="ticket-val">{bookingSuccess.schedule.route.to}</span>
                      </div>
                    </div>

                    <div className="ticket-row-grid">
                      <div className="ticket-field">
                        <span className="ticket-label">Bus Name</span>
                        <span className="ticket-val">{bookingSuccess.schedule?.bus?.operator_name || 'N/A'}</span>
                      </div>
                      <div className="ticket-field">
                        <span className="ticket-label">Departure Date & Time</span>
                        <span className="ticket-val">
                          {formatDate(bookingSuccess.schedule.departure_time)} @ {formatTime(bookingSuccess.schedule.departure_time)}
                        </span>
                      </div>
                      <div className="ticket-field">
                        <span className="ticket-label">Coach Details</span>
                        <span className="ticket-val">
                          {bookingSuccess.schedule.bus.operator_name} ({bookingSuccess.schedule.bus.coach_type})
                        </span>
                      </div>
                    </div>

                    <div className="ticket-row-grid">
                      <div className="ticket-field">
                        <span className="ticket-label">Reserved Seats</span>
                        <span className="ticket-val" style={{ color: 'var(--primary)', fontWeight: 'bold' }}>
                          {bookingSuccess.seat_numbers}
                        </span>
                      </div>
                      <div className="ticket-field">
                        <span className="ticket-label">Estimated Duration</span>
                        <span className="ticket-val">{bookingSuccess.schedule.route.duration}</span>
                      </div>
                    </div>

                    <div className="ticket-divider"></div>

                    <div className="ticket-row-grid" style={{ marginBottom: '10px' }}>
                      <div className="ticket-field">
                        <span className="ticket-label">Payment Method</span>
                        <span className="ticket-val">{bookingSuccess.payment_method}</span>
                      </div>
                      <div className="ticket-field">
                        <span className="ticket-label">Reservation Status</span>
                        <span className="badge-status paid" style={{ width: 'max-content', marginTop: '4px' }}>
                          {bookingSuccess.status}
                        </span>
                      </div>
                    </div>

                    <div className="ticket-row-grid" style={{ marginBottom: '0' }}>
                      <div className="ticket-field">
                        <span className="ticket-label">Fare Summary</span>
                        <span className="ticket-val" style={{ fontSize: '18px', color: 'var(--gold)' }}>
                          BDT {bookingSuccess.total_fare.toLocaleString()}
                        </span>
                      </div>
                    </div>

                    {/* Virtual Barcode */}
                    <div className="ticket-barcode-wrap">
                      <div className="ticket-barcode">
                        <div className="barcode-line wide"></div>
                        <div className="barcode-line"></div>
                        <div className="barcode-line narrow"></div>
                        <div className="barcode-line wide"></div>
                        <div className="barcode-line narrow"></div>
                        <div className="barcode-line"></div>
                        <div className="barcode-line wide"></div>
                        <div className="barcode-line"></div>
                        <div className="barcode-line narrow"></div>
                        <div className="barcode-line wide"></div>
                        <div className="barcode-line"></div>
                        <div className="barcode-line narrow"></div>
                        <div className="barcode-line wide"></div>
                        <div className="barcode-line"></div>
                      </div>
                      <span className="barcode-number">PNR-{bookingSuccess.pnr}</span>
                    </div>

                  </div>
                </div>

                <div style={{ display: 'flex', justifyContent: 'center', gap: '15px' }}>
                  <button className="btn btn-secondary" onClick={() => window.print()}>
                    Print Boarding Ticket
                  </button>
                  <button className="btn btn-primary" onClick={() => setBookingSuccess(null)}>
                    Book Another Ticket
                  </button>
                </div>
              </div>
            ) : (
              <>
                {/* Search Banner Header */}
                <section className="search-banner">
                  <div className="container">
                    <h1 className="banner-title">Premium Bus Booking Portal</h1>
                    <p className="banner-subtitle">Search routes, visual seat grids, and get instant PNR confirmations</p>
                    
                    <div className="search-card">
                      <form className="search-form" onSubmit={handleSearch}>
                        
                        <div className="input-group">
                          <label htmlFor="from-station">From</label>
                          <div className="input-with-icon">
                            <span className="input-icon">📍</span>
                            <select 
                              id="from-station"
                              className="input-control" 
                              value={searchParams.from}
                              onChange={(e) => setSearchParams(prev => ({ ...prev, from: e.target.value }))}
                            >
                              <option value="">Select departure...</option>
                              {stations.map(st => (
                                <option key={st.id} value={st.id}>{st.name} ({st.district})</option>
                              ))}
                            </select>
                          </div>
                        </div>

                        <div className="input-group">
                          <label htmlFor="to-station">To</label>
                          <div className="input-with-icon">
                            <span className="input-icon">🏁</span>
                            <select 
                              id="to-station"
                              className="input-control" 
                              value={searchParams.to}
                              onChange={(e) => setSearchParams(prev => ({ ...prev, to: e.target.value }))}
                            >
                              <option value="">Select arrival...</option>
                              {stations.map(st => (
                                <option key={st.id} value={st.id}>{st.name} ({st.district})</option>
                              ))}
                            </select>
                          </div>
                        </div>

                        <div className="input-group">
                          <label htmlFor="journey-date">Date of Journey</label>
                          <div className="input-with-icon">
                            <span className="input-icon">📅</span>
                            <input 
                              id="journey-date"
                              type="date" 
                              className="input-control"
                              min={minDateStr}
                              value={searchParams.date}
                              onChange={(e) => setSearchParams(prev => ({ ...prev, date: e.target.value }))}
                            />
                          </div>
                        </div>

                        <div className="input-group">
                          <label htmlFor="coach-type">Coach Type</label>
                          <div className="input-with-icon">
                            <span className="input-icon">🚌</span>
                            <select 
                              id="coach-type"
                              className="input-control"
                              value={searchParams.coachType}
                              onChange={(e) => setSearchParams(prev => ({ ...prev, coachType: e.target.value }))}
                            >
                              <option value="All">All Coach Types</option>
                              <option value="AC">AC (Air Conditioned)</option>
                              <option value="Non AC">Non AC</option>
                            </select>
                          </div>
                        </div>

                      </form>
                      
                      <div style={{ marginTop: '20px', display: 'flex', justifyContent: 'flex-end' }}>
                        <button 
                          className="btn btn-primary search-submit-btn" 
                          style={{ maxWidth: '250px' }}
                          onClick={handleSearch}
                          disabled={isLoading}
                        >
                          {isLoading ? 'Searching...' : 'Search Buses'}
                        </button>
                      </div>
                    </div>
                  </div>
                </section>

                {/* Results listing */}
                {searchDone && (
                  <section className="results-container container">
                    <div className="results-header">
                      <h2 className="section-title" style={{ margin: '0', textAlign: 'left' }}>Available Coach Services</h2>
                      <span className="results-count">Showing {searchResults.length} schedules matches</span>
                    </div>

                    <div className="bus-list">
                      {searchResults.length === 0 ? (
                        <div className="search-card text-center" style={{ padding: '60px' }}>
                          <h3>No Coaches Scheduled</h3>
                          <p style={{ color: 'var(--text-secondary)', marginTop: '8px' }}>
                            We couldn't find any scheduled buses matching this criteria. Try selecting another date or location.
                          </p>
                        </div>
                      ) : (
                        searchResults.map(sched => {
                          const isExpanded = selectedSchedule?.id === sched.id;
                          return (
                            <div className="bus-card" key={sched.id}>
                              
                              <div className="bus-main-info">
                                <div className="operator-block">
                                  <span className="operator-name">{sched.bus.operator_name}</span>
                                  <span style={{ fontSize: '11px', color: 'var(--text-muted)' }}>Coach {sched.bus.coach_number}</span>
                                  <span className={`coach-tag ${sched.bus.coach_type === 'AC' ? 'ac' : ''}`}>
                                    {sched.bus.coach_type}
                                  </span>
                                </div>
                                
                                <div className="time-block">
                                  <span className="time-label">Departure</span>
                                  <span className="time-value">{formatTime(sched.departure_time)}</span>
                                  <span className="station-value">{searchParams.from ? stations.find(s => s.id === parseInt(searchParams.from))?.name : ''}</span>
                                </div>

                                <div className="time-block">
                                  <span className="time-label">Arrival</span>
                                  <span className="time-value">{formatTime(sched.arrival_time)}</span>
                                  <span className="station-value">{searchParams.to ? stations.find(s => s.id === parseInt(searchParams.to))?.name : ''}</span>
                                </div>

                                <div className="time-block">
                                  <span className="time-label">Duration</span>
                                  <span className="time-value" style={{ fontWeight: '500' }}>{sched.route.duration}</span>
                                  <span className="station-value" style={{ fontSize: '11px' }}>{sched.route.distance}</span>
                                </div>

                                <div className="seats-block">
                                  <span className="time-label">Seats Available</span>
                                  <span className="seats-count" style={{ color: sched.available_seats_count === 0 ? 'var(--danger)' : 'var(--success)' }}>
                                    {sched.available_seats_count} Seats
                                  </span>
                                </div>

                                <div className="price-block">
                                  <span className="time-label">Fare Price</span>
                                  <span className="price-amount">BDT {sched.fare.toLocaleString()}</span>
                                  <button 
                                    className={`btn ${isExpanded ? 'btn-secondary' : 'btn-primary'}`} 
                                    style={{ marginTop: '8px', padding: '6px 12px', fontSize: '12px' }}
                                    onClick={() => {
                                      if (!authUser) {
                                        openAuthModal('login');
                                        showToast('Login required to select seats and book tickets.', 'error');
                                        return;
                                      }
                                      if (isExpanded) {
                                        setSelectedSchedule(null);
                                        setSelectedSeats([]);
                                      } else {
                                        setSelectedSchedule(sched);
                                        setSelectedSeats([]);
                                      }
                                      setAppliedPromo(null);
                                      setPromoInput('');
                                    }}
                                  >
                                    {isExpanded ? 'Close Map' : (authUser ? 'Select Seats' : 'Login to Book')}
                                  </button>
                                </div>
                              </div>

                              {isExpanded && (
                                <div className="seats-selector-container">
                                  <div className="seat-selection-grid">
                                    
                                    <div>
                                      <h3 style={{ fontSize: '14px', marginBottom: '8px', color: 'var(--text-secondary)', textTransform: 'uppercase', letterSpacing: '1px' }}>
                                        Bus Seat Layout (Select Up To 4)
                                      </h3>
                                      {seatMapLastSync && (
                                        <div className="seat-map-live-status">
                                          <span className="live-dot"></span>
                                          <span>
                                            Live — updated {seatMapLastSync.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })} (every 5s)
                                          </span>
                                        </div>
                                      )}
                                      {renderSeatMap(sched)}
                                    </div>

                                    <div className="booking-form-sidebar">
                                      {(() => {
                                        const pricing = calcPricing(sched, Math.max(selectedSeats.length, 1), passengerDetails.paymentMethod);
                                        const seatClass = sched.seat_class || 'E-Class';
                                        const promoDiscount = appliedPromo ? Number(appliedPromo.discount_amount) : 0;
                                        const grandTotal = Math.max(0, pricing.total - promoDiscount);
                                        const activeBoarding = (sched.boarding_points || []).find(bp => bp.value === boardingPoint);
                                        const activeDropping = (sched.dropping_points || []).find(dp => dp.value === droppingPoint);
                                        return (
                                          <div className="ticket-booking-panel">
                                            <h3>Boarding / Dropping Point</h3>
                                            <label>Boarding Point *</label>
                                            <select
                                              className="ticket-field"
                                              value={boardingPoint}
                                              onChange={(e) => setBoardingPoint(e.target.value)}
                                              disabled={selectedSeats.length === 0}
                                            >
                                              {(sched.boarding_points || []).map(bp => (
                                                <option key={bp.id} value={bp.value}>
                                                  {bp.label} — Reporting: {bp.reporting_time || '—'}, Departure: {bp.departure_time || '—'}
                                                </option>
                                              ))}
                                            </select>
                                            <p className="boarding-point-info">
                                              {activeBoarding
                                                ? `Reporting: ${activeBoarding.reporting_time || '—'} · Departure: ${activeBoarding.departure_time || '—'}`
                                                : 'Select a boarding point'}
                                            </p>
                                            <label>Dropping Point *</label>
                                            <select
                                              className="ticket-field"
                                              value={droppingPoint}
                                              onChange={(e) => setDroppingPoint(e.target.value)}
                                              disabled={selectedSeats.length === 0}
                                            >
                                              <option value="">Select dropping point</option>
                                              {(sched.dropping_points || []).map(dp => (
                                                <option key={dp.id} value={dp.value}>
                                                  {dp.label} — Arrival: {dp.arrival_time || '—'}
                                                </option>
                                              ))}
                                            </select>
                                            <p className="boarding-point-info">
                                              {activeDropping
                                                ? `Estimated arrival: ${activeDropping.arrival_time || '—'}`
                                                : 'Select a dropping point'}
                                            </p>
                                            <label>Mobile Number *</label>
                                            <input
                                              type="tel"
                                              className="ticket-field"
                                              placeholder="01XXXXXXXXX"
                                              value={passengerDetails.phone}
                                              onChange={(e) => setPassengerDetails(prev => ({ ...prev, phone: e.target.value }))}
                                              disabled={selectedSeats.length === 0}
                                            />
                                            <label>Passenger Name *</label>
                                            <input
                                              type="text"
                                              className="ticket-field"
                                              placeholder="Full name"
                                              value={passengerDetails.name}
                                              onChange={(e) => setPassengerDetails(prev => ({ ...prev, name: e.target.value }))}
                                              disabled={selectedSeats.length === 0}
                                            />
                                            <label>Email *</label>
                                            <input
                                              type="email"
                                              className="ticket-field"
                                              placeholder="email@example.com"
                                              value={passengerDetails.email}
                                              onChange={(e) => setPassengerDetails(prev => ({ ...prev, email: e.target.value }))}
                                              disabled={selectedSeats.length === 0}
                                            />
                                            <label>Gender</label>
                                            <select
                                              className="ticket-field"
                                              value={passengerGender}
                                              onChange={(e) => setPassengerGender(e.target.value)}
                                              disabled={selectedSeats.length === 0}
                                            >
                                              <option value="M">Male</option>
                                              <option value="F">Female</option>
                                            </select>
                                            <label>Payment Method</label>
                                            <div className="payment-toggle-group" style={{ marginBottom: '12px' }}>
                                              {['bKash', 'Nagad', 'Card'].map(method => (
                                                <div
                                                  key={method}
                                                  className={`payment-toggle ${passengerDetails.paymentMethod === method ? 'active' : ''}`}
                                                  onClick={() => selectedSeats.length > 0 && setPassengerDetails(prev => ({ ...prev, paymentMethod: method }))}
                                                >
                                                  {method}
                                                </div>
                                              ))}
                                            </div>
                                            <form className="coupon-field" onSubmit={handleApplyPromo} style={{ marginBottom: '8px' }}>
                                              <input
                                                type="text"
                                                placeholder="Promo code"
                                                className="coupon-input"
                                                value={promoInput}
                                                onChange={(e) => setPromoInput(e.target.value)}
                                                disabled={selectedSeats.length === 0}
                                              />
                                              <button className="btn btn-secondary btn-coupon-apply" type="submit" disabled={selectedSeats.length === 0}>Apply</button>
                                            </form>
                                            {appliedPromo && (
                                              <p style={{ fontSize: '11px', color: 'var(--success)', marginBottom: '8px' }}>
                                                Promo: -{formatBdt(appliedPromo.discount_amount)}
                                              </p>
                                            )}
                                            <button
                                              type="button"
                                              className="btn-ticket-submit"
                                              onClick={handleConfirmBooking}
                                              disabled={selectedSeats.length === 0 || isBooking}
                                            >
                                              {isBooking ? 'Processing...' : 'Submit'}
                                            </button>
                                            <h4>Seat Information</h4>
                                            <table className="seat-info-table">
                                              <thead>
                                                <tr><th>Seats</th><th>Class</th><th>Fare</th></tr>
                                              </thead>
                                              <tbody>
                                                {selectedSeats.length > 0 ? selectedSeats.map(seat => (
                                                  <tr key={seat}>
                                                    <td>{seat}</td>
                                                    <td>{seatClass}</td>
                                                    <td>{formatBdt(sched.fare)}</td>
                                                  </tr>
                                                )) : (
                                                  <tr><td colSpan={3} style={{ fontStyle: 'italic', color: '#9ca3af' }}>Select seat(s) from the map</td></tr>
                                                )}
                                              </tbody>
                                            </table>
                                            <div className="fare-breakdown">
                                              <div className="fare-line"><span>Seat Fare:</span><strong>{formatBdt(pricing.seatFare)}</strong></div>
                                              <div className="fare-line"><span>Service Charge:</span><strong>{formatBdt(pricing.serviceCharge)}</strong></div>
                                              <div className="fare-line"><span>Gateway Charge:</span><strong>{formatBdt(pricing.gatewayCharge)}</strong></div>
                                              <div className="fare-line"><span>SC Discount:</span><strong>{formatBdt(pricing.scDiscount)}</strong></div>
                                              <div className="fare-line"><span>GC Discount:</span><strong>{formatBdt(pricing.gcDiscount)}</strong></div>
                                              {promoDiscount > 0 && (
                                                <div className="fare-line"><span>Promo Discount:</span><strong>-{formatBdt(promoDiscount)}</strong></div>
                                              )}
                                              <div className="fare-line fare-total"><span>Total Payable:</span><strong>{formatBdt(grandTotal)}</strong></div>
                                            </div>
                                          </div>
                                        );
                                      })()}
                                    </div>
                                  </div>
                                </div>
                              )}

                            </div>
                          );
                        })
                      )}
                    </div>
                  </section>
                )}

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
          <div className="container" style={{ flexGrow: 1, padding: '40px 0' }}>
            <div className="cancel-card">
              <h2 className="cancel-title">My Tickets & Cancellations</h2>
              <p className="cancel-desc">
                Sign in to view tickets you purchased online. You can only cancel your own reservations.
              </p>

              {!authUser ? (
                <div style={{ textAlign: 'center', padding: '30px 0' }}>
                  <p style={{ color: 'var(--text-secondary)', marginBottom: '16px' }}>
                    Please log in to access your ticket dashboard.
                  </p>
                  <button className="btn btn-primary" onClick={() => openAuthModal('login', () => setActiveTab('cancel'))}>
                    Login to Continue
                  </button>
                </div>
              ) : (
                <>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px', flexWrap: 'wrap', gap: '10px' }}>
                    <span style={{ fontSize: '13px', color: 'var(--text-secondary)' }}>
                      Signed in as <strong style={{ color: '#fff' }}>{authUser.email}</strong>
                    </span>
                    <button className="btn btn-secondary btn-sm" onClick={fetchMyBookings} disabled={isSearchingCancel}>
                      {isSearchingCancel ? 'Refreshing...' : 'Refresh My Tickets'}
                    </button>
                  </div>

                  <div className="cancellation-preview">
                    {isSearchingCancel ? (
                      <div className="loading-spinner"></div>
                    ) : cancelBookings.length > 0 ? (
                      cancelBookings.map(b => (
                        <div 
                          key={b.id} 
                          style={{ 
                            border: '1px solid var(--border-color)', 
                            borderRadius: '12px', 
                            padding: '20px', 
                            backgroundColor: '#111124',
                            marginBottom: '15px'
                          }}
                        >
                          <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '10px' }}>
                            <span style={{ fontWeight: 'bold', fontSize: '15px' }}>Ticket PNR: {b.pnr}</span>
                            <span className={`badge-status ${b.status === 'PAID' ? 'paid' : (b.status === 'CANCEL_REQUESTED' ? 'pending' : 'cancelled')}`}>{b.status}</span>
                          </div>

                          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '10px', fontSize: '13px', color: 'var(--text-secondary)' }}>
                            <div>Passenger: <strong style={{ color: '#fff' }}>{b.passenger_name}</strong></div>
                            <div>Phone: <strong style={{ color: '#fff' }}>{b.passenger_phone}</strong></div>
                            <div>From: <strong style={{ color: '#fff' }}>{b.schedule.route.from}</strong></div>
                            <div>To: <strong style={{ color: '#fff' }}>{b.schedule.route.to}</strong></div>
                            <div>Bus Name: <strong style={{ color: '#fff' }}>{b.schedule?.bus?.operator_name || 'N/A'}</strong></div>
                            <div>Date: <strong style={{ color: '#fff' }}>{formatDate(b.schedule.departure_time)}</strong></div>
                            <div>Departure: <strong style={{ color: '#fff' }}>{formatTime(b.schedule.departure_time)}</strong></div>
                            <div>Seats Reserved: <strong style={{ color: 'var(--primary)' }}>{b.seat_numbers}</strong></div>
                            <div>Fare paid: <strong style={{ color: 'var(--gold)' }}>BDT {b.total_fare.toLocaleString()}</strong></div>
                          </div>

                          {b.status === 'PAID' ? (
                            <div style={{ marginTop: '20px' }}>
                              <div className="cancellation-refund-info">
                                <strong>Notice:</strong> You can submit a cancellation request for admin verification. After approval, the ticket is cancelled and refund is processed to your {b.payment_method} account.
                              </div>
                              <button
                                className="btn btn-secondary w-full"
                                style={{ marginBottom: '10px' }}
                                onClick={() => handleDownloadTicketPdf(b)}
                              >
                                Download Ticket PDF
                              </button>
                              <button 
                                className="btn btn-danger w-full"
                                onClick={() => handleCancelBooking(b.id)}
                              >
                                Submit Cancellation Request
                              </button>
                            </div>
                          ) : b.status === 'CANCEL_REQUESTED' ? (
                            <div style={{ marginTop: '15px', color: '#FBBF24', fontSize: '12px', fontStyle: 'italic', textAlign: 'center' }}>
                              Cancellation request submitted. Waiting for admin approval.
                              <div style={{ marginTop: '10px' }}>
                                <button
                                  className="btn btn-secondary w-full"
                                  onClick={() => handleDownloadTicketPdf(b)}
                                >
                                  Download Ticket PDF
                                </button>
                              </div>
                            </div>
                          ) : (
                            <div style={{ marginTop: '15px' }}>
                              <div style={{ color: 'var(--text-muted)', fontSize: '12px', fontStyle: 'italic', textAlign: 'center' }}>
                                This reservation was cancelled. Refund has been processed.
                              </div>
                              <div style={{ marginTop: '10px' }}>
                                <button
                                  className="btn btn-secondary w-full"
                                  onClick={() => handleDownloadTicketPdf(b)}
                                >
                                  Download Ticket PDF
                                </button>
                              </div>
                            </div>
                          )}
                        </div>
                      ))
                    ) : (
                      <div style={{ color: 'var(--text-muted)', fontSize: '13px', textAlign: 'center', marginTop: '20px' }}>
                        You have no ticket bookings yet. Book a ticket from the home page while logged in.
                      </div>
                    )}
                  </div>
                </>
              )}

            </div>
          </div>
        )}

        {/* VIEW: PROMOTIONS & OFFERS */}
        {activeTab === 'offers' && (
          <div className="container" style={{ flexGrow: 1, padding: '40px 0' }}>
            <div className="section-title">
              <h2>Discount Coupon Offers</h2>
              <p>Apply these promotional codes during checkout to secure special flat pricing discounts</p>
            </div>

            {isLoadingOffers ? (
              <div className="loading-spinner"></div>
            ) : (
              <div className="offers-grid">
                {offers.length === 0 ? (
                  <div style={{ gridColumn: '1 / -1', textAlign: 'center', color: 'var(--text-secondary)' }}>
                    No promotional coupons are available at the moment.
                  </div>
                ) : (
                  offers.map(promo => (
                    <div className="offer-card" key={promo.id}>
                      <div className="offer-header">
                        <div className="offer-badge">Flat Discount</div>
                        <div className="offer-discount">BDT {promo.discount_amount} Off</div>
                      </div>
                      
                      <div className="offer-body">
                        <p className="offer-desc">{promo.description}</p>
                        <div className="coupon-pill">
                          <span className="coupon-code">{promo.code}</span>
                          <span className="coupon-copy-btn" onClick={() => handleCopyCode(promo.code)}>
                            Copy Code
                          </span>
                        </div>
                      </div>
                    </div>
                  ))
                )}
              </div>
            )}
          </div>
        )}

        {/* VIEW: PROFILE */}
        {activeTab === 'profile' && (
          <div className="container" style={{ flexGrow: 1, padding: '40px 0' }}>
            <div className="cancel-card">
              <h2 className="cancel-title">My Profile</h2>
              {!authUser ? (
                <div style={{ textAlign: 'center', padding: '30px 0' }}>
                  <p style={{ color: 'var(--text-secondary)', marginBottom: '16px' }}>
                    Please log in to manage your account password.
                  </p>
                  <button className="btn btn-primary" onClick={() => openAuthModal('login', () => setActiveTab('profile'))}>
                    Login to Continue
                  </button>
                </div>
              ) : (
                <div style={{ maxWidth: '560px', margin: '0 auto' }}>
                  <p style={{ color: 'var(--text-secondary)', marginBottom: '18px' }}>
                    Signed in as <strong style={{ color: '#fff' }}>{authUser.email}</strong>
                  </p>
                  <form onSubmit={handleProfilePasswordSubmit} className="booking-form-fields">
                    <div className="input-group">
                      <label>Current Password</label>
                      <input
                        type="password"
                        className="coupon-input"
                        value={profileForm.current_password}
                        onChange={(e) => setProfileForm(prev => ({ ...prev, current_password: e.target.value }))}
                        required
                        minLength={6}
                        autoComplete="current-password"
                      />
                    </div>
                    <div className="input-group">
                      <label>New Password</label>
                      <input
                        type="password"
                        className="coupon-input"
                        value={profileForm.password}
                        onChange={(e) => setProfileForm(prev => ({ ...prev, password: e.target.value }))}
                        required
                        minLength={6}
                        autoComplete="new-password"
                      />
                    </div>
                    <div className="input-group">
                      <label>Confirm New Password</label>
                      <input
                        type="password"
                        className="coupon-input"
                        value={profileForm.password_confirmation}
                        onChange={(e) => setProfileForm(prev => ({ ...prev, password_confirmation: e.target.value }))}
                        required
                        minLength={6}
                        autoComplete="new-password"
                      />
                    </div>
                    <button className="btn btn-primary" type="submit" disabled={isUpdatingProfile}>
                      {isUpdatingProfile ? 'Updating Password...' : 'Update Password'}
                    </button>
                  </form>
                </div>
              )}
            </div>
          </div>
        )}

      </main>

      {/* Auth Modal */}
      {showAuthModal && (
        <div className="modal-overlay" onClick={closeAuthModal}>
          <div className="auth-modal" onClick={(e) => e.stopPropagation()}>
            <button className="modal-close-btn" type="button" onClick={closeAuthModal}>&times;</button>
            <h2 style={{ fontFamily: 'var(--font-display)', marginBottom: '8px' }}>
              {authMode === 'register' ? 'Create Account' : 'Customer Login'}
            </h2>
            <p style={{ color: 'var(--text-secondary)', fontSize: '13px', marginBottom: '20px' }}>
              {authMode === 'register'
                ? 'Register to book and manage your bus tickets online.'
                : 'Sign in to purchase tickets and cancel your own bookings.'}
            </p>

            <div className="auth-tabs">
              <div
                className={`auth-tab ${authMode === 'login' ? 'active' : ''}`}
                onClick={() => setAuthMode('login')}
              >
                Login
              </div>
              <div
                className={`auth-tab ${authMode === 'register' ? 'active' : ''}`}
                onClick={() => setAuthMode('register')}
              >
                Register
              </div>
            </div>

            <form onSubmit={handleAuthSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '14px', textAlign: 'left' }}>
              {authMode === 'register' && (
                <div className="input-group">
                  <label>Full Name</label>
                  <input
                    type="text"
                    className="coupon-input"
                    value={authForm.name}
                    onChange={(e) => setAuthForm(prev => ({ ...prev, name: e.target.value }))}
                    required
                  />
                </div>
              )}
              <div className="input-group">
                <label>Email Address</label>
                <input
                  type="email"
                  className="coupon-input"
                  value={authForm.email}
                  onChange={(e) => setAuthForm(prev => ({ ...prev, email: e.target.value }))}
                  required
                />
              </div>
              <div className="input-group">
                <label>Password</label>
                <input
                  type="password"
                  className="coupon-input"
                  value={authForm.password}
                  onChange={(e) => setAuthForm(prev => ({ ...prev, password: e.target.value }))}
                  required
                  minLength={6}
                />
              </div>
              {authMode === 'register' && (
                <div className="input-group">
                  <label>Confirm Password</label>
                  <input
                    type="password"
                    className="coupon-input"
                    value={authForm.password_confirmation}
                    onChange={(e) => setAuthForm(prev => ({ ...prev, password_confirmation: e.target.value }))}
                    required
                    minLength={6}
                  />
                </div>
              )}
              <button className="btn btn-primary w-full" type="submit" disabled={isAuthLoading}>
                {isAuthLoading ? 'Please wait...' : (authMode === 'register' ? 'Create Account' : 'Sign In')}
              </button>
            </form>
          </div>
        </div>
      )}

      {/* Footer */}
      <footer className="app-footer">
        <div className="container">
          <div className="footer-logo">SonyaBus Enterprise</div>
          <ul className="footer-links">
            <li onClick={() => { setActiveTab('home'); setBookingSuccess(null); }} style={{ cursor: 'pointer' }}>Search Buses</li>
            <li onClick={() => setActiveTab('cancel')} style={{ cursor: 'pointer' }}>My Tickets</li>
            <li onClick={() => setActiveTab('offers')} style={{ cursor: 'pointer' }}>Special Promotions</li>
            <li onClick={() => authUser ? setActiveTab('profile') : openAuthModal('login', () => setActiveTab('profile'))} style={{ cursor: 'pointer' }}>My Profile</li>
          </ul>
          <p>© 2026 SonyaBus Enterprise Ltd. All rights reserved. Built with React + Laravel.</p>
        </div>
      </footer>
    </>
  );
}

export default App;
