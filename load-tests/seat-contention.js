import http from 'k6/http';
import { check } from 'k6';
import { Counter } from 'k6/metrics';

const BASE_URL =
    __ENV.BASE_URL || 'http://nginx';

const EVENT_ID = __ENV.EVENT_ID;

const successfulReservations =
    new Counter('successful_reservations');

const seatConflicts =
    new Counter('seat_conflicts');

const unexpectedResponses =
    new Counter('unexpected_responses');

export const options = {
    setupTimeout: '60s',

    thresholds: {
        checks: ['rate==1'],
        unexpected_responses: ['count==0'],
        successful_reservations: ['count==10'],
        seat_conflicts: ['count==40'],
    },

    scenarios: {
        seat_contention: {
            executor: 'per-vu-iterations',
            vus: 50,
            iterations: 1,
            maxDuration: '30s',
        },
    },
};

export function setup() {
    if (!EVENT_ID) {
        throw new Error(
            'EVENT_ID environment variable is required'
        );
    }

    const tokens = [];

    for (let i = 1; i <= 50; i++) {
        const suffix =
            String(i).padStart(2, '0');

        const response = http.post(
            `${BASE_URL}/api/auth/login`,
            JSON.stringify({
                email:
                    `loadtest-buyer-${suffix}@atlas.test`,
                password: 'Password123!',
            }),
            {
                headers: {
                    'Content-Type':
                        'application/json',
                    Accept: 'application/json',
                },
            }
        );

        if (response.status !== 200) {
            throw new Error(
                `Login failed for buyer ${i}: ` +
                response.body
            );
        }

        tokens.push(
            response.json('token')
        );
    }

    const seatsResponse = http.get(
        `${BASE_URL}/api/events/${EVENT_ID}/seats`,
        {
            headers: {
                Accept: 'application/json',
                Authorization:
                    `Bearer ${tokens[0]}`,
            },
        }
    );

    if (seatsResponse.status !== 200) {
        throw new Error(
            'Could not retrieve seats'
        );
    }

    const seatIds = seatsResponse
        .json('data')
        .map((seat) => seat.id);

    if (seatIds.length !== 10) {
        throw new Error(
            `Expected 10 available seats, got ${seatIds.length}`
        );
    }

    return {
        tokens,
        seatIds,
    };
}

export default function (data) {
    const buyerIndex = __VU - 1;

    /*
     * 50 compradores / 10 assentos.
     *
     * Buyer 1,11,21,31,41 -> seat 1
     * Buyer 2,12,22,32,42 -> seat 2
     * ...
     *
     * Cada assento recebe cinco concorrentes.
     */
    const seatIndex =
        buyerIndex % data.seatIds.length;

    const seatId =
        data.seatIds[seatIndex];

    const response = http.post(
        `${BASE_URL}/api/events/${EVENT_ID}/reservations`,
        JSON.stringify({
            seat_ids: [seatId],
        }),
        {
            headers: {
                'Content-Type':
                    'application/json',

                Accept:
                    'application/json',

                Authorization:
                    `Bearer ${data.tokens[buyerIndex]}`,
            },
        }
    );

    if (response.status === 201) {
        successfulReservations.add(1);
    } else if (response.status === 409) {
        seatConflicts.add(1);
    } else {
        unexpectedResponses.add(1);
    }

    check(response, {
        'response is expected':
            (r) =>
                r.status === 201
                || r.status === 409,
    });
}