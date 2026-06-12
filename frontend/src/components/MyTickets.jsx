import React from 'react';

export default function MyTickets({
  authUser,
  openAuthModal,
  setActiveTab,
  fetchMyBookings,
  isSearchingCancel,
  cancelBookings,
  handleCancelBooking,
  handleDownloadTicketPdf,
  formatDate,
  formatTime
}) {
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
