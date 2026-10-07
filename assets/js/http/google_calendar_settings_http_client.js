App.Http.GoogleCalendarSettings = (function () {
    function save(googleCalendarSettings) {
        const url = App.Utils.Url.siteUrl('google_calendar_settings/save');
        return $.post(url, {
            csrf_token: vars('csrf_token'),
            google_calendar_settings: googleCalendarSettings,
        });
    }

    function getCalendars(providerId) {
        const url = App.Utils.Url.siteUrl('google_calendar_settings/get_calendars');
        return $.post(url, {
            csrf_token: vars('csrf_token'),
            provider_id: providerId,
        });
    }

    return {
        save,
        getCalendars,
    };
})();
