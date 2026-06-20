/** Promo validation — concurrent coupon checks */
import { sleep } from 'k6';
import { baseUrl, thresholds, PROMO_CODES } from '../config/index.js';
import { ApiClient } from '../lib/client.js';
import { randomItem } from '../helpers/index.js';

export const options = {
    vus: 30,
    duration: '1m',
    thresholds: thresholds.strict,
};

export default function () {
    const api = new ApiClient(baseUrl());
    api.checkPromotion(randomItem(PROMO_CODES));
    sleep(1 + Math.random() * 2);
}
