import React from 'react';

export default function BookingSuccess({
  bookingSuccess,
  setBookingSuccess,
  formatDate,
  formatTime
}) {
  if (!bookingSuccess) return null;

  return (
    <div className="container success-container">
      <div className="success-badge">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3">
          <polyline points="20 6 9 17 4 12" />
        </svg>
        Reservation Secured Successfully!
      </div>

      <h2 className="banner-title" style={{ fontSize: '28px' }}>
        Boarding Pass Invoice
      </h2>
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
                BDT {Number(bookingSuccess.total_fare).toLocaleString()}
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
  );
}
