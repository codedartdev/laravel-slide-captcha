## 0.3.1 — 2026-10-12

- Preserve JSON object/list distinctions in form binding; reject fingerprints from older canonicalization. Pending tokens must be solved again after upgrade.
- Serialize challenge validation with a cache lock to prevent concurrent issuance of multiple proofs.

# Changelog

## 0.3.0

- Require `form_data` when verifying a challenge. The token is bound to every
  submitted form value using a server-side HMAC; raw values are never cached.
- Reject modified, added or removed fields when consuming the token. Both a
  successful use and a mismatch consume the token, under an atomic cache lock.
- The Blade widget collects its enclosing form and clears verification on edits,
  including edits while a verification response is in flight.
- Old unbound tokens are invalid with the default configuration. Upgrade API
  clients before enabling this release. See the migration guide in README.
