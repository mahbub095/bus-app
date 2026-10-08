const DEV = import.meta.env.DEV;

class ScheduleCache {
  constructor(defaultTtlMs = 60000) {
    this.cache = new Map();
    this.defaultTtlMs = defaultTtlMs;
  }

  // Generate cache key from search parameters
  _getKey(from, to, date, coachType) {
    return `${from}_${to}_${date}_${coachType}`;
  }

  // Get both data and metadata from cache
  getEntry(from, to, date, coachType) {
    const key = this._getKey(from, to, date, coachType);
    const entry = this.cache.get(key);

    if (!entry) {
      return null;
    }

    const age = Date.now() - entry.timestamp;
    if (age > this.defaultTtlMs) {
      this.cache.delete(key);
      return null;
    }

    return entry;
  }

  // Get data only from cache
  get(from, to, date, coachType) {
    const entry = this.getEntry(from, to, date, coachType);
    return entry ? entry.data : null;
  }

  // Save data to cache
  set(from, to, date, coachType, data) {
    const key = this._getKey(from, to, date, coachType);
    this.cache.set(key, {
      data,
      timestamp: Date.now()
    });
  }

  // Invalidate a specific query cache
  invalidate(from, to, date, coachType) {
    const key = this._getKey(from, to, date, coachType);
    this.cache.delete(key);
  }

  // Clear all cache
  clear() {
    this.cache.clear();
  }
}

export const scheduleCache = new ScheduleCache();

// Expose to window only in development for debugging
if (DEV && typeof window !== 'undefined') {
  window.__scheduleCache = scheduleCache;
}
