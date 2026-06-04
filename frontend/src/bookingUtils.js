export const SEAT_ROWS = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];

export const SEAT_STATUS_LABELS = {
  available: 'Available',
  selected: 'Selected',
  blocked: 'Blocked',
  booked_m: 'Booked (M)',
  booked_f: 'Booked (F)',
  sold_m: 'Sold (M)',
  sold_f: 'Sold (F)',
};

export function seatStatusClass(status, isSelected = false) {
  if (isSelected) return 'selected';
  return status || 'available';
}

export function isSeatSelectable(status) {
  return status === 'available';
}

export function isSeatOccupied(status) {
  return status && status !== 'available' && status !== 'blocked';
}

export function formatBdt(amount) {
  return `৳ ${Number(amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

export function calcPricing(schedule, seatCount, paymentMethod = 'bKash') {
  const fare = Number(schedule?.fare || 0);
  const perSeat = schedule?.pricing || {};
  const applyGateway = String(paymentMethod).toLowerCase() !== 'cash';

  const seatFare = fare * seatCount;
  const serviceCharge = (perSeat.service_charge ?? 20) * seatCount;
  const gatewayCharge = applyGateway ? (perSeat.gateway_charge ?? 16) * seatCount : 0;
  const scDiscount = (perSeat.sc_discount ?? 20) * seatCount;
  const gcDiscount = applyGateway ? (perSeat.gc_discount ?? 16) * seatCount : 0;
  const total = Math.max(0, seatFare + serviceCharge + gatewayCharge - scDiscount - gcDiscount);

  return {
    seatFare,
    serviceCharge,
    gatewayCharge,
    scDiscount,
    gcDiscount,
    total,
  };
}

export function getSeatMap(schedule) {
  if (schedule?.seat_map && typeof schedule.seat_map === 'object') {
    return schedule.seat_map;
  }
  const map = {};
  const booked = schedule?.booked_seats || [];
  SEAT_ROWS.forEach((row) => {
    [1, 2, 3, 4].forEach((n) => {
      const code = `${row}${n}`;
      map[code] = booked.includes(code) ? 'sold_m' : 'available';
    });
  });
  return map;
}
