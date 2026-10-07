# EsotericTrips Google Calendar extensions

This fork keeps the Easy!Appointments 1.6.0 Google Calendar architecture and adds three targeted improvements.

## 1. Provider Google Calendar selection

After Google OAuth, the provider can select the concrete Google Calendar used for Easy!Appointments synchronization. The selected calendar is stored as the provider's `google_calendar` setting.

## 2. Google Meet persistence

Google Meet conference creation is asynchronous. The integration now retries fetching the event when the conference is still pending and stores the resulting video URI in `appointments.meeting_link`.

The appointment array is passed by reference through synchronization so the saved Meet URL is not overwritten when the Google event ID is saved.

## 3. Secondary conflict calendar

A second Google Calendar can be used only for availability conflicts on selected service locations.

Add the following optional constants to the private root `config.php` (do not commit real Calendar IDs):

```php
const GOOGLE_DEFAULT_CALENDAR = 'your-primary-calendar-id';
const GOOGLE_SECONDARY_CONFLICT_CALENDAR = 'your-secondary-calendar-id';
const GOOGLE_SECONDARY_CONFLICT_LOCATION_KEYWORDS = ['Center Manas'];
```

- The primary/provider calendar remains the write calendar.
- The secondary calendar is read-only for conflict checking.
- Services whose location contains one of the configured keywords are blocked when the secondary calendar is busy.
- Online services are unaffected.
- No booking is written to the secondary calendar.

If `GOOGLE_DEFAULT_CALENDAR` is empty, the normal Easy!Appointments default (`primary`) is used and the provider calendar can still be selected through the Google Calendar sync UI.

## Security

Google API HTTP requests keep TLS certificate verification enabled.

## Current target workflow

### Online
Leon calendar → conflict check → write to Leon calendar.

### Center Manas / live
Leon calendar + configured secondary Manas calendar → conflict check → write to Leon calendar.

The secondary calendar is intentionally not used as a booking destination.
