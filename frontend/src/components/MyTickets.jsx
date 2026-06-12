import React, { useState, useEffect } from 'react';

export default function MyTickets({
  authUser,
  authToken,
  clearAuth,
  openAuthModal,
  setActiveTab,
  showToast,
  API_BASE
}) {
  const [cancelBookings, setCancelBookings] = useState([]);
  const [isSearchingCancel, setIsSearchingCancel] = useState(false);

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

  const authHeaders = (extra = {}) => {
    const headers = { 'Accept': 'application/json', ...extra };
    if (authToken) {
      headers['Authorization'] = `Bearer ${authToken}`;
    }
    return headers;
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

  const handleCancelBooking = async (bookingId) => {
    if (!authUser || !authToken) {
      openAuthModal('login');
      return;
    }
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
      } else {
        showToast(data.message || 'Failed to cancel booking.', 'error');
      }
    } catch (err) {
      showToast('Network error during cancellation.', 'error');
    }
  };

  useEffect(() => {
    if (authToken) {
      fetchMyBookings();
    }
  }, [authToken]);

  return (
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
            <button
              className="btn btn-primary"
              onClick={() => openAuthModal('login', () => setActiveTab('cancel'))}
            >
              Login to Continue
            </button>
          </div>
        ) : (
          <>
            <div
              style={{
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center',
                marginBottom: '20px',
                flexWrap: 'wrap',
                gap: '10px'
              }}
            >
              <span style={{ fontSize: '13px', color: 'var(--text-secondary)' }}>
                Signed in as <strong style={{ color: '#fff' }}>{authUser.email}</strong>
              </span>
              <button
                className="btn btn-secondary btn-sm"
                onClick={fetchMyBookings}
                disabled={isSearchingCancel}
              >
                {isSearchingCancel ? 'Refreshing...' : 'Refresh My Tickets'}
              </button>
            </div>

            <div className="cancellation-preview">
              {isSearchingCancel ? (
                <div className="loading-spinner"></div>
              ) : cancelBookings.length > 0 ? (
                cancelBookings.map((b) => (
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
                      <span
                        className={`badge-status ${
                          b.status === 'PAID'
                            ? 'paid'
                            : b.status === 'CANCEL_REQUESTED'
                            ? 'pending'
                            : 'cancelled'
                        }`}
                      >
                        {b.status}
                      </span>
                    </div>

                    <div
                      style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: '10px',
                        fontSize: '13px',
                        color: 'var(--text-secondary)'
                      }}
                    >
                      <div>
                        Passenger: <strong style={{ color: '#fff' }}>{b.passenger_name}</strong>
                      </div>
                      <div>
                        Phone: <strong style={{ color: '#fff' }}>{b.passenger_phone}</strong>
                      </div>
                      <div>
                        From: <strong style={{ color: '#fff' }}>{b.schedule.route.from}</strong>
                      </div>
                      <div>
                        To: <strong style={{ color: '#fff' }}>{b.schedule.route.to}</strong>
                      </div>
                      <div>
                        Bus Name:{' '}
                        <strong style={{ color: '#fff' }}>
                          {b.schedule?.bus?.operator_name || 'N/A'}
                        </strong>
                      </div>
                      <div>
                        Date:{' '}
                        <strong style={{ color: '#fff' }}>{formatDate(b.schedule.departure_time)}</strong>
                      </div>
                      <div>
                        Departure:{' '}
                        <strong style={{ color: '#fff' }}>{formatTime(b.schedule.departure_time)}</strong>
                      </div>
                      <div>
                        Seats Reserved:{' '}
                        <strong style={{ color: 'var(--primary)' }}>{b.seat_numbers}</strong>
                      </div>
                      <div>
                        Fare paid:{' '}
                        <strong style={{ color: 'var(--gold)' }}>
                          BDT {Number(b.total_fare).toLocaleString()}
                        </strong>
                      </div>
                    </div>

                    {b.status === 'PAID' ? (
                      <div style={{ marginTop: '20px' }}>
                        <div className="cancellation-refund-info">
                          <strong>Notice:</strong> You can submit a cancellation request for admin verification.
                          After approval, the ticket is cancelled and refund is processed to your{' '}
                          {b.payment_method} account.
                        </div>
                        <button
                          className="btn btn-secondary w-full"
                          style={{ marginBottom: '10px' }}
                          onClick={() => handleDownloadTicketPdf(b)}
                        >
                          Download Ticket PDF
                        </button>
                        <button className="btn btn-danger w-full" onClick={() => handleCancelBooking(b.id)}>
                          Submit Cancellation Request
                        </button>
                      </div>
                    ) : b.status === 'CANCEL_REQUESTED' ? (
                      <div
                        style={{
                          marginTop: '15px',
                          color: '#FBBF24',
                          fontSize: '12px',
                          fontStyle: 'italic',
                          textAlign: 'center'
                        }}
                      >
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
                        <div
                          style={{
                            color: 'var(--text-muted)',
                            fontSize: '12px',
                            fontStyle: 'italic',
                            textAlign: 'center'
                          }}
                        >
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
                <div
                  style={{
                    color: 'var(--text-muted)',
                    fontSize: '13px',
                    textAlign: 'center',
                    marginTop: '20px'
                  }}
                >
                  You have no ticket bookings yet. Book a ticket from the home page while logged in.
                </div>
              )}
            </div>
          </>
        )}
      </div>
    </div>
  );
}
