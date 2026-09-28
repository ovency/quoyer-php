# Security

## Reporting a vulnerability

Please report security issues privately by email to **support@quoyer.com**
with "Security" in the subject, not in a public GitHub issue. Include what you
found, how to reproduce it, and the SDK version. We will acknowledge within
three working days.

## Using the SDK safely

- Keep API keys server-side: in environment variables or an encrypted
  column, never in a browser, a mobile app bundle, logs or version control.
  `ClientOptions::maskedApiKey()` gives a loggable form.
- The client refuses a plain-`http` base URL except on `localhost` and
  `*.test` development hosts, and never follows redirects, so a key cannot be
  sent in clear text or to another host by accident.
- Verify every webhook with `Webhook::constructEvent()` against the raw body
  before acting on it.
- Never show `getAdminReason()` to a shopper.
