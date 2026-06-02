import { useState, useEffect } from 'react';
import './App.css';

const API_BASE = 'http://localhost:8000/api';

function App() {
  // Navigation & View Tabs
  const [activeTab, setActiveTab] = useState('home'); // home, cancel, offers

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
  const [isBooking, setIsBooking] = useState(false);
  const [bookingSuccess, setBookingSuccess] = useState(null);

  // Cancellation States
  const [cancelQuery, setCancelQuery] = useState('');
  const [cancelBookings, setCancelBookings] = useState([]);
  const [isSearchingCancel, setIsSearchingCancel] = useState(false);

  // Offers States
  const [offers, setOffers] = useState([]);
  const [isLoadingOffers, setIsLoadingOffers] = useState(false);

  // Toast Notification State
  const [toast, setToast] = useState({ show: false, message: '', type: 'success' });

  // Min Date constraint for safety
  const minDateStr = new Date().toISOString().split('T')[0];

  // Show Toast Helper
  const showToast = (message, type = 'success') => {
    setToast({ show: true, message, type });
    setTimeout(() => {
      setToast({ show: false, message: '', type: 'success' });
    }, 4500);
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
  }, []);

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

  // Handle seat click
  const handleSeatClick = (seatCode, isBooked) => {
    if (isBooked) return;

    if (selectedSeats.includes(seatCode)) {
      setSelectedSeats(prev => prev.filter(s => s !== seatCode));
    } else {
      if (selectedSeats.length >= 4) {
        showToast('You can select a maximum of 4 seats per booking.', 'error');
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

    setIsBooking(true);
    try {
      const res = await fetch(`${API_BASE}/bookings`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          schedule_id: selectedSchedule.id,
          passenger_name: passengerDetails.name,
          passenger_phone: passengerDetails.phone,
          passenger_email: passengerDetails.email,
          seat_numbers: selectedSeats.join(','),
          payment_method: passengerDetails.paymentMethod,
          promo_code: appliedPromo ? appliedPromo.code : null
        })
      });

      const data = await res.json();
      if (res.ok) {
        setBookingSuccess(data.booking);
        showToast('Ticket reserved successfully!', 'success');
        // Reset inputs
        setSelectedSeats([]);
        setAppliedPromo(null);
        setPromoInput('');
        setPassengerDetails({
          name: '',
          phone: '',
          email: '',
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

  // Search booking for cancellation
  const handleSearchCancel = async (e) => {
    e.preventDefault();
    if (!cancelQuery.trim()) {
      showToast('Please enter PNR or Phone number', 'error');
      return;
    }

    setIsSearchingCancel(true);
    try {
      const res = await fetch(`${API_BASE}/bookings/search?query=${encodeURIComponent(cancelQuery.trim())}`);
      if (res.ok) {
        const data = await res.json();
        setCancelBookings(data);
        if (data.length === 0) {
          showToast('No tickets found matching your query.', 'error');
        } else {
          showToast(`Found ${data.length} bookings.`, 'success');
        }
      } else {
        showToast('Failed to search tickets.', 'error');
      }
    } catch (err) {
      showToast('Error connecting to server.', 'error');
    } finally {
      setIsSearchingCancel(false);
    }
  };

  // Cancel Booking action
  const handleCancelBooking = async (bookingId) => {
    if (!window.confirm('Are you sure you want to cancel this ticket booking? This will release your seats.')) {
      return;
    }

    try {
      const res = await fetch(`${API_BASE}/bookings/${bookingId}/cancel`, {
        method: 'POST',
        headers: {
          'Accept': 'application/json'
        }
      });
      const data = await res.json();
      if (res.ok) {
        showToast('Booking successfully cancelled and seat released!', 'success');
        setCancelBookings(prev => prev.map(b => b.id === bookingId ? { ...b, status: 'CANCELLED' } : b));
        
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

  // Render Seat Grid helper
  const renderSeatMap = (schedule) => {
    const booked = schedule.booked_seats || [];
    const rows = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];
    
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
          {rows.map(row => {
            const s1 = `${row}1`;
            const s2 = `${row}2`;
            const s3 = `${row}3`;
            const s4 = `${row}4`;

            const s1Booked = booked.includes(s1);
            const s2Booked = booked.includes(s2);
            const s3Booked = booked.includes(s3);
            const s4Booked = booked.includes(s4);

            const s1Selected = selectedSeats.includes(s1);
            const s2Selected = selectedSeats.includes(s2);
            const s3Selected = selectedSeats.includes(s3);
            const s4Selected = selectedSeats.includes(s4);

            return (
              <div className="seat-row" key={row}>
                <div className="seat-pair">
                  <div 
                    className={`seat ${s1Booked ? 'booked' : ''} ${s1Selected ? 'selected' : ''}`}
                    onClick={() => handleSeatClick(s1, s1Booked)}
                  >
                    {s1}
                  </div>
                  <div 
                    className={`seat ${s2Booked ? 'booked' : ''} ${s2Selected ? 'selected' : ''}`}
                    onClick={() => handleSeatClick(s2, s2Booked)}
                  >
                    {s2}
                  </div>
                </div>
                
                <div className="bus-aisle"></div>
                
                <div className="seat-pair">
                  <div 
                    className={`seat ${s3Booked ? 'booked' : ''} ${s3Selected ? 'selected' : ''}`}
                    onClick={() => handleSeatClick(s3, s3Booked)}
                  >
                    {s3}
                  </div>
                  <div 
                    className={`seat ${s4Booked ? 'booked' : ''} ${s4Selected ? 'selected' : ''}`}
                    onClick={() => handleSeatClick(s4, s4Booked)}
                  >
                    {s4}
                  </div>
                </div>
              </div>
            );
          })}
        </div>

        <div className="seat-legend">
          <div className="legend-item">
            <div className="legend-dot available"></div>
            <span>Available</span>
          </div>
          <div className="legend-item">
            <div className="legend-dot selected"></div>
            <span>Selected</span>
          </div>
          <div className="legend-item">
            <div className="legend-dot booked"></div>
            <span>Booked</span>
          </div>
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
              Cancel Ticket
            </li>
            <li 
              className={`nav-link ${activeTab === 'offers' ? 'active' : ''}`}
              onClick={() => setActiveTab('offers')}
            >
              Promotions & Offers
            </li>
          </ul>
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
                                    {isExpanded ? 'Close Map' : 'Select Seats'}
                                  </button>
                                </div>
                              </div>

                              {isExpanded && (
                                <div className="seats-selector-container">
                                  <div className="seat-selection-grid">
                                    
                                    <div>
                                      <h3 style={{ fontSize: '14px', marginBottom: '15px', color: 'var(--text-secondary)', textTransform: 'uppercase', letterSpacing: '1px' }}>
                                        Bus Seat Layout Grid (Select Up To 4)
                                      </h3>
                                      {renderSeatMap(sched)}
                                    </div>

                                    <div className="booking-form-sidebar">
                                      <h3 className="booking-summary-title">Reservation Summary</h3>
                                      
                                      <div className="summary-row">
                                        <span className="summary-label">Route</span>
                                        <span className="summary-value">
                                          {stations.find(s => s.id === parseInt(searchParams.from))?.name} ➔ {stations.find(s => s.id === parseInt(searchParams.to))?.name}
                                        </span>
                                      </div>

                                      <div className="summary-row">
                                        <span className="summary-label">Coach</span>
                                        <span className="summary-value">
                                          {sched.bus.operator_name} ({sched.bus.coach_type})
                                        </span>
                                      </div>

                                      <div className="summary-row">
                                        <span className="summary-label">Selected Seats</span>
                                        <span className="summary-value">
                                          {selectedSeats.length > 0 ? (
                                            selectedSeats.map(seat => (
                                              <span key={seat} className="selected-seats-badge">{seat}</span>
                                            ))
                                          ) : (
                                            <span style={{ color: 'var(--text-muted)', fontStyle: 'italic' }}>None selected</span>
                                          )}
                                        </span>
                                      </div>

                                      <div className="summary-row">
                                        <span className="summary-label">Ticket Subtotal</span>
                                        <span className="summary-value">
                                          BDT {(sched.fare * selectedSeats.length).toLocaleString()}
                                        </span>
                                      </div>

                                      <div style={{ display: 'flex', flexDirection: 'column', gap: '6px' }}>
                                        <span className="summary-label">Promo Coupon Discount</span>
                                        <form className="coupon-field" onSubmit={handleApplyPromo}>
                                          <input 
                                            type="text" 
                                            placeholder="Enter promo (e.g. SONYANEW)" 
                                            className="coupon-input"
                                            value={promoInput}
                                            onChange={(e) => setPromoInput(e.target.value)}
                                            disabled={selectedSeats.length === 0}
                                          />
                                          <button 
                                            className="btn btn-secondary btn-coupon-apply" 
                                            type="submit"
                                            disabled={selectedSeats.length === 0}
                                          >
                                            Apply
                                          </button>
                                        </form>
                                        {appliedPromo && (
                                          <span style={{ fontSize: '11px', color: 'var(--success)', fontWeight: '600' }}>
                                            Discount Coupon Applied: -BDT {appliedPromo.discount_amount} ({appliedPromo.description})
                                          </span>
                                        )}
                                      </div>

                                      <div className="summary-row" style={{ borderTop: '1px solid var(--border-color)', paddingTop: '15px' }}>
                                        <span className="summary-label" style={{ fontSize: '15px', fontWeight: 'bold', color: '#fff' }}>Total Payable Fare</span>
                                        <span className="summary-value" style={{ fontSize: '20px', color: 'var(--gold)' }}>
                                          BDT {Math.max(0, (sched.fare * selectedSeats.length) - (appliedPromo ? appliedPromo.discount_amount : 0)).toLocaleString()}
                                        </span>
                                      </div>

                                      <div className="booking-form-fields">
                                        <h4 style={{ fontSize: '12px', color: 'var(--text-secondary)', textTransform: 'uppercase', marginTop: '10px' }}>
                                          Passenger Credentials
                                        </h4>
                                        
                                        <div className="input-group">
                                          <input 
                                            type="text" 
                                            placeholder="Passenger Full Name" 
                                            className="coupon-input"
                                            value={passengerDetails.name}
                                            onChange={(e) => setPassengerDetails(prev => ({ ...prev, name: e.target.value }))}
                                            disabled={selectedSeats.length === 0}
                                          />
                                        </div>

                                        <div className="input-group">
                                          <input 
                                            type="tel" 
                                            placeholder="Mobile Number (e.g. 017xxxxxxxx)" 
                                            className="coupon-input"
                                            value={passengerDetails.phone}
                                            onChange={(e) => setPassengerDetails(prev => ({ ...prev, phone: e.target.value }))}
                                            disabled={selectedSeats.length === 0}
                                          />
                                        </div>

                                        <div className="input-group">
                                          <input 
                                            type="email" 
                                            placeholder="Email Address" 
                                            className="coupon-input"
                                            value={passengerDetails.email}
                                            onChange={(e) => setPassengerDetails(prev => ({ ...prev, email: e.target.value }))}
                                            disabled={selectedSeats.length === 0}
                                          />
                                        </div>

                                        <div className="input-group">
                                          <label style={{ fontSize: '11px', color: 'var(--text-secondary)' }}>Payment gateway channel</label>
                                          <div className="payment-toggle-group">
                                            <div 
                                              className={`payment-toggle ${passengerDetails.paymentMethod === 'bKash' ? 'active' : ''}`}
                                              onClick={() => selectedSeats.length > 0 && setPassengerDetails(prev => ({ ...prev, paymentMethod: 'bKash' }))}
                                            >
                                              bKash
                                            </div>
                                            <div 
                                              className={`payment-toggle ${passengerDetails.paymentMethod === 'Nagad' ? 'active' : ''}`}
                                              onClick={() => selectedSeats.length > 0 && setPassengerDetails(prev => ({ ...prev, paymentMethod: 'Nagad' }))}
                                            >
                                              Nagad
                                            </div>
                                            <div 
                                              className={`payment-toggle ${passengerDetails.paymentMethod === 'Card' ? 'active' : ''}`}
                                              onClick={() => selectedSeats.length > 0 && setPassengerDetails(prev => ({ ...prev, paymentMethod: 'Card' }))}
                                            >
                                              Card
                                            </div>
                                          </div>
                                        </div>

                                        <button 
                                          className="btn btn-primary" 
                                          style={{ marginTop: '10px', height: '45px', fontWeight: 'bold' }}
                                          onClick={handleConfirmBooking}
                                          disabled={selectedSeats.length === 0 || isBooking}
                                        >
                                          {isBooking ? 'Processing Reservation...' : 'Confirm Ticket Reservation'}
                                        </button>
                                      </div>

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
              <h2 className="cancel-title">Manage ticket / Cancellations</h2>
              <p className="cancel-desc">Enter your PNR Ticket code or Passenger mobile number to view details or request a cancellation.</p>
              
              <form className="coupon-field" onSubmit={handleSearchCancel}>
                <input 
                  type="text" 
                  className="coupon-input" 
                  placeholder="e.g. SE00001 or 01712345678"
                  value={cancelQuery}
                  onChange={(e) => setCancelQuery(e.target.value)}
                />
                <button 
                  className="btn btn-primary" 
                  type="submit"
                  disabled={isSearchingCancel}
                >
                  {isSearchingCancel ? 'Searching...' : 'Search Ticket'}
                </button>
              </form>

              <div className="cancellation-preview">
                {cancelBookings.length > 0 ? (
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
                        <span className={`badge-status ${b.status === 'PAID' ? 'paid' : 'cancelled'}`}>{b.status}</span>
                      </div>

                      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '10px', fontSize: '13px', color: 'var(--text-secondary)' }}>
                        <div>Passenger: <strong style={{ color: '#fff' }}>{b.passenger_name}</strong></div>
                        <div>Phone: <strong style={{ color: '#fff' }}>{b.passenger_phone}</strong></div>
                        <div>From: <strong style={{ color: '#fff' }}>{b.schedule.route.from}</strong></div>
                        <div>To: <strong style={{ color: '#fff' }}>{b.schedule.route.to}</strong></div>
                        <div>Date: <strong style={{ color: '#fff' }}>{formatDate(b.schedule.departure_time)}</strong></div>
                        <div>Departure: <strong style={{ color: '#fff' }}>{formatTime(b.schedule.departure_time)}</strong></div>
                        <div>Seats Reserved: <strong style={{ color: 'var(--primary)' }}>{b.seat_numbers}</strong></div>
                        <div>Fare paid: <strong style={{ color: 'var(--gold)' }}>BDT {b.total_fare.toLocaleString()}</strong></div>
                      </div>

                      {b.status === 'PAID' ? (
                        <div style={{ marginTop: '20px' }}>
                          <div className="cancellation-refund-info">
                            <strong>Notice:</strong> Cancelling this ticket releases your reserved seats instantly. A 100% refund will be credited back to your account ({b.payment_method}) within 24 hours.
                          </div>
                          <button 
                            className="btn btn-danger w-full"
                            onClick={() => handleCancelBooking(b.id)}
                          >
                            Cancel Reservation & Request Refund
                          </button>
                        </div>
                      ) : (
                        <div style={{ marginTop: '15px', color: 'var(--text-muted)', fontSize: '12px', fontStyle: 'italic', textAlign: 'center' }}>
                          This reservation was cancelled. Refund has been processed.
                        </div>
                      )}
                    </div>
                  ))
                ) : (
                  cancelQuery && !isSearchingCancel && (
                    <div style={{ color: 'var(--text-muted)', fontSize: '13px', textAlign: 'center', marginTop: '20px' }}>
                      No active reservations found for this inquiry.
                    </div>
                  )
                )}
              </div>

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

      </main>

      {/* Footer */}
      <footer className="app-footer">
        <div className="container">
          <div className="footer-logo">SonyaBus Enterprise</div>
          <ul className="footer-links">
            <li onClick={() => { setActiveTab('home'); setBookingSuccess(null); }} style={{ cursor: 'pointer' }}>Search Buses</li>
            <li onClick={() => setActiveTab('cancel')} style={{ cursor: 'pointer' }}>Cancel Booking</li>
            <li onClick={() => setActiveTab('offers')} style={{ cursor: 'pointer' }}>Special Promotions</li>
          </ul>
          <p>© 2026 SonyaBus Enterprise Ltd. All rights reserved. Built with React + Laravel.</p>
        </div>
      </footer>
    </>
  );
}

export default App;
