import React from 'react';

export default function PaymentFailed({ paymentFailed, setPaymentFailed, setActiveTab }) {
  const isCancelled = paymentFailed?.type === 'cancelled';
  const errorMsg = paymentFailed?.errorMsg;

  const handleReturnHome = () => {
    setPaymentFailed(null);
    setActiveTab('home');
  };

  return (
    <div className="container failed-container">
      <div className="failed-card">
        <h1 className="failed-title">Payment not completed</h1>
        
        <p className="failed-subtitle">
          {isCancelled 
            ? "You cancelled the payment. No ticket was issued and your held seats have been released."
            : `Your payment was not successful. ${errorMsg ? errorMsg : "No ticket was issued and your held seats have been released."}`}
        </p>
        
        <button className="failed-btn-home" onClick={handleReturnHome}>
          <svg 
            width="16" 
            height="16" 
            viewBox="0 0 24 24" 
            fill="none" 
            stroke="currentColor" 
            strokeWidth="2.5" 
            strokeLinecap="round" 
            strokeLinejoin="round"
            style={{ marginRight: '6px' }}
          >
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
            <polyline points="9 22 9 12 15 12 15 22" />
          </svg>
          Return Home
        </button>
        
        {/* Ticket-stub zig-zag jagged edge effect at the bottom */}
        <div className="failed-card-zigzag"></div>
      </div>
    </div>
  );
}
