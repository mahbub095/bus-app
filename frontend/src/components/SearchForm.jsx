import React from 'react';

export default function SearchForm({
  stations,
  searchParams,
  setSearchParams,
  minDateStr,
  handleSearch,
  isLoading
}) {
  return (
    <section className="search-banner">
      <div className="container">
        <h1 className="banner-title">Premium Bus Booking Portal</h1>
        <p className="banner-subtitle">
          Search routes, visual seat grids, and get instant PNR confirmations
        </p>

        <div className="search-card">
          <form className="search-form" onSubmit={handleSearch}>
            <div className="input-group">
              <label htmlFor="from-station">From</label>
              <div className="input-with-icon">
                <span className="input-icon">📍</span>
                <select
                  id="from-station"
                  className="input-control"
                  value={searchParams.from}
                  onChange={(e) => setSearchParams((prev) => ({ ...prev, from: e.target.value }))}
                >
                  <option value="">&nbsp;&nbsp;Select departure...</option>
                  {stations.map((st) => (
                    <option key={st.id} value={st.id}>
                      &nbsp;&nbsp;{st.name} ({st.district})
                    </option>
                  ))}
                </select>
              </div>
            </div>

            <div className="input-group">
              <label htmlFor="to-station">To</label>
              <div className="input-with-icon">
                <span className="input-icon">🏁</span>
                <select
                  id="to-station"
                  className="input-control"
                  value={searchParams.to}
                  onChange={(e) => setSearchParams((prev) => ({ ...prev, to: e.target.value }))}
                >
                  <option value="">&nbsp;&nbsp;Select arrival...</option>
                  {stations.map((st) => (
                    <option key={st.id} value={st.id}>
                      &nbsp;&nbsp;{st.name} ({st.district})
                    </option>
                  ))}
                </select>
              </div>
            </div>

            <div className="input-group">
              <label htmlFor="journey-date">Date of Journey</label>
              <div className="input-with-icon">
                <span className="input-icon">📅</span>
                <input
                  id="journey-date"
                  type="date"
                  className="input-control"
                  min={minDateStr}
                  value={searchParams.date}
                  onChange={(e) => setSearchParams((prev) => ({ ...prev, date: e.target.value }))}
                />
              </div>
            </div>

            <div className="input-group">
              <label htmlFor="coach-type">Coach Type</label>
              <div className="input-with-icon">
                <span className="input-icon">🚌</span>
                <select
                  id="coach-type"
                  className="input-control"
                  value={searchParams.coachType}
                  onChange={(e) => setSearchParams((prev) => ({ ...prev, coachType: e.target.value }))}
                >
                  <option value="All">&nbsp;&nbsp;All Coach Types</option>
                  <option value="AC">&nbsp;&nbsp;AC (Air Conditioned)</option>
                  <option value="Non AC">&nbsp;&nbsp;Non AC</option>
                </select>
              </div>
            </div>
          </form>

          <div style={{ marginTop: '20px', display: 'flex', justifyContent: 'flex-end' }}>
            <button
              className="btn btn-primary search-submit-btn"
              style={{ maxWidth: '250px' }}
              onClick={handleSearch}
              disabled={isLoading}
            >
              {isLoading ? 'Searching...' : 'Search Buses'}
            </button>
          </div>
        </div>
      </div>
    </section>
  );
}
