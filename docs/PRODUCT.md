# Product Overview

## Purpose

Studio Booking SaaS lets small studios run their schedule, bookings and memberships from one hosted app. Many studios (tenants) share the same system, and each one sees only its own data. The product is deliberately ordinary so that the engineering (isolation, concurrency, billing, roles) is the focus.

## Primary Users

- **Studio owner** — configures the studio, manages staff, plans and billing, reads the audit log.
- **Staff / instructor** — publishes classes and slots, manages bookings and the waitlist.
- **Customer** — browses the schedule, books and cancels, buys a membership or class pack.
- **Integrator** — embeds the booking widget using the public JSON API.

## Key User Flows

- A customer browses a studio's schedule, books a class, and receives a confirmation email.
- Two customers try to book the last spot at the same moment: exactly one succeeds, the other joins the waitlist.
- A booking is cancelled inside the allowed window and the first person on the waitlist is promoted automatically.
- A customer subscribes to a monthly membership (Stripe test mode); a failed payment starts a grace period and then suspends booking rights.
- An owner reviews who changed what, and when, in the audit log.

## Non-Goals

- No real payments, real customer data or real email delivery.
- No marketing website, generic plugin system or white-label theming.
- No multiple billing models beyond one monthly membership and one class-pack option.

## Product Constraints

- **Tenant isolation is a hard requirement:** no query, page, API response or queued job may expose another tenant's data, and tests must prove it.
- Money-affecting state changes (bookings, entitlements, payments) are idempotent and safe to retry.
- Secrets come from environment variables and are never committed.
- The whole system starts with one command (`docker compose up`) and a seeded demo of two tenants.
