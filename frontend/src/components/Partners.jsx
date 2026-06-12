import React from 'react';

export default function Partners() {
  return (
    <section className="payment-section">
      <div className="container">
        <p
          style={{
            color: 'var(--text-secondary)',
            fontSize: '13px',
            textTransform: 'uppercase',
            letterSpacing: '1px'
          }}
        >
          Secure Gateway Channels & Partners
        </p>
        <div className="payment-list">
          <div
            className="payment-logo"
            style={{ color: '#E2136E', fontWeight: '800', fontSize: '20px', letterSpacing: '-0.5px' }}
          >
            bKash
          </div>
          <div
            className="payment-logo"
            style={{ color: '#F05A24', fontWeight: '800', fontSize: '20px', letterSpacing: '-0.5px' }}
          >
            NAGAD
          </div>
          <div
            className="payment-logo"
            style={{ color: '#1B6CA8', fontWeight: '800', fontSize: '20px', letterSpacing: '-0.5px' }}
          >
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
  );
}
