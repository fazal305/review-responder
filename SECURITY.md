# Security Policy

## Reporting a vulnerability

Please report security issues privately by email to **fazalabbas2002@gmail.com** rather than opening a public issue. Include steps to reproduce and the affected version or commit.

You can expect an acknowledgement within 5 days. Once a fix is ready, it will be released and the report credited if you'd like.

## Scope

In scope: the Laravel API (`api/`) and the React app (`web/`). Please only test against your own local copy — do not scan or load-test any hosted instance.

## What's already in place

- Bearer tokens (Laravel Sanctum) that expire after 7 days; the API sets no cookies
- Google sign-in uses the authorization-code flow with `state` and PKCE; the browser receives only a single-use, one-minute code that must be redeemed from the same tab that started sign-in
- Google refresh and access tokens are encrypted at rest and never sent to the browser
- Every location, review and draft is checked against its owner; other users' records return 404
- Admin-only routes require the admin role, which can only be granted from the server command line
- Rate limits on sign-in, demo accounts and the API
- Security headers on API responses; CORS limited to the configured web app origin
