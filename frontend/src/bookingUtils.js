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
  const layout = schedule?.bus?.seat_layout || '2+2';
  const totalSeats = schedule?.bus?.total_seats || 36;
  const rowLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];

  let seatCodes = [];
  const customGrid = schedule?.bus?.seat_layout_grid;
  
  if (customGrid) {
    const gridObj = typeof customGrid === 'string' ? JSON.parse(customGrid) : customGrid;
    if (gridObj.lower || gridObj.upper) {
      ['lower', 'upper'].forEach(deck => {
        if (gridObj[deck]) {
          gridObj[deck].forEach(row => {
            row.forEach(cell => {
              if (cell.type === 'seat' && cell.label) {
                seatCodes.push(cell.label);
              }
            });
          });
        }
      });
    } else {
      gridObj.forEach(row => {
        row.forEach(cell => {
          if (cell.type === 'seat' && cell.label) {
            seatCodes.push(cell.label);
          }
        });
      });
    }
  } else if (layout === '1+2') {
    let seatsCount = 0;
    for (let r = 0; r < rowLetters.length; r++) {
      let row = rowLetters[r];
      for (let num = 1; num <= 3; num++) {
        if (seatsCount >= totalSeats) break;
        seatCodes.push(row + num);
        seatsCount++;
      }
      if (seatsCount >= totalSeats) break;
    }
  } else if (layout === 'sleeper') {
    let lowerCount = Math.ceil(totalSeats / 2);
    let upperCount = totalSeats - lowerCount;

    // Lower deck
    let seatsCount = 0;
    for (let r = 0; r < rowLetters.length; r++) {
      let row = rowLetters[r];
      for (let num = 1; num <= 3; num++) {
        if (seatsCount >= lowerCount) break;
        seatCodes.push('L-' + row + num);
        seatsCount++;
      }
      if (seatsCount >= lowerCount) break;
    }

    // Upper deck
    seatsCount = 0;
    for (let r = 0; r < rowLetters.length; r++) {
      let row = rowLetters[r];
      for (let num = 1; num <= 3; num++) {
        if (seatsCount >= upperCount) break;
        seatCodes.push('U-' + row + num);
        seatsCount++;
      }
      if (seatsCount >= upperCount) break;
    }
  } else if (layout === '2+2_last5') {
    let seatsCount = 0;
    let remainingSeats = totalSeats - 5;
    let normalRows = Math.ceil(remainingSeats / 4);
    
    for (let r = 0; r < normalRows; r++) {
      let row = rowLetters[r];
      for (let num = 1; num <= 4; num++) {
        if (seatsCount >= remainingSeats) break;
        seatCodes.push(row + num);
        seatsCount++;
      }
      if (seatsCount >= remainingSeats) break;
    }

    let lastRowLetter = rowLetters[normalRows] || 'Z';
    for (let num = 1; num <= 5; num++) {
      seatCodes.push(lastRowLetter + num);
    }
  } else {
    // '2+2'
    let seatsCount = 0;
    for (let r = 0; r < rowLetters.length; r++) {
      let row = rowLetters[r];
      for (let num = 1; num <= 4; num++) {
        if (seatsCount >= totalSeats) break;
        seatCodes.push(row + num);
        seatsCount++;
      }
      if (seatsCount >= totalSeats) break;
    }
  }

  seatCodes.forEach(code => {
    map[code] = booked.includes(code) ? 'sold_m' : 'available';
  });

  return map;
}
