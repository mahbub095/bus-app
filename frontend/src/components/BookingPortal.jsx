import React, { useState, useEffect } from 'react';
import SearchForm from './SearchForm';
import ScheduleList from './ScheduleList';
import Features from './Features';
import Partners from './Partners';
import BookingSuccess from './BookingSuccess';

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

  // Booking & Selection States
  const [selectedSchedule, setSelectedSchedule] = useState(null);
  const [selectedSeats, setSelectedSeats] = useState([]);
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
          {!searchDone && <Features />}

          {/* Partners Logo */}
          <Partners />
        </>
      )}
    </>
  );
}
