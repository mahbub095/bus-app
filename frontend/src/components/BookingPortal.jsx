import React, { useState, useEffect } from 'react';
import SearchForm from './SearchForm';
import ScheduleList from './ScheduleList';
import Features from './Features';
import Partners from './Partners';
import BookingSuccess from './BookingSuccess';
import { scheduleCache } from '../scheduleCache';

export default function BookingPortal({
  bookingSuccess,
  setBookingSuccess,
  verificationStatus,
  setVerificationStatus,
  authUser,
  authToken,
  clearAuth,
  openAuthModal,
  showToast,
  API_BASE
}) {
  // Min Date constraint for safety
  const minDateStr = new Date().toISOString().split('T')[0];

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
  const [isFromCache, setIsFromCache] = useState(false);
  const [lastFetched, setLastFetched] = useState(null);

  // Booking & Selection States
  const [selectedSchedule, setSelectedSchedule] = useState(null);
  const [selectedSeats, setSelectedSeats] = useState([]);
  const [seatExpirations, setSeatExpirations] = useState({});
  const [promoInput, setPromoInput] = useState('');
  const [appliedPromo, setAppliedPromo] = useState(null);
  const [passengerDetails, setPassengerDetails] = useState({
    name: authUser?.name || '',
    phone: '',
    email: authUser?.email || '',
    paymentMethod: 'ZiniPay'
  });
  const [boardingPoint, setBoardingPoint] = useState('');
  const [droppingPoint, setDroppingPoint] = useState('');
  const [passengerGender, setPassengerGender] = useState('M');
  const [seatMapLastSync, setSeatMapLastSync] = useState(null);
  const [isBooking, setIsBooking] = useState(false);

  // Update passenger details when authUser changes
  useEffect(() => {
    if (authUser) {
      setPassengerDetails(prev => ({
        ...prev,
        name: prev.name || authUser.name,
        email: authUser.email
      }));
    }
  }, [authUser]);

  // Auth helper headers
  const authHeaders = (extra = {}) => {
    const headers = { 'Accept': 'application/json', ...extra };
    if (authToken) {
      headers['Authorization'] = `Bearer ${authToken}`;
    }
    return headers;
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
  }, []);

  const prevScheduleIdRef = React.useRef(null);
  const prevSeatsRef = React.useRef([]);

  useEffect(() => {
    prevSeatsRef.current = selectedSeats;
  }, [selectedSeats]);

  // Release holds when schedule changes or collapses
  useEffect(() => {
    const prevScheduleId = prevScheduleIdRef.current;
    const prevSeats = prevSeatsRef.current;

    if (prevScheduleId && prevScheduleId !== selectedSchedule?.id && prevSeats.length > 0) {
      prevSeats.forEach(async (seat) => {
        try {
          await fetch(`${API_BASE}/seats/release`, {
            method: 'POST',
            headers: {
              'Accept': 'application/json',
              'Content-Type': 'application/json',
              'Authorization': `Bearer ${authToken}`
            },
            body: JSON.stringify({
              schedule_id: prevScheduleId,
              seat_number: seat
            })
          });
        } catch {
          // silent
        }
      });
    }
    prevScheduleIdRef.current = selectedSchedule?.id;
  }, [selectedSchedule?.id, authToken]);

  // Release holds on page unload / tab close
  useEffect(() => {
    const handleBeforeUnload = () => {
      const scheduleId = prevScheduleIdRef.current;
      const seats = prevSeatsRef.current;
      if (scheduleId && seats.length > 0) {
        const url = `${API_BASE}/seats/release`;
        seats.forEach(seat => {
          try {
            const client = new XMLHttpRequest();
            client.open("POST", url, false); // synchronous XHR
            client.setRequestHeader("Content-Type", "application/json");
            client.setRequestHeader("Accept", "application/json");
            if (authToken) {
              client.setRequestHeader("Authorization", `Bearer ${authToken}`);
            }
            client.send(JSON.stringify({ schedule_id: scheduleId, seat_number: seat }));
          } catch {
            // ignore
          }
        });
      }
    };
    window.addEventListener('beforeunload', handleBeforeUnload);
    return () => window.removeEventListener('beforeunload', handleBeforeUnload);
  }, [authToken]);

  // Expiration checking effect
  useEffect(() => {
    const checkExpirations = async () => {
      const now = new Date();
      const expired = [];

      Object.entries(seatExpirations).forEach(([seat, expiresAt]) => {
        if (new Date(expiresAt) <= now) {
          expired.push(seat);
        }
      });

      if (expired.length > 0) {
        setSelectedSeats(prev => prev.filter(s => !expired.includes(s)));
        setSeatExpirations(prev => {
          const next = { ...prev };
          expired.forEach(s => delete next[s]);
          return next;
        });

        expired.forEach(seat => {
          showToast(`Hold reservation for seat ${seat} has expired.`, 'error');
        });

        // Release holds on backend
        for (const seat of expired) {
          try {
            await fetch(`${API_BASE}/seats/release`, {
              method: 'POST',
              headers: authHeaders({ 'Content-Type': 'application/json' }),
              body: JSON.stringify({
                schedule_id: selectedSchedule.id,
                seat_number: seat
              })
            });
          } catch {
            // silent
          }
        }
      }
    };

    const interval = setInterval(checkExpirations, 1000);
    return () => clearInterval(interval);
  }, [seatExpirations, selectedSchedule?.id]);

  const handleSeatClick = async (seat, status) => {
    if (!requireAuth()) return;

    if (selectedSeats.includes(seat)) {
      // Optimistic deselect — update UI immediately, then confirm with server
      setSelectedSeats(prev => prev.filter(s => s !== seat));
      setSeatExpirations(prev => {
        const next = { ...prev };
        delete next[seat];
        return next;
      });

      try {
        const res = await fetch(`${API_BASE}/seats/release`, {
          method: 'POST',
          headers: authHeaders({ 'Content-Type': 'application/json' }),
          body: JSON.stringify({
            schedule_id: selectedSchedule.id,
            seat_number: seat
          })
        });

        if (!res.ok) {
          // Roll back if server rejected
          setSelectedSeats(prev => [...prev, seat]);
          showToast('Failed to release seat.', 'error');
        }
      } catch {
        // Roll back on network error
        setSelectedSeats(prev => [...prev, seat]);
        showToast('Connection error. Could not release seat.', 'error');
      }
    } else {
      // Check limit before doing anything
      if (selectedSeats.length >= 4) {
        window.alert('You can select a maximum of 4 seats per booking.');
        return;
      }

      // Optimistic select — mark seat immediately with a placeholder expiry so the
      // UI responds at once; the real expiry is patched in once the server replies
      const optimisticExpiry = new Date(Date.now() + 2 * 60 * 1000).toISOString();
      setSelectedSeats(prev => [...prev, seat]);
      setSeatExpirations(prev => ({ ...prev, [seat]: optimisticExpiry }));

      try {
        const res = await fetch(`${API_BASE}/seats/hold`, {
          method: 'POST',
          headers: authHeaders({ 'Content-Type': 'application/json' }),
          body: JSON.stringify({
            schedule_id: selectedSchedule.id,
            seat_number: seat
          })
        });

        const data = await res.json();
        if (res.ok && data.success) {
          // Replace placeholder expiry with the real server-issued one
          setSeatExpirations(prev => ({ ...prev, [seat]: data.expires_at }));
        } else {
          // Roll back optimistic selection
          setSelectedSeats(prev => prev.filter(s => s !== seat));
          setSeatExpirations(prev => {
            const next = { ...prev };
            delete next[seat];
            return next;
          });
          showToast(data.message || 'Seat is not available.', 'error');
        }
      } catch {
        // Roll back on network error
        setSelectedSeats(prev => prev.filter(s => s !== seat));
        setSeatExpirations(prev => {
          const next = { ...prev };
          delete next[seat];
          return next;
        });
        showToast('Connection error. Could not reserve seat.', 'error');
      }
    }
  };

  // Handle Search Submission
  const handleSearch = async (e, forceRefresh = false) => {
    if (e && e.preventDefault) {
      e.preventDefault();
    }
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

    // Check Cache first if not forcing a refresh
    if (!forceRefresh) {
      const cached = scheduleCache.getEntry(
        searchParams.from,
        searchParams.to,
        searchParams.date,
        searchParams.coachType
      );
      if (cached) {
        setSearchResults(cached.data);
        setSearchDone(true);
        setIsFromCache(true);
        setLastFetched(cached.timestamp);
        setIsLoading(false);
        if (cached.data.length === 0) {
          showToast('No schedules found for this query.', 'error');
        } else {
          showToast(`Found ${cached.data.length} schedules (cached).`, 'success');
        }
        return;
      }
    }

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
        setIsFromCache(false);
        const now = Date.now();
        setLastFetched(now);

        // Update cache
        scheduleCache.set(
          searchParams.from,
          searchParams.to,
          searchParams.date,
          searchParams.coachType,
          data
        );

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

  const handleRefresh = () => {
    handleSearch(null, true);
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
        setIsFromCache(false);
        setLastFetched(Date.now());

        // Update cache
        scheduleCache.set(
          searchParams.from,
          searchParams.to,
          searchParams.date,
          searchParams.coachType,
          fresh
        );

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
          setIsFromCache(false);
          setLastFetched(Date.now());

          // Update cache
          scheduleCache.set(
            searchParams.from,
            searchParams.to,
            searchParams.date,
            searchParams.coachType,
            freshData
          );
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
            handleSeatClick={handleSeatClick}
            seatExpirations={seatExpirations}
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
            isFromCache={isFromCache}
            lastFetched={lastFetched}
            onRefresh={handleRefresh}
          />

          {/* Info Visual Steps */}
          {!searchDone && <Features />}

          {/* Partners Logo */}
          <Partners />
        </>
      )}
    </>
  );
}
