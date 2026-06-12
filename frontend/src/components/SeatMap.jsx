import React from 'react';
import {
  SEAT_STATUS_LABELS,
  calcPricing,
  formatBdt,
  getSeatMap,
  isSeatSelectable,
} from '../bookingUtils';

export default function SeatMap({
  sched,
  selectedSeats,
  handleSeatClick,
  seatMapLastSync,
  boardingPoint,
  setBoardingPoint,
  droppingPoint,
  setDroppingPoint,
  passengerDetails,
  setPassengerDetails,
  passengerGender,
  setPassengerGender,
  promoInput,
  setPromoInput,
  appliedPromo,
  handleApplyPromo,
  handleConfirmBooking,
  isBooking
}) {
  const generateDefaultGrid = (layout, totalSeats) => {
    const rowLetters = [
      'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M',
      'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'
    ];

    if (layout === '2+2_last5') {
      const grid = [];
      grid.push([
        { type: 'engine', label: 'Engine' },
        { type: 'empty' },
        { type: 'aisle' },
        { type: 'empty' },
        { type: 'driver', label: 'Driver' }
      ]);

      let remainingSeats = totalSeats - 5;
      let normalRows = Math.ceil(remainingSeats / 4);

      let rIndex = 0;
      for (let r = 0; r < normalRows; r++) {
        const rowLetter = rowLetters[rIndex++];
        const row = [];
        row.push({ type: 'seat', label: rowLetter + '1' });
        row.push({ type: 'seat', label: rowLetter + '2' });
        row.push({ type: 'aisle' });
        row.push({ type: 'seat', label: rowLetter + '3' });
        row.push({ type: 'seat', label: rowLetter + '4' });
        grid.push(row);
      }

      const lastRowLetter = rowLetters[rIndex++];
      const lastRow = [];
      for (let num = 1; num <= 5; num++) {
        lastRow.push({ type: 'seat', label: lastRowLetter + num });
      }
      grid.push(lastRow);

      return grid;
    } else if (layout === '1+2') {
      const grid = [];
      grid.push([
        { type: 'engine', label: 'Engine' },
        { type: 'aisle' },
        { type: 'empty' },
        { type: 'driver', label: 'Driver' }
      ]);

      let normalRows = Math.ceil(totalSeats / 3);
      let rIndex = 0;
      for (let r = 0; r < normalRows; r++) {
        const rowLetter = rowLetters[rIndex++];
        const row = [];
        row.push({ type: 'seat', label: rowLetter + '1' });
        row.push({ type: 'aisle' });
        row.push({ type: 'seat', label: rowLetter + '2' });
        row.push({ type: 'seat', label: rowLetter + '3' });
        grid.push(row);
      }
      return grid;
    } else if (layout === 'sleeper') {
      const lowerCount = Math.ceil(totalSeats / 2);
      const upperCount = totalSeats - lowerCount;

      const lowerGrid = [];
      lowerGrid.push([
        { type: 'engine', label: 'Engine' },
        { type: 'aisle' },
        { type: 'empty' },
        { type: 'driver', label: 'Driver' }
      ]);
      let remainingSeats = lowerCount - 4;
      let lowerRows = Math.ceil(remainingSeats / 3);
      if (lowerRows < 0) lowerRows = 0;
      let rIndex = 0;
      for (let r = 0; r < lowerRows; r++) {
        const rowLetter = rowLetters[rIndex++];
        const row = [];
        row.push({ type: 'seat', label: 'L-' + rowLetter + '1' });
        row.push({ type: 'aisle' });
        row.push({ type: 'seat', label: 'L-' + rowLetter + '2' });
        row.push({ type: 'seat', label: 'L-' + rowLetter + '3' });
        lowerGrid.push(row);
      }
      const lastRowLetter = rowLetters[rIndex++];
      const lastRow = [];
      for (let num = 1; num <= 4; num++) {
        lastRow.push({ type: 'seat', label: 'L-' + lastRowLetter + num });
      }
      lowerGrid.push(lastRow);

      const upperGrid = [];
      upperGrid.push([
        { type: 'empty' },
        { type: 'aisle' },
        { type: 'empty' },
        { type: 'empty' }
      ]);
      let remainingSeatsU = upperCount - 4;
      let upperRows = Math.ceil(remainingSeatsU / 3);
      if (upperRows < 0) upperRows = 0;
      rIndex = 0;
      for (let r = 0; r < upperRows; r++) {
        const rowLetter = rowLetters[rIndex++];
        const row = [];
        row.push({ type: 'seat', label: 'U-' + rowLetter + '1' });
        row.push({ type: 'aisle' });
        row.push({ type: 'seat', label: 'U-' + rowLetter + '2' });
        row.push({ type: 'seat', label: 'U-' + rowLetter + '3' });
        upperGrid.push(row);
      }
      const lastRowLetterU = rowLetters[rIndex++];
      const lastRowU = [];
      for (let num = 1; num <= 4; num++) {
        lastRowU.push({ type: 'seat', label: 'U-' + lastRowLetterU + num });
      }
      upperGrid.push(lastRowU);

      return { lower: lowerGrid, upper: upperGrid };
    }

    const grid = [];
    grid.push([
      { type: 'engine', label: 'Engine' },
      { type: 'empty' },
      { type: 'aisle' },
      { type: 'empty' },
      { type: 'driver', label: 'Driver' }
    ]);
    let normalRows = Math.ceil(totalSeats / 4);
    let rIndex = 0;
    for (let r = 0; r < normalRows; r++) {
      const rowLetter = rowLetters[rIndex++];
      const row = [];
      row.push({ type: 'seat', label: rowLetter + '1' });
      row.push({ type: 'seat', label: rowLetter + '2' });
      row.push({ type: 'aisle' });
      row.push({ type: 'seat', label: rowLetter + '3' });
      row.push({ type: 'seat', label: rowLetter + '4' });
      grid.push(row);
    }
    return grid;
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

  const renderSeatMapLayout = (schedule) => {
    const seatMap = getSeatMap(schedule);
    const layout = schedule?.bus?.seat_layout || '2+2';
    const totalSeats = schedule?.bus?.total_seats || 36;

    let grid = schedule?.bus?.seat_layout_grid;
    if (typeof grid === 'string') {
      try {
        grid = JSON.parse(grid);
      } catch (e) {
        grid = null;
      }
    }
    if (!grid || typeof grid !== 'object') {
      grid = generateDefaultGrid(layout, totalSeats);
    }

    const isSleeper = grid.lower !== undefined;

    const renderDeckHtml = (deckGrid, hasDriver = false) => {
      let driverCell = null;
      let engineCell = null;

      if (hasDriver) {
        deckGrid.forEach((row) => {
          row.forEach((cell) => {
            if (cell.type === 'driver') driverCell = cell;
            if (cell.type === 'engine') engineCell = cell;
          });
        });
      }

      return (
        <div className="bus-blueprint">
          <div
            className="bus-head"
            style={{
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'center',
              paddingBottom: '16px',
              borderBottom: '2px dashed #2A2A44',
              marginBottom: '20px'
            }}
          >
            {engineCell ? (
              <div
                className="seat status-engine"
                title="Engine cover"
                style={{
                  cursor: 'not-allowed',
                  backgroundColor: '#374151',
                  borderColor: '#1f2937',
                  color: '#9ca3af',
                  fontSize: '9px',
                  fontWeight: 'bold',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  width: '36px',
                  height: '36px',
                  borderRadius: '8px'
                }}
              >
                ENG
              </div>
            ) : (
              <div style={{ width: '36px' }}></div>
            )}

            <span style={{ fontSize: '11px', color: 'var(--text-muted)', fontWeight: 'bold' }}>
              {hasDriver ? 'ENTRANCE' : 'UPPER DECK FRONT'}
            </span>

            {driverCell ? (
              <div
                className="seat status-driver"
                title="Driver Seat"
                style={{
                  cursor: 'not-allowed',
                  backgroundColor: '#10B981',
                  borderColor: '#059669',
                  color: '#fff',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  width: '36px',
                  height: '36px',
                  borderRadius: '8px'
                }}
              >
                <svg
                  width="20"
                  height="20"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2.5"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                >
                  <circle cx="12" cy="12" r="10" />
                  <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
                  <path d="M2 12h20" />
                </svg>
              </div>
            ) : (
              <div style={{ width: '36px' }}></div>
            )}
          </div>

          <div className="bus-body-seats">
            {deckGrid.map((row, rIdx) => {
              if (row.some((cell) => cell.type === 'driver' || cell.type === 'engine')) {
                return null;
              }
              return (
                <div className="seat-row" key={rIdx}>
                  {row.map((cell, cIdx) => {
                    if (cell.type === 'seat') {
                      return renderSeatCell(schedule, cell.label, seatMap);
                    } else if (cell.type === 'aisle') {
                      return <div className="bus-aisle" key={`aisle-${cIdx}`}></div>;
                    } else {
                      return <div className="seat seat-placeholder" key={`empty-${cIdx}`}></div>;
                    }
                  })}
                </div>
              );
            })}
          </div>
        </div>
      );
    };

    if (isSleeper) {
      return (
        <>
          <div className="sleeper-decks">
            <div>
              <div className="deck-title">Lower Deck</div>
              {renderDeckHtml(grid.lower, true)}
            </div>
            <div>
              <div className="deck-title">Upper Deck</div>
              {renderDeckHtml(grid.upper, false)}
            </div>
          </div>
          <div className="seat-legend" style={{ marginTop: '20px' }}>
            {['booked_m', 'booked_f', 'blocked', 'available', 'selected', 'sold_m', 'sold_f'].map((key) => (
              <div className="legend-item" key={key}>
                <div className={`legend-dot status-${key}`}></div>
                <span>{SEAT_STATUS_LABELS[key]}</span>
              </div>
            ))}
          </div>
        </>
      );
    }

    return (
      <>
        {renderDeckHtml(grid, true)}
        <div className="seat-legend">
          {['booked_m', 'booked_f', 'blocked', 'available', 'selected', 'sold_m', 'sold_f'].map((key) => (
            <div className="legend-item" key={key}>
              <div className={`legend-dot status-${key}`}></div>
              <span>{SEAT_STATUS_LABELS[key]}</span>
            </div>
          ))}
        </div>
      </>
    );
  };

  const pricing = calcPricing(sched, Math.max(selectedSeats.length, 1), passengerDetails.paymentMethod);
  const seatClass = sched.seat_class || 'E-Class';
  const promoDiscount = appliedPromo ? Number(appliedPromo.discount_amount) : 0;
  const grandTotal = Math.max(0, pricing.total - promoDiscount);
  const activeBoarding = (sched.boarding_points || []).find((bp) => bp.value === boardingPoint);
  const activeDropping = (sched.dropping_points || []).find((dp) => dp.value === droppingPoint);

  return (
    <div className="seats-selector-container">
      <div className="seat-selection-grid">
        <div>
          <h3
            style={{
              fontSize: '14px',
              marginBottom: '8px',
              color: 'var(--text-secondary)',
              textTransform: 'uppercase',
              letterSpacing: '1px'
            }}
          >
            Bus Seat Layout (Select Up To 4)
          </h3>
          {seatMapLastSync && (
            <div className="seat-map-live-status">
              <span className="live-dot"></span>
              <span>
                Live — updated{' '}
                {seatMapLastSync.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}{' '}
                (every 5s)
              </span>
            </div>
          )}
          {renderSeatMapLayout(sched)}
        </div>

        <div className="booking-form-sidebar">
          <div className="ticket-booking-panel">
            <h3>Boarding / Dropping Point</h3>
            <label>Boarding Point *</label>
            <select
              className="ticket-field"
              value={boardingPoint}
              onChange={(e) => setBoardingPoint(e.target.value)}
              disabled={selectedSeats.length === 0}
            >
              {(sched.boarding_points || []).map((bp) => (
                <option key={bp.id} value={bp.value}>
                  {bp.label} — Reporting: {bp.reporting_time || '—'}, Departure: {bp.departure_time || '—'}
                </option>
              ))}
            </select>
            <p className="boarding-point-info">
              {activeBoarding
                ? `Reporting: ${activeBoarding.reporting_time || '—'} · Departure: ${
                    activeBoarding.departure_time || '—'
                  }`
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
              {(sched.dropping_points || []).map((dp) => (
                <option key={dp.id} value={dp.value}>
                  {dp.label} — Arrival: {dp.arrival_time || '—'}
                </option>
              ))}
            </select>
            <p className="boarding-point-info">
              {activeDropping ? `Estimated arrival: ${activeDropping.arrival_time || '—'}` : 'Select a dropping point'}
            </p>
            <label>Mobile Number *</label>
            <input
              type="tel"
              className="ticket-field"
              placeholder="01XXXXXXXXX"
              value={passengerDetails.phone}
              onChange={(e) => setPassengerDetails((prev) => ({ ...prev, phone: e.target.value }))}
              disabled={selectedSeats.length === 0}
            />
            <label>Passenger Name *</label>
            <input
              type="text"
              className="ticket-field"
              placeholder="Full name"
              value={passengerDetails.name}
              onChange={(e) => setPassengerDetails((prev) => ({ ...prev, name: e.target.value }))}
              disabled={selectedSeats.length === 0}
            />
            <label>Email *</label>
            <input
              type="email"
              className="ticket-field"
              placeholder="email@example.com"
              value={passengerDetails.email}
              onChange={(e) => setPassengerDetails((prev) => ({ ...prev, email: e.target.value }))}
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
              {['ZiniPay'].map((method) => (
                <div
                  key={method}
                  className={`payment-toggle ${passengerDetails.paymentMethod === method ? 'active' : ''}`}
                  onClick={() =>
                    selectedSeats.length > 0 &&
                    setPassengerDetails((prev) => ({ ...prev, paymentMethod: method }))
                  }
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
              <button
                className="btn btn-secondary btn-coupon-apply"
                type="submit"
                disabled={selectedSeats.length === 0}
              >
                Apply
              </button>
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
                <tr>
                  <th>Seats</th>
                  <th>Class</th>
                  <th>Fare</th>
                </tr>
              </thead>
              <tbody>
                {selectedSeats.length > 0 ? (
                  selectedSeats.map((seat) => (
                    <tr key={seat}>
                      <td>{seat}</td>
                      <td>{seatClass}</td>
                      <td>{formatBdt(sched.fare)}</td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan={3} style={{ fontStyle: 'italic', color: '#9ca3af' }}>
                      Select seat(s) from the map
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
            <div className="fare-breakdown">
              <div className="fare-line">
                <span>Seat Fare:</span>
                <strong>{formatBdt(pricing.seatFare)}</strong>
              </div>
              <div className="fare-line">
                <span>Service Charge:</span>
                <strong>{formatBdt(pricing.serviceCharge)}</strong>
              </div>
              <div className="fare-line">
                <span>Gateway Charge:</span>
                <strong>{formatBdt(pricing.gatewayCharge)}</strong>
              </div>
              <div className="fare-line">
                <span>SC Discount:</span>
                <strong>{formatBdt(pricing.scDiscount)}</strong>
              </div>
              <div className="fare-line">
                <span>GC Discount:</span>
                <strong>{formatBdt(pricing.gcDiscount)}</strong>
              </div>
              {promoDiscount > 0 && (
                <div className="fare-line">
                  <span>Promo Discount:</span>
                  <strong>-{formatBdt(promoDiscount)}</strong>
                </div>
              )}
              <div className="fare-line fare-total">
                <span>Total Payable:</span>
                <strong>{formatBdt(grandTotal)}</strong>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
