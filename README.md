# Webtanan Lucky Wheel

Webtanan Lucky Wheel is a production-oriented WordPress campaign plugin for building registration-gated “spin to win” experiences. Reward selection is always performed on the server with weighted randomness; the browser only animates the result returned by the server.

## Features

- Seven configurable wheel sections with label, icon, color, probability and active state.
- Registration gate with name, mobile, email and password; users are logged in automatically and receive an initial attempt.
- WooCommerce one-use coupons restricted to the recipient, with fixed or percentage discount and expiry.
- Internal wallet fallback (`wp_webtanan_wallet`) when WooCommerce is unavailable.
- Extra-attempt rewards (`+1` and `+2`) and configurable custom rewards.
- Atomic-style short-lived locks, idempotency keys, WordPress nonces, capability checks and IP rate limiting.
- WooCommerce My Account endpoint: `/my-account/my-rewards/`.
- Dashboard metrics and a users/rewards history table.
- Responsive, mobile-first premium purple/gold UI with wheel animation, result modal and confetti.

## Installation

1. Copy `webtanan-lucky-wheel` to `wp-content/plugins/` (or upload a zip from the WordPress Plugins screen).
2. Activate **Webtanan Lucky Wheel**. Activation creates the wheel log and wallet tables and seeds the default campaign.
3. Open **Lucky Wheel → Wheel Settings** to configure the campaign.
4. Add `[webtanan_lucky_wheel]` to any page, landing page or template.

WooCommerce is optional. When active, coupon rewards create a single-use WooCommerce coupon and attach it to the winning user. Without WooCommerce, coupon-type rewards are automatically credited to the internal wallet.

## Data and security

The plugin stores wheel events in `wp_webtanan_wheel_logs` and wallet transactions in `wp_webtanan_wallet` (the prefix follows the site configuration). Spin results, probability and attempt checks happen in PHP. AJAX requests require a nonce and authentication; duplicate request IDs, rapid concurrent spins and excessive IP traffic are rejected.

## Screenshots

The repository keeps the plugin UI self-contained so screenshots can be captured directly from the target theme after activation. Recommended captures are the public registration gate, the spinning/result modal, and the Lucky Wheel admin dashboard/settings screens.

## Development

The code is split into focused OOP services under `includes/`: database, wallet, WooCommerce, rewards, wheel engine, AJAX, admin and shortcode. Public and admin assets live in their respective folders. Keep changes WordPress Coding Standards compatible and run PHP syntax checks before submitting a pull request.

## Roadmap

- Optional OTP login and phone verification.
- SMS/email reward notifications.
- CRM and analytics integrations, sales reports and A/B testing.
- Scheduled campaigns and advanced anti-fraud controls.

## Contributing

Open an issue describing the problem or feature, then submit a focused pull request with reproduction steps and tests. Do not include production credentials or `wp-config.php`.

## License

MIT. See [LICENSE](LICENSE).
