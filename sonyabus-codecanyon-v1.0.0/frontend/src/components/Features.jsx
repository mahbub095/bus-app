import React from 'react';

export default function Features() {
  return (
    <section className="features-section">
      <div className="container">
        <div className="section-title">
          <h2>Travel Made Simple</h2>
          <p>Reservation takes less than 2 minutes through our state-of-the-art visual blueprint system</p>
        </div>

        <div className="features-grid">
          <div className="feature-card">
            <div className="feature-icon-wrapper">🔍</div>
            <h3>1. Search Routes</h3>
            <p>Select your starting city, terminal arrival destination, and your scheduled travel date.</p>
          </div>

          <div className="feature-card">
            <div className="feature-icon-wrapper">🎟️</div>
            <h3>2. Interactive Layout</h3>
            <p>Pick your seats visually using our simulated bus blueprint grid. See booked versus empty seats.</p>
          </div>

          <div className="feature-card">
            <div className="feature-icon-wrapper">🚌</div>
            <h3>3. Board Coach</h3>
            <p>Get instant invoice with PNR code on screen. Pay securely and head directly to boarding point.</p>
          </div>
        </div>
      </div>
    </section>
  );
}
