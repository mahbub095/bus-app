import React from 'react';

export default function VerificationStatus({
  verificationStatus,
  setVerificationStatus,
  setBookingSuccess,
  API_BASE
}) {
  if (!verificationStatus) return null;

  return (
    <div className="container" style={{ marginTop: '20px' }}>
      <div
        id="verification-status"
        className="verification-status-box"
        style={{
          background: 'rgba(30, 30, 56, 0.85)',
          backdropFilter: 'blur(20px)',
          WebkitBackdropFilter: 'blur(20px)',
          border: '1px solid rgba(255, 255, 255, 0.08)',
          borderRadius: '12px',
          padding: '24px',
          color: '#fff',
          textAlign: 'center',
          marginBottom: '20px',
          boxShadow: '0 20px 60px rgba(0, 0, 0, 0.4)'
        }}
      >
        {verificationStatus.loading ? (
          <div>
            <div className="loading-spinner" style={{ margin: '10px auto' }}></div>
            <p style={{ fontSize: '15px' }}>
              Verifying payment for Invoice ID: <strong>{verificationStatus.invoiceId}</strong>...
            </p>
          </div>
        ) : (
          <div>
            {verificationStatus.success ? (
              <div>
                <div style={{ fontSize: '32px', marginBottom: '10px' }}>✅</div>
                <p style={{ fontSize: '18px', fontWeight: 'bold' }}>Payment Verified Successfully!</p>
                <p style={{ color: 'var(--text-secondary)', marginTop: '8px', fontSize: '14px' }}>
                  Invoice ID: <strong>{verificationStatus.invoiceId}</strong>
                  <br />
                  Transaction ID: <strong>{verificationStatus.transactionId}</strong>
                </p>
                <button
                  className="btn btn-primary"
                  style={{ marginTop: '20px', padding: '10px 24px', fontSize: '13px', fontWeight: 'bold' }}
                  onClick={() => {
                    const bid = verificationStatus.bookingId;
                    setVerificationStatus(null);
                    if (bid) {
                      fetch(`${API_BASE}/bookings/public/${bid}`)
                        .then((res) => res.json())
                        .then((data) => {
                          setBookingSuccess(data);
                        })
                        .catch(() => {});
                    }
                  }}
                >
                  View Boarding Pass
                </button>
              </div>
            ) : (
              <div>
                <div style={{ fontSize: '32px', marginBottom: '10px' }}>❌</div>
                <p style={{ fontSize: '18px', fontWeight: 'bold', color: '#F87171' }}>
                  Payment Verification Failed
                </p>
                <p style={{ color: 'var(--text-secondary)', marginTop: '8px', fontSize: '14px' }}>
                  {verificationStatus.message}
                </p>
                <button
                  className="btn btn-secondary"
                  style={{ marginTop: '20px', padding: '10px 24px', fontSize: '13px', fontWeight: 'bold' }}
                  onClick={() => setVerificationStatus(null)}
                >
                  Dismiss
                </button>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
