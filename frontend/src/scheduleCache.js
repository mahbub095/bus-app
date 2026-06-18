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
      console.log(`[ScheduleCache] Cache MISS for key: ${key}`);
      return null;
    }

    const age = Date.now() - entry.timestamp;
    if (age > this.defaultTtlMs) {
      console.log(`[ScheduleCache] Cache EXPIRED for key: ${key} (Age: ${Math.round(age / 1000)}s)`);
      this.cache.delete(key);
      return null;
    }

    console.log(`[ScheduleCache] Cache HIT for key: ${key} (Age: ${Math.round(age / 1000)}s)`);
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
    console.log(`[ScheduleCache] Cache SET for key: ${key}`);
    this.cache.set(key, {
      data,
      timestamp: Date.now()
    });
  }

  // Invalidate a specific query cache
  invalidate(from, to, date, coachType) {
    const key = this._getKey(from, to, date, coachType);
    console.log(`[ScheduleCache] Cache INVALIDATE for key: ${key}`);
    this.cache.delete(key);
  }

  // Clear all cache
  clear() {
    console.log('[ScheduleCache] Cache CLEAR');
    this.cache.clear();
  }
}

export const scheduleCache = new ScheduleCache();

// Expose to window for debugging/verification in dev environment
if (typeof window !== 'undefined') {
  window.__scheduleCache = scheduleCache;
}
