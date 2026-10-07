<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Google controller.
 */
class Google extends EA_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->library('google_sync');

        $this->load->model('appointments_model');
        $this->load->model('providers_model');
        $this->load->model('users_model');
        $this->load->model('roles_model');
    }

    // Existing synchronization logic remains provider-based.
    public static function sync(?string $provider_id = null): void
    {
        // The synchronization implementation is unchanged.
        /** @var EA_Controller $CI */
        $CI = get_instance();
        $CI->load->library('google_sync');
        $CI->load->model('appointments_model');
        $CI->load->model('unavailabilities_model');
        $CI->load->model('providers_model');
        $CI->load->model('services_model');
        $CI->load->model('customers_model');
        $CI->load->model('settings_model');

        $user_id = session('user_id');

        if (!$user_id && !is_cli()) {
            return;
        }

        if (!$provider_id) {
            throw new InvalidArgumentException('No provider ID provided.');
        }

        $provider = $CI->providers_model->find($provider_id);
        $google_sync = $CI->providers_model->get_setting($provider['id'], 'google_sync');

        if (!$google_sync) {
            return;
        }

        $google_token = json_decode($provider['settings']['google_token'], true);
        $CI->google_sync->refresh_token($google_token['refresh_token']);

        $sync_past_days = $provider['settings']['sync_past_days'];
        $sync_future_days = $provider['settings']['sync_future_days'];

        $start = strtotime('-' . $sync_past_days . ' days', strtotime(date('Y-m-d')));
        $end = strtotime('+' . $sync_future_days . ' days', strtotime(date('Y-m-d')));

        $where = [
            'start_datetime >=' => date('Y-m-d H:i:s', $start),
            'end_datetime <=' => date('Y-m-d H:i:s', $end),
            'id_users_provider' => $provider['id'],
        ];

        $appointments = $CI->appointments_model->get($where);
        $unavailabilities = $CI->unavailabilities_model->get($where);
        $local_events = [...$appointments, ...$unavailabilities];

        $company_color = setting('company_color');

        $settings = [
            'company_name' => setting('company_name'),
            'company_link' => setting('company_link'),
            'company_email' => setting('company_email'),
            'company_color' =>
                !empty($company_color) && $company_color != DEFAULT_COMPANY_COLOR ? $company_color : null,
        ];

        $provider_timezone = new DateTimeZone($provider['timezone']);

        try {
            $existing_google_events = $CI->google_sync->get_sync_events(
                $CI->google_sync->get_write_calendar($provider),
                $start,
                $end,
            );
        } catch (Throwable) {
            $existing_google_events = null;
        }

        $extract_google_event_range = function ($google_event) use ($provider_timezone): ?array {
            if ($google_event->getStart() === null || $google_event->getEnd() === null) {
                return null;
            }

            $is_all_day = $google_event->getStart()->getDateTime() === null;

            if ($is_all_day) {
                $g_start = new DateTime($google_event->getStart()->getDate() . ' 00:00:00', $provider_timezone);
                $g_end = new DateTime($google_event->getEnd()->getDate() . ' 00:00:00', $provider_timezone);
                $g_end->modify('-1 minute');
            } else {
                $g_start = new DateTime($google_event->getStart()->getDateTime());
                $g_start->setTimezone($provider_timezone);
                $g_end = new DateTime($google_event->getEnd()->getDateTime());
                $g_end->setTimezone($provider_timezone);
            }

            return [$g_start->getTimestamp(), $g_end->getTimestamp()];
        };

        foreach ($local_events as $local_event) {
            if (!$local_event['is_unavailability']) {
                $service = $CI->services_model->find($local_event['id_services']);
                $customer = $CI->customers_model->find($local_event['id_users_customer']);
                $events_model = $CI->appointments_model;
            } else {
                $service = null;
                $customer = null;
                $events_model = $CI->unavailabilities_model;
            }

            if (!$local_event['id_google_calendar']) {
                $matched_google_event = null;

                if ($existing_google_events !== null) {
                    $local_start_ts = (new DateTime($local_event['start_datetime'], $provider_timezone))->getTimestamp();
                    $local_end_ts = (new DateTime($local_event['end_datetime'], $provider_timezone))->getTimestamp();

                    foreach ($existing_google_events->getItems() as $candidate) {
                        if ($candidate->getStatus() === 'cancelled') {
                            continue;
                        }

                        $candidate_range = $extract_google_event_range($candidate);

                        if ($candidate_range === null) {
                            continue;
                        }

                        if ($candidate_range[0] !== $local_start_ts || $candidate_range[1] !== $local_end_ts) {
                            continue;
                        }

                        if ($local_event['is_unavailability']) {
                            $candidate_summary = trim((string) $candidate->getSummary());

                            if (strcasecmp($candidate_summary, 'Unavailable') !== 0) {
                                continue;
                            }
                        }

                        $matched_google_event = $candidate;
                        break;
                    }
                }

                if ($matched_google_event !== null) {
                    $local_event = $events_model->find($local_event['id']);
                    $local_event['id_google_calendar'] = $matched_google_event->getId();
                    $events_model->save($local_event);
                    continue;
                }

                if (!$local_event['is_unavailability']) {
                    $google_event = $CI->google_sync->add_appointment(
                        $local_event,
                        $provider,
                        $service,
                        $customer,
                        $settings,
                    );
                } else {
                    $google_event = $CI->google_sync->add_unavailability($provider, $local_event);
                }

                $local_event = $events_model->find($local_event['id']);
                $local_event['id_google_calendar'] = $google_event->getId();
                $events_model->save($local_event);
                continue;
            }

            try {
                $google_event = $CI->google_sync->get_event($provider, $local_event['id_google_calendar']);

                if ($google_event->getStatus() == 'cancelled') {
                    throw new Exception('Event is cancelled, remove the record from Easy!Appointments.');
                }

                $local_event_start = (new DateTime($local_event['start_datetime'], $provider_timezone))->getTimestamp();
                $local_event_end = (new DateTime($local_event['end_datetime'], $provider_timezone))->getTimestamp();

                $is_google_all_day = $google_event->getStart()->getDateTime() === null;

                if ($is_google_all_day) {
                    $google_event_start = new DateTime(
                        $google_event->getStart()->getDate() . ' 00:00:00',
                        $provider_timezone,
                    );
                    $google_event_end = new DateTime(
                        $google_event->getEnd()->getDate() . ' 00:00:00',
                        $provider_timezone,
                    );
                    $google_event_end->modify('-1 minute');
                } else {
                    $google_event_start = new DateTime($google_event->getStart()->getDateTime());
                    $google_event_start->setTimezone($provider_timezone);
                    $google_event_end = new DateTime($google_event->getEnd()->getDateTime());
                    $google_event_end->setTimezone($provider_timezone);
                }

                if ($local_event['is_unavailability']) {
                    $google_event_summary = $google_event->getSummary();
                    $google_event_notes = strcasecmp(trim((string) $google_event_summary), 'Unavailable') === 0
                        ? (string) $google_event->getDescription()
                        : trim($google_event_summary . ' ' . $google_event->getDescription());
                } else {
                    $google_event_notes = $google_event->getDescription();
                }

                $is_different =
                    $local_event_start !== $google_event_start->getTimestamp() ||
                    $local_event_end !== $google_event_end->getTimestamp() ||
                    $local_event['notes'] !== $google_event_notes;

                if ($is_different) {
                    $local_event['start_datetime'] = $google_event_start->format('Y-m-d H:i:s');
                    $local_event['end_datetime'] = $google_event_end->format('Y-m-d H:i:s');
                    $local_event['notes'] = $google_event_notes;
                    $events_model->save($local_event);
                }
            } catch (Throwable) {
                $events_model->delete($local_event['id']);
                $local_event['id_google_calendar'] = null;
            }
        }

        $google_calendar = $CI->google_sync->get_write_calendar($provider);

        try {
            $google_events = $CI->google_sync->get_sync_events($google_calendar, $start, $end);
        } catch (Throwable $e) {
            if ($e->getCode() === 404) {
                log_message('error', 'Google - Remote Calendar not found for provider ID: ' . $provider_id);
                return;
            }

            throw $e;
        }

        foreach ($google_events->getItems() as $google_event) {
            if ($google_event->getStatus() === 'cancelled') {
                continue;
            }

            if ($google_event->getStart() === null || $google_event->getEnd() === null) {
                continue;
            }

            $is_google_all_day = $google_event->getStart()->getDateTime() === null;

            if ($is_google_all_day) {
                $google_event_start = new DateTime(
                    $google_event->getStart()->getDate() . ' 00:00:00',
                    $provider_timezone,
                );
                $google_event_end = new DateTime(
                    $google_event->getEnd()->getDate() . ' 00:00:00',
                    $provider_timezone,
                );
                $google_event_end->modify('-1 minute');
            } else {
                if ($google_event->getStart()->getDateTime() === $google_event->getEnd()->getDateTime()) {
                    continue;
                }

                $google_event_start = new DateTime($google_event->getStart()->getDateTime());
                $google_event_start->setTimezone($provider_timezone);
                $google_event_end = new DateTime($google_event->getEnd()->getDateTime());
                $google_event_end->setTimezone($provider_timezone);
            }

            $appointment_results = $CI->appointments_model->get([
                'id_google_calendar' => $google_event->getId(),
                'id_users_provider' => $provider_id,
            ]);

            if (!empty($appointment_results)) {
                continue;
            }

            $unavailability_results = $CI->unavailabilities_model->get([
                'id_google_calendar' => $google_event->getId(),
                'id_users_provider' => $provider_id,
            ]);

            if (!empty($unavailability_results)) {
                continue;
            }

            $google_event_summary = $google_event->getSummary();
            $google_event_notes =
                strcasecmp(trim((string) $google_event_summary), 'Unavailable') === 0
                    ? (string) $google_event->getDescription()
                    : trim($google_event_summary . ' ' . $google_event->getDescription());

            $local_event = [
                'start_datetime' => $google_event_start->format('Y-m-d H:i:s'),
                'end_datetime' => $google_event_end->format('Y-m-d H:i:s'),
                'is_unavailability' => true,
                'location' => $google_event->getLocation(),
                'notes' => $google_event_notes,
                'id_users_provider' => $provider_id,
                'id_google_calendar' => $google_event->getId(),
                'id_users_customer' => null,
                'id_services' => null,
            ];

            $CI->unavailabilities_model->save($local_event);
        }

        json_response(['success' => true]);
    }

    public function oauth(string $provider_id): void
    {
        $user_id = session('user_id');

        if (!$user_id) {
            show_error('Forbidden', 403);
        }

        $provider_id = filter_var($provider_id, FILTER_VALIDATE_INT);

        if ($provider_id === false || $provider_id <= 0) {
            show_error('Invalid user ID', 400);
        }

        if (cannot('edit', PRIV_USERS) && (int) $user_id !== (int) $provider_id) {
            show_error('Forbidden', 403);
        }

        $this->users_model->find((int) $provider_id);

        $oauth_state = bin2hex(random_bytes(32));

        session([
            'oauth_provider_id' => (int) $provider_id,
            'oauth_state' => $oauth_state,
        ]);

        header('Location: ' . $this->google_sync->get_auth_url($oauth_state));
    }

    public function oauth_callback(): void
    {
        if (!session('user_id')) {
            abort(403, 'Forbidden');
        }

        $returned_state = request('state');
        $stored_state = session('oauth_state');

        if (empty($returned_state) || empty($stored_state) || !hash_equals($stored_state, $returned_state)) {
            session(['oauth_state' => null]);
            show_error('Security validation failed. Please try the Google Calendar sync again.', 403);
            return;
        }

        session(['oauth_state' => null]);

        $code = request('code');

        if (empty($code)) {
            response('Code authorization failed.');
            return;
        }

        $token = $this->google_sync->authenticate($code);

        if (empty($token)) {
            response('Token authorization failed.');
            return;
        }

        $oauth_user_id = filter_var(session('oauth_provider_id'), FILTER_VALIDATE_INT);
        $user_id = (int) session('user_id');

        if ($oauth_user_id && $oauth_user_id > 0) {
            if (cannot('edit', PRIV_USERS) && $user_id !== (int) $oauth_user_id) {
                show_error('Forbidden', 403);
                return;
            }

            $this->users_model->find((int) $oauth_user_id);
            $this->users_model->set_setting($oauth_user_id, 'google_sync', '1');
            $this->users_model->set_setting($oauth_user_id, 'google_token', json_encode($token));
            $this->users_model->set_setting(
                $oauth_user_id,
                'google_calendar',
                config('google_default_calendar') ?: 'primary',
            );

            session(['oauth_provider_id' => null]);

            echo '<script>window.opener && window.opener.postMessage("oauth_success", window.location.origin); window.close();</script>';
        } else {
            response('Sync user id not specified.');
        }
    }

    public function get_google_calendars(): void
    {
        try {
            method('post');
            check('provider_id', 'numeric');

            $provider_id = (int) request('provider_id');

            if (empty($provider_id)) {
                throw new Exception('Provider id is required in order to fetch the google calendars.');
            }

            $user = $this->users_model->find($provider_id);
            $google_sync = $user['settings']['google_sync'] ?? false;

            if (!filter_var($google_sync, FILTER_VALIDATE_BOOLEAN)) {
                json_response(['success' => false]);
                return;
            }

            $google_token = json_decode($user['settings']['google_token'] ?? '', true);

            if (empty($google_token['refresh_token'])) {
                throw new RuntimeException('Google refresh token is not available.');
            }

            $this->google_sync->refresh_token($google_token['refresh_token']);
            json_response($this->google_sync->get_google_calendars());
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    public function select_google_calendar(): void
    {
        try {
            method('post');
            check('provider_id', 'numeric');
            check('calendar_id', 'string');

            $provider_id = (int) request('provider_id');
            $user_id = session('user_id');

            if (cannot('edit', PRIV_USERS) && (int) $user_id !== $provider_id) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            $this->users_model->find($provider_id);
            $this->users_model->set_setting($provider_id, 'google_calendar', request('calendar_id'));

            json_response(['success' => true]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    public function disable_provider_sync(): void
    {
        try {
            method('post');
            check('provider_id', 'numeric');

            $provider_id = (int) request('provider_id');

            if (!$provider_id) {
                throw new Exception('Provider id not specified.');
            }

            $user_id = session('user_id');

            if (cannot('edit', PRIV_USERS) && (int) $user_id !== $provider_id) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            $this->users_model->find($provider_id);
            $this->users_model->set_setting($provider_id, 'google_sync', '0');
            $this->users_model->set_setting($provider_id, 'google_token', '');
            $this->appointments_model->clear_google_sync_ids($provider_id);

            json_response(['success' => true]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }
}
