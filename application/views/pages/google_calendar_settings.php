<?php extend('layouts/backend_layout'); ?>

<?php section('content'); ?>

<div id="google-calendar-settings-page" class="container backend-page py-3">
    <div class="row">
        <div class="col-sm-3">
            <?php component('settings_nav'); ?>
        </div>

        <div id="google-calendar-settings" class="col-sm-9">
            <form>
                <fieldset>
                    <div class="d-flex justify-content-between align-items-center border-bottom mb-4 py-2">
                        <h4 class="mb-0 fw-light"><?= lang('google_calendar') ?></h4>

                        <div>
                            <a href="<?= site_url('integrations') ?>" class="btn btn-outline-primary me-2">
                                <i class="fas fa-chevron-left me-2"></i><?= lang('back') ?>
                            </a>

                            <?php if (can('edit', PRIV_SYSTEM_SETTINGS)): ?>
                                <button type="button" id="save-settings" class="btn btn-primary">
                                    <i class="fas fa-check-square me-2"></i><?= lang('save') ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-text text-muted mb-4"><?= lang('google_calendar_info') ?></div>

                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" id="google-sync-feature"
                               data-field="google_sync_feature">
                        <label class="form-check-label" for="google-sync-feature"><?= lang('active') ?></label>
                    </div>

                    <div class="mb-3">
                        <label for="google-client-id" class="form-label"><?= lang('google_client_id') ?></label>
                        <input type="text" class="form-control" id="google-client-id"
                               data-field="google_client_id" placeholder="<?= lang('google_client_id') ?>">
                        <div class="form-text text-muted"><?= lang('google_client_id_info') ?></div>
                    </div>

                    <div class="mb-3">
                        <label for="google-client-secret" class="form-label"><?= lang('google_client_secret') ?></label>
                        <input type="password" class="form-control" id="google-client-secret"
                               data-field="google_client_secret" placeholder="<?= lang('google_client_secret') ?>">
                        <div class="form-text text-muted"><?= lang('google_client_secret_info') ?></div>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <?= lang('google_calendar_setup_info') ?>
                        <a href="https://console.developers.google.com" target="_blank">Google Cloud Console</a>
                    </div>

                    <hr class="my-4">

                    <h5 class="fw-light mb-3"><?= lang('google_calendar_routing') ?></h5>
                    <p class="text-muted"><?= lang('google_calendar_routing_info') ?></p>

                    <div class="mb-3">
                        <label for="google-calendar-provider" class="form-label"><?= lang('google_calendar_account') ?></label>
                        <select id="google-calendar-provider" class="form-select" data-field="google_calendar_provider_id">
                            <option value=""><?= lang('please_select') ?></option>
                            <?php foreach (vars('google_calendar_providers') as $provider): ?>
                                <option value="<?= (int) $provider['id'] ?>">
                                    <?= html_escape($provider['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text text-muted"><?= lang('google_calendar_account_info') ?></div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label for="google-write-calendar" class="form-label mb-0">
                                <?= lang('google_write_calendar') ?>
                            </label>
                            <button type="button" id="load-google-calendars" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-sync-alt me-1"></i><?= lang('reload') ?>
                            </button>
                        </div>
                        <select id="google-write-calendar" class="form-select" data-field="google_write_calendar">
                            <option value=""><?= lang('please_select') ?></option>
                        </select>
                        <div class="form-text text-muted"><?= lang('google_write_calendar_info') ?></div>
                    </div>

                    <div class="mb-3">
                        <label for="google-online-conflict-calendars" class="form-label">
                            <?= lang('google_online_conflict_calendars') ?>
                        </label>
                        <select id="google-online-conflict-calendars" class="form-select" multiple size="6"
                                data-field="google_online_conflict_calendars"></select>
                        <div class="form-text text-muted"><?= lang('google_online_conflict_calendars_info') ?></div>
                    </div>

                    <div class="mb-4">
                        <label for="google-live-conflict-calendars" class="form-label">
                            <?= lang('google_live_conflict_calendars') ?>
                        </label>
                        <select id="google-live-conflict-calendars" class="form-select" multiple size="6"
                                data-field="google_live_conflict_calendars"></select>
                        <div class="form-text text-muted"><?= lang('google_live_conflict_calendars_info') ?></div>
                    </div>

                    <hr class="my-4">

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="google-meet-link-generation"
                               data-field="google_meet_link_generation">
                        <label class="form-check-label" for="google-meet-link-generation">
                            <?= lang('google_meet_link_generation') ?>
                        </label>
                    </div>
                    <div class="form-text text-muted mb-4"><?= lang('google_meet_link_generation_info') ?></div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="display-add-to-google-calendar"
                               data-field="display_add_to_google_calendar">
                        <label class="form-check-label" for="display-add-to-google-calendar">
                            <?= lang('display_add_to_google_calendar') ?>
                        </label>
                    </div>
                    <div class="form-text text-muted"><?= lang('display_add_to_google_calendar_info') ?></div>
                </fieldset>
            </form>
        </div>
    </div>
</div>

<?php end_section('content'); ?>

<?php section('scripts'); ?>
<script src="<?= asset_url('assets/js/http/google_calendar_settings_http_client.js') ?>"></script>
<script src="<?= asset_url('assets/js/pages/google_calendar_settings.js') ?>"></script>
<?php end_section('scripts'); ?>
