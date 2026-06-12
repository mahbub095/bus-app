import React from 'react';
import SeatMap from './SeatMap';

export default function ScheduleList({
  searchDone,
  searchResults,
  selectedSchedule,
  setSelectedSchedule,
  selectedSeats,
  setSelectedSeats,
  setAppliedPromo,
  setPromoInput,
  authUser,
  openAuthModal,
  showToast,
  stations,
  searchParams,
  boardingPoint,
  setBoardingPoint,
  droppingPoint,
  setDroppingPoint,
  passengerDetails,
  setPassengerDetails,
  passengerGender,
  setPassengerGender,
  promoInput,
  appliedPromo,
  handleApplyPromo,
  handleConfirmBooking,
  isBooking,
  seatMapLastSync
}) {
  if (!searchDone) return null;

  const formatTime = (isoString) => {
    if (!isoString) return '';
    const date = new Date(isoString);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  };

  return (
    <section className="results-container container">
      <div className="results-header">
        <h2 className="section-title" style={{ margin: '0', textAlign: 'left' }}>
          Available Coach Services
        </h2>
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
          searchResults.map((sched) => {
            const isExpanded = selectedSchedule?.id === sched.id;
            return (
              <div className="bus-card" key={sched.id}>
                <div className="bus-main-info">
                  <div className="operator-block">
                    <span className="operator-name">{sched.bus.operator_name}</span>
                    <span style={{ fontSize: '11px', color: 'var(--text-muted)' }}>
                      Coach {sched.bus.coach_number}
                    </span>
                    <span className={`coach-tag ${sched.bus.coach_type === 'AC' ? 'ac' : ''}`}>
                      {sched.bus.coach_type}
                    </span>
                  </div>

                  <div className="time-block">
                    <span className="time-label">Departure</span>
                    <span className="time-value">{formatTime(sched.departure_time)}</span>
                    <span className="station-value">
                      {searchParams.from
                        ? stations.find((s) => s.id === parseInt(searchParams.from))?.name
                        : ''}
                    </span>
                  </div>

                  <div className="time-block">
                    <span className="time-label">Arrival</span>
                    <span className="time-value">{formatTime(sched.arrival_time)}</span>
                    <span className="station-value">
                      {searchParams.to
                        ? stations.find((s) => s.id === parseInt(searchParams.to))?.name
                        : ''}
                    </span>
                  </div>

                  <div className="time-block">
                    <span className="time-label">Duration</span>
                    <span className="time-value" style={{ fontWeight: '500' }}>
                      {sched.route.duration}
                    </span>
                    <span className="station-value" style={{ fontSize: '11px' }}>
                      {sched.route.distance}
                    </span>
                  </div>

                  <div className="seats-block">
                    <span className="time-label">Seats Available</span>
                    <span
                      className="seats-count"
                      style={{
                        color:
                          sched.available_seats_count === 0 ? 'var(--danger)' : 'var(--success)'
                      }}
                    >
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
                      {isExpanded ? 'Close Map' : authUser ? 'Select Seats' : 'Login to Book'}
                    </button>
                  </div>
                </div>

                {isExpanded && (
                  <SeatMap
                    sched={sched}
                    selectedSeats={selectedSeats}
                    handleSeatClick={(seat, status) => {
                      if (!isSeatSelectable(status)) return;
                      if (selectedSeats.includes(seat)) {
                        setSelectedSeats((prev) => prev.filter((s) => s !== seat));
                      } else {
                        if (selectedSeats.length >= 4) {
                          window.alert('You can select a maximum of 4 seats per booking.');
                          return;
                        }
                        setSelectedSeats((prev) => [...prev, seat]);
                      }
                    }}
                    seatMapLastSync={seatMapLastSync}
                    boardingPoint={boardingPoint}
                    setBoardingPoint={setBoardingPoint}
                    droppingPoint={droppingPoint}
                    setDroppingPoint={setDroppingPoint}
                    passengerDetails={passengerDetails}
                    setPassengerDetails={setPassengerDetails}
                    passengerGender={passengerGender}
                    setPassengerGender={setPassengerGender}
                    promoInput={promoInput}
                    setPromoInput={setPromoInput}
                    appliedPromo={appliedPromo}
                    handleApplyPromo={handleApplyPromo}
                    handleConfirmBooking={handleConfirmBooking}
                    isBooking={isBooking}
                  />
                )}
              </div>
            );
          })
        )}
      </div>
    </section>
  );
}

// Helper to determine selectable seats
function isSeatSelectable(status) {
  return status === 'available';
}
