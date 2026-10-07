# EsotericTrips Google Calendar extensions

This fork keeps the Easy!Appointments 1.6.0 Google Calendar architecture and adds targeted improvements for explicit calendar routing and Google Meet persistence.

## 1. Explicit calendar routing in the E!A UI

Calendar routing is configured at:

**Settings → Integrations → Google Calendar**

The administrator selects:

- **Google Calendar account** — the connected E!A provider account whose OAuth token is used.
- **Write calendar** — the one Google Calendar where E!A creates and updates appointments.
- **Online conflict calendars** — one or more calendars that are read for conflicts for online services.
- **Live conflict calendars** — one or more calendars that are read for conflicts for in-person/live services.

The write calendar and conflict calendars are independent. A calendar is only read for conflicts when explicitly selected.

No Google Calendar is silently assumed by this routing layer.

## 2. Google Meet persistence

Google Meet conference creation is asynchronous. The integration retries fetching the event when the conference is still pending and stores the resulting video URI in `appointments.meeting_link`.

The appointment array is passed by reference through synchronization so the saved Meet URL is not overwritten when the Google event ID is saved.

The appointment is reloaded before notifications are rendered so the confirmation email can use the persisted Meet link.

## 3. Conflict checking

For each booking request, E!A checks the explicitly selected conflict calendars for the relevant service type:

- **Online service** → calendars selected under **Online conflict calendars**.
- **Live/in-person service** → calendars selected under **Live conflict calendars**.

If a configured conflict calendar cannot be read, availability fails closed and the affected slots are not offered.

Conflict calendars are read-only. E!A never writes bookings to them.

## Current target workflow

### Online
Selected online conflict calendars → conflict check → selected write calendar.

### Live / Center Manas
Selected live conflict calendars (for example Leon + Manas) → conflict check → selected write calendar.

The write destination is always the calendar selected explicitly in E!A settings.

## Security

Google API HTTP requests keep TLS certificate verification enabled.
