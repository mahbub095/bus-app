import React, { useState, useEffect } from 'react';

export default function OffersList({ API_BASE, showToast }) {
  const [offers, setOffers] = useState([]);
  const [isLoadingOffers, setIsLoadingOffers] = useState(false);

  const fetchOffers = async () => {
    setIsLoadingOffers(true);
    try {
      const res = await fetch(`${API_BASE}/promotions`);
      if (res.ok) {
        const data = await res.json();
        setOffers(data);
      }
    } catch (err) {
      showToast('Could not fetch promotions.', 'error');
    } finally {
      setIsLoadingOffers(false);
    }
  };

  useEffect(() => {
    fetchOffers();
  }, []);

  const handleCopyCode = (code) => {
    navigator.clipboard.writeText(code);
    showToast(`Coupon code "${code}" copied to clipboard!`, 'success');
  };

  return (
    <div className="container" style={{ flexGrow: 1, padding: '40px 0' }}>
      <div className="section-title">
        <h2>Discount Coupon Offers</h2>
        <p>Apply these promotional codes during checkout to secure special flat pricing discounts</p>
      </div>

      {isLoadingOffers ? (
        <div className="loading-spinner"></div>
      ) : (
        <div className="offers-grid">
          {offers.length === 0 ? (
            <div style={{ gridColumn: '1 / -1', textAlign: 'center', color: 'var(--text-secondary)' }}>
              No promotional coupons are available at the moment.
            </div>
          ) : (
            offers.map((promo) => (
              <div className="offer-card" key={promo.id}>
                <div className="offer-header">
                  <div className="offer-badge">Flat Discount</div>
                  <div className="offer-discount">BDT {promo.discount_amount} Off</div>
                </div>

                <div className="offer-body">
                  <p className="offer-desc">{promo.description}</p>
                  <div className="coupon-pill">
                    <span className="coupon-code">{promo.code}</span>
                    <span className="coupon-copy-btn" onClick={() => handleCopyCode(promo.code)}>
                      Copy Code
                    </span>
                  </div>
                </div>
              </div>
            ))
          )}
        </div>
      )}
    </div>
  );
}
