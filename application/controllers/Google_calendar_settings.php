<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Google Calendar integration settings.
 */
class Google_calendar_settings extends EA_Controller
{
    public function __construct()
    {
        parent::__construct();

        if (!can('edit', PRIV_SYSTEM_SETTINGS)) {
            show_error('Forbidden', 403);
        }

        $this->load->model('providers_model');
        $this->load->model('settings_model');
        $this->load->library('google_sync');
    }

    public function index(): void
    {
        $providers = [];

        // Google Calendar ownership is independent from the E!A provider role.
        // List all users so the administrator can explicitly choose which E!A
        // account owns the Google OAuth connection.
        foreach ($this->providers_model->get(null, null, null, 'first_name ASC, last_name ASC') as $user) {
            $providers[] = [
                'id' => (int) $user['id'],
                'name' => trim($user['first_name'] . ' ' . $user['last_name']),
                'google_connected' =>
                    filter_var($user['settings']['google_sync'] ?? false, FILTER_VALIDATE_BOOLEAN) &&
                    !empty($user['settings']['google_token']),
            ];
        }

        $selected_provider_id = (int) setting('google_calendar_provider_id', 0);

        if ($selected_provider_id === 0 && count($providers) === 1) {
            $selected_provider_id = $providers[0]['id'];
        }

        $google_calendar_settings = [
            ['name' => 'google_sync_feature', 'value' => setting('google_sync_feature', '0')],
            ['name' => 'google_client_id', 'value' => setting('google_client_id', '')],
            ['name' => 'google_client_secret', 'value' => ''],
            ['name' => 'google_meet_link_generation', 'value' => setting('google_meet_link_generation', '0')],
            ['name' => 'display_add_to_google_calendar', 'value' => setting('display_add_to_google_calendar', '1')],
            ['name' => 'google_calendar_provider_id', 'value' => $selected_provider_id],
            ['name' => 'google_write_calendar', 'value' => setting('google_write_calendar', '')],
            ['name' => 'google_online_conflict_calendars', 'value' => setting('google_online_conflict_calendars', '[]')],
            ['name' => 'google_live_conflict_calendars', 'value' => setting('google_live_conflict_calendars', '[]')],
        ];

        script_vars([
            'user_id' => session('user_id'),
            'role_slug' => session('role_slug'),
            'google_calendar_settings' => array_merge(
                filter_sensitive_settings($google_calendar_settings),
                [['name' => 'google_client_secret', 'value' => setting('google_client_secret', '') !== '' ? '********' : '']],
            ),
            'google_calendar_providers' => $providers,
        ]);

        html_vars([
            'page_title' => lang('settings'),
            'active_menu' => PRIV_SYSTEM_SETTINGS,
            'user_display_name' => $this->accounts->get_user_display_name(session('user_id')),
        ]);

        $this->load->view('pages/google_calendar_settings');
    }

    /**
     * Return calendars available to the selected connected Google account.
     */
    public function get_calendars(): void
    {
        try {
            method('post');

            $provider_id = (int) request('provider_id');

            if ($provider_id <= 0) {
                throw new InvalidArgumentException('Google Calendar provider is required.');
            }

            $user = $this->providers_model->find($provider_id);

            if (
                !filter_var($user['settings']['google_sync'] ?? false, FILTER_VALIDATE_BOOLEAN) ||
                empty($user['settings']['google_token'])
            ) {
                throw new RuntimeException('The selected Google account has no active Calendar connection.');
            }

            $google_token = json_decode($user['settings']['google_token'], true);

            if (empty($google_token['refresh_token'])) {
                throw new RuntimeException('The selected Google account has no Google refresh token.');
            }

            $this->google_sync->refresh_token($google_token['refresh_token']);

            json_response($this->google_sync->get_google_calendars());
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    public function save(): void
    {
        try {
            if (cannot('edit', PRIV_SYSTEM_SETTINGS)) {
                abort(403, 'Forbidden');
            }

            check('google_calendar_settings', 'array|null');

            $allowed = [
                'google_sync_feature',
                'google_client_id',
                'google_client_secret',
                'google_meet_link_generation',
                'display_add_to_google_calendar',
                'google_calendar_provider_id',
                'google_write_calendar',
                'google_online_conflict_calendars',
                'google_live_conflict_calendars',
            ];

            foreach (request('google_calendar_settings', []) as $item) {
                if (
                    !is_array($item) ||
                    empty($item['name']) ||
                    !in_array($item['name'], $allowed, true)
                ) {
                    continue;
                }

                $name = (string) $item['name'];
                $value = $item['value'] ?? '';

                if (
                    $name === 'google_client_secret' &&
                    in_array(trim((string) $value), ['', '********'], true)
                ) {
                    continue;
                }

                setting([$name => $value]);
            }

            response();
        } catch (Throwable $e) {
            json_exception($e);
        }
    }
}
