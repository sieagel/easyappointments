App.Pages.GoogleCalendarSettings = (function () {
    const $saveSettings = $('#save-settings');
    const $provider = $('#google-calendar-provider');
    const $writeCalendar = $('#google-write-calendar');
    const $onlineCalendars = $('#google-online-conflict-calendars');
    const $liveCalendars = $('#google-live-conflict-calendars');
    const $loadCalendars = $('#load-google-calendars');

    function parseList(value) {
        try {
            const parsed = JSON.parse(value || '[]');
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    }

    function deserialize(settings) {
        settings.forEach((setting) => {
            const $field = $('[data-field="' + setting.name + '"]');

            if ($field.is(':checkbox')) {
                $field.prop('checked', Boolean(Number(setting.value)));
            } else if ($field.is('select[multiple]')) {
                $field.data('saved-values', parseList(setting.value));
            } else {
                $field.val(setting.value);
            }
        });
    }

    function serialize() {
        const settings = [];

        $('[data-field]').each((index, field) => {
            const $field = $(field);
            let value;

            if ($field.is(':checkbox')) {
                value = Number($field.prop('checked'));
            } else if ($field.is('select[multiple]')) {
                value = JSON.stringify($field.val() || []);
            } else {
                value = $field.val();
            }

            settings.push({name: $field.data('field'), value});
        });

        return settings;
    }

    function renderCalendars(calendars) {
        const savedWrite = $writeCalendar.data('saved-value') || $writeCalendar.val() || '';
        const savedOnline = $onlineCalendars.data('saved-values') || [];
        const savedLive = $liveCalendars.data('saved-values') || [];

        $writeCalendar.empty().append($('<option>', {
            value: '',
            text: lang('please_select'),
        }));

        $onlineCalendars.empty();
        $liveCalendars.empty();

        calendars.forEach((calendar) => {
            const label = calendar.summary || calendar.id;

            $writeCalendar.append($('<option>', {value: calendar.id, text: label}));
            $onlineCalendars.append($('<option>', {value: calendar.id, text: label}));
            $liveCalendars.append($('<option>', {value: calendar.id, text: label}));
        });

        $writeCalendar.val(savedWrite);
        $onlineCalendars.val(savedOnline);
        $liveCalendars.val(savedLive);
    }

    function loadCalendars() {
        const providerId = $provider.val();

        if (!providerId) {
            renderCalendars([]);
            return;
        }

        $loadCalendars.prop('disabled', true);

        App.Http.GoogleCalendarSettings.getCalendars(providerId)
            .done((calendars) => renderCalendars(calendars))
            .fail((xhr) => {
                App.Layouts.Backend.displayNotification(
                    xhr.responseJSON?.message || lang('google_calendar_calendars_load_failed'),
                );
                renderCalendars([]);
            })
            .always(() => $loadCalendars.prop('disabled', false));
    }

    function onSaveSettingsClick() {
        App.Http.GoogleCalendarSettings.save(serialize()).done(() => {
            App.Layouts.Backend.displayNotification(lang('settings_saved'));
        });
    }

    function initialize() {
        deserialize(vars('google_calendar_settings'));

        $provider.on('change', () => {
            $writeCalendar.data('saved-value', '');
            $onlineCalendars.data('saved-values', []);
            $liveCalendars.data('saved-values', []);
            loadCalendars();
        });

        $loadCalendars.on('click', loadCalendars);
        $saveSettings.on('click', onSaveSettingsClick);

        if ($provider.val()) {
            loadCalendars();
        }
    }

    document.addEventListener('DOMContentLoaded', initialize);

    return {};
})();
