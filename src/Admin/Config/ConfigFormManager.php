<?php

namespace Bookurier\Admin\Config;

use Bookurier\Client\Sameday\SamedayClient;

class ConfigFormManager
{
    private $module;

    private $lockerRepository;

    private $lockerSyncService;

    public function __construct($module)
    {
        $this->module = $module;
        $this->lockerRepository = new SamedayLockerRepository();
        $this->lockerSyncService = new SamedayLockerSyncService($module, $this->lockerRepository);
    }

    public function handle()
    {
        $output = '';

        if (\Tools::isSubmit(\Bookurier::ACTION_SYNC_LOCKERS)) {
            $output .= $this->processLockerSync();
        } elseif (\Tools::isSubmit(\Bookurier::ACTION_SUBMIT_CONFIG)) {
            $output .= $this->processConfigForm();
        }

        $output .= $this->renderLockerImportWarning();

        return $output . $this->renderConfigForm();
    }

    private function processConfigForm()
    {
        $apiUser = trim((string) \Tools::getValue(\Bookurier::CONFIG_API_USER, \Configuration::get(\Bookurier::CONFIG_API_USER)));
        $apiPasswordInput = (string) \Tools::getValue(\Bookurier::CONFIG_API_PASSWORD, '');
        $apiPassword = $apiPasswordInput !== '' ? $apiPasswordInput : (string) \Configuration::get(\Bookurier::CONFIG_API_PASSWORD);
        $apiKey = trim((string) \Tools::getValue(\Bookurier::CONFIG_API_KEY, \Configuration::get(\Bookurier::CONFIG_API_KEY)));
        $defaultPickupPoint = (int) \Tools::getValue(\Bookurier::CONFIG_DEFAULT_PICKUP_POINT, \Configuration::get(\Bookurier::CONFIG_DEFAULT_PICKUP_POINT));
        $defaultService = (int) \Tools::getValue(\Bookurier::CONFIG_DEFAULT_SERVICE, \Configuration::get(\Bookurier::CONFIG_DEFAULT_SERVICE));

        $samedayEnabled = (int) \Tools::getValue(\Bookurier::CONFIG_SAMEDAY_ENABLED, \Configuration::get(\Bookurier::CONFIG_SAMEDAY_ENABLED));
        $samedayUser = trim((string) \Tools::getValue(\Bookurier::CONFIG_SAMEDAY_API_USERNAME, \Configuration::get(\Bookurier::CONFIG_SAMEDAY_API_USERNAME)));
        $samedayPasswordInput = (string) \Tools::getValue(\Bookurier::CONFIG_SAMEDAY_API_PASSWORD, '');
        $samedayPassword = $samedayPasswordInput !== '' ? $samedayPasswordInput : (string) \Configuration::get(\Bookurier::CONFIG_SAMEDAY_API_PASSWORD);
        $samedayEnv = $this->normalizeSamedayEnvironment((string) \Tools::getValue(\Bookurier::CONFIG_SAMEDAY_ENV, \Configuration::get(\Bookurier::CONFIG_SAMEDAY_ENV)));
        $samedayPickupPoint = (int) \Tools::getValue(
            \Bookurier::CONFIG_SAMEDAY_PICKUP_POINT,
            (int) \Configuration::get(\Bookurier::CONFIG_SAMEDAY_PICKUP_POINT)
        );

        $errors = array();
        if ($apiUser === '') {
            $errors[] = $this->t('Bookurier API username is required.');
        }
        if ($apiPassword === '') {
            $errors[] = $this->t('Bookurier API password is required.');
        }
        if ($defaultPickupPoint <= 0) {
            $errors[] = $this->t('Bookurier default pickup point must be a positive integer.');
        }
        if (!$this->isValidBookurierService($defaultService)) {
            $errors[] = $this->t('Bookurier default service is invalid.');
        }
        if ($samedayEnabled === 1) {
            if ($samedayUser === '') {
                $errors[] = $this->t('SameDay API username is required when SameDay is enabled.');
            }
            if ($samedayPassword === '') {
                $errors[] = $this->t('SameDay API password is required when SameDay is enabled.');
            }
        }

        if (empty($errors) && $samedayEnabled === 1) {
            try {
                $syncData = $this->syncAndStoreSamedayPickupPoints($samedayUser, $samedayPassword, $samedayEnv, $samedayPickupPoint);
                $samedayPickupPoint = (int) $syncData['selected_id'];
            } catch (\Exception $e) {
                $errors[] = $this->t('SameDay pickup points could not be loaded: ') . $e->getMessage();
            }
        }

        if (!empty($errors)) {
            return $this->module->displayError(implode('<br>', $errors));
        }

        \Configuration::updateValue(\Bookurier::CONFIG_API_USER, $apiUser);
        \Configuration::updateValue(\Bookurier::CONFIG_API_KEY, $apiKey);
        \Configuration::updateValue(\Bookurier::CONFIG_DEFAULT_PICKUP_POINT, (string) $defaultPickupPoint);
        \Configuration::updateValue(\Bookurier::CONFIG_DEFAULT_SERVICE, (string) $defaultService);
        \Configuration::updateValue(\Bookurier::CONFIG_SAMEDAY_ENABLED, (string) $samedayEnabled);
        \Configuration::updateValue(\Bookurier::CONFIG_SAMEDAY_API_USERNAME, $samedayUser);
        \Configuration::updateValue(\Bookurier::CONFIG_SAMEDAY_ENV, $samedayEnv);
        \Configuration::updateValue(\Bookurier::CONFIG_SAMEDAY_PICKUP_POINT, (string) $samedayPickupPoint);

        if ($apiPasswordInput !== '') {
            \Configuration::updateValue(\Bookurier::CONFIG_API_PASSWORD, $apiPasswordInput);
        }
        if ($samedayPasswordInput !== '') {
            \Configuration::updateValue(\Bookurier::CONFIG_SAMEDAY_API_PASSWORD, $samedayPasswordInput);
        }

        $this->module->resetClients();

        return $this->module->displayConfirmation($this->t('Settings saved successfully.'));
    }

    private function processLockerSync()
    {
        if ((int) \Configuration::get(\Bookurier::CONFIG_SAMEDAY_ENABLED) !== 1) {
            return $this->module->displayError(
                $this->t('Enable SameDay before syncing lockers.')
            );
        }

        if (!$this->lockerRepository->ensureTable()) {
            return $this->module->displayError(
                $this->t('Locker storage could not be initialized.')
            );
        }

        $username = trim((string) \Configuration::get(\Bookurier::CONFIG_SAMEDAY_API_USERNAME));
        $password = trim((string) \Configuration::get(\Bookurier::CONFIG_SAMEDAY_API_PASSWORD));
        $environment = $this->normalizeSamedayEnvironment((string) \Configuration::get(\Bookurier::CONFIG_SAMEDAY_ENV));

        if ($username === '' || $password === '') {
            return $this->module->displayError(
                $this->t('SameDay API credentials are required for locker sync.')
            );
        }

        try {
            $syncedCount = $this->lockerSyncService->sync($username, $password, $environment);
        } catch (\Exception $e) {
            return $this->module->displayError(
                $this->t('SameDay lockers could not be synced: ') . $e->getMessage()
            );
        }

        return $this->module->displayConfirmation(
            sprintf($this->t('SameDay lockers synced successfully. Active lockers: %d'), (int) $syncedCount)
        );
    }

    private function renderConfigForm()
    {
        $helper = new \HelperForm();
        $helper->module = $this->module;
        $helper->name_controller = $this->module->name;
        $helper->token = \Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = \AdminController::$currentIndex . '&configure=' . $this->module->name;
        $helper->default_form_language = (int) \Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = (int) \Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->title = $this->module->displayName;
        $helper->submit_action = \Bookurier::ACTION_SUBMIT_CONFIG;
        $helper->fields_value = $this->getConfigFormValues();

        return $helper->generateForm(array($this->getConfigFormDefinition()));
    }

    private function getConfigFormDefinition()
    {
        return array(
            'form' => array(
                'legend' => array('title' => $this->t('Bookurier Courier Settings'), 'icon' => 'icon-cogs'),
                'input' => array(
                    array('type' => 'html', 'name' => 'bookurier_api_separator', 'html_content' => '<h3>' . $this->t('Bookurier API') . '</h3>'),
                    array('type' => 'text', 'label' => $this->t('Bookurier API Username'), 'name' => \Bookurier::CONFIG_API_USER),
                    array(
                        'type' => 'password',
                        'label' => $this->t('Bookurier API Password'),
                        'name' => \Bookurier::CONFIG_API_PASSWORD,
                        'desc' => $this->t('Leave empty to keep current password.'),
                    ),
                    array('type' => 'text', 'label' => $this->t('Bookurier API Key (Tracking)'), 'name' => \Bookurier::CONFIG_API_KEY),
                    array(
                        'type' => 'text',
                        'label' => $this->t('Bookurier Default Pickup Point'),
                        'name' => \Bookurier::CONFIG_DEFAULT_PICKUP_POINT,
                        'class' => 'fixed-width-md',
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->t('Bookurier Default Service'),
                        'name' => \Bookurier::CONFIG_DEFAULT_SERVICE,
                        'options' => array('query' => $this->getBookurierServiceOptions(), 'id' => 'id', 'name' => 'name'),
                    ),
                    array('type' => 'html', 'name' => 'sameday_api_separator', 'html_content' => '<hr><h3>' . $this->t('SameDay API') . '</h3>'),
                    array(
                        'type' => 'switch',
                        'label' => $this->t('Enable SameDay'),
                        'name' => \Bookurier::CONFIG_SAMEDAY_ENABLED,
                        'is_bool' => true,
                        'values' => array(
                            array('id' => 'sameday_on', 'value' => 1, 'label' => $this->t('Yes')),
                            array('id' => 'sameday_off', 'value' => 0, 'label' => $this->t('No')),
                        ),
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->t('SameDay Environment'),
                        'name' => \Bookurier::CONFIG_SAMEDAY_ENV,
                        'options' => array(
                            'query' => array(
                                array('id' => 'demo', 'name' => $this->t('Demo')),
                                array('id' => 'prod', 'name' => $this->t('Production')),
                            ),
                            'id' => 'id',
                            'name' => 'name',
                        ),
                    ),
                    array('type' => 'text', 'label' => $this->t('SameDay API Username'), 'name' => \Bookurier::CONFIG_SAMEDAY_API_USERNAME),
                    array(
                        'type' => 'password',
                        'label' => $this->t('SameDay API Password'),
                        'name' => \Bookurier::CONFIG_SAMEDAY_API_PASSWORD,
                        'desc' => $this->t('Leave empty to keep current password.'),
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->t('SameDay Pickup Point'),
                        'name' => \Bookurier::CONFIG_SAMEDAY_PICKUP_POINT,
                        'class' => 'bookurier-sameday-pickup',
                        'options' => array(
                            'query' => $this->getSamedayPickupPointOptions(),
                            'id' => 'id',
                            'name' => 'name',
                        ),
                        'desc' => $this->t('List is refreshed from SameDay when you save settings and SameDay is enabled.'),
                    ),
                    array(
                        'type' => 'html',
                        'name' => 'sameday_lockers_sync',
                        'html_content' => '<div class="bookurier-sameday-sync"><button type="submit" class="btn btn-default" name="' . \Bookurier::ACTION_SYNC_LOCKERS . '" value="1"><i class="process-icon-refresh"></i> ' . $this->t('Sync SameDay Lockers') . '</button></div>',
                    ),
                ),
                'submit' => array('title' => $this->t('Save')),
            ),
        );
    }

    private function getConfigFormValues()
    {
        return array(
            \Bookurier::CONFIG_API_USER => (string) \Tools::getValue(\Bookurier::CONFIG_API_USER, (string) \Configuration::get(\Bookurier::CONFIG_API_USER)),
            \Bookurier::CONFIG_API_PASSWORD => '',
            \Bookurier::CONFIG_API_KEY => (string) \Tools::getValue(\Bookurier::CONFIG_API_KEY, (string) \Configuration::get(\Bookurier::CONFIG_API_KEY)),
            \Bookurier::CONFIG_DEFAULT_PICKUP_POINT => (string) \Tools::getValue(
                \Bookurier::CONFIG_DEFAULT_PICKUP_POINT,
                (string) \Configuration::get(\Bookurier::CONFIG_DEFAULT_PICKUP_POINT)
            ),
            \Bookurier::CONFIG_DEFAULT_SERVICE => (int) \Tools::getValue(
                \Bookurier::CONFIG_DEFAULT_SERVICE,
                (int) (\Configuration::get(\Bookurier::CONFIG_DEFAULT_SERVICE) ?: 9)
            ),
            \Bookurier::CONFIG_SAMEDAY_ENABLED => (int) \Tools::getValue(
                \Bookurier::CONFIG_SAMEDAY_ENABLED,
                (int) (\Configuration::get(\Bookurier::CONFIG_SAMEDAY_ENABLED) ?: 0)
            ),
            \Bookurier::CONFIG_SAMEDAY_ENV => (string) \Tools::getValue(
                \Bookurier::CONFIG_SAMEDAY_ENV,
                (string) (\Configuration::get(\Bookurier::CONFIG_SAMEDAY_ENV) ?: 'demo')
            ),
            \Bookurier::CONFIG_SAMEDAY_API_USERNAME => (string) \Tools::getValue(
                \Bookurier::CONFIG_SAMEDAY_API_USERNAME,
                (string) \Configuration::get(\Bookurier::CONFIG_SAMEDAY_API_USERNAME)
            ),
            \Bookurier::CONFIG_SAMEDAY_API_PASSWORD => '',
            \Bookurier::CONFIG_SAMEDAY_PICKUP_POINT => (int) \Tools::getValue(
                \Bookurier::CONFIG_SAMEDAY_PICKUP_POINT,
                (int) \Configuration::get(\Bookurier::CONFIG_SAMEDAY_PICKUP_POINT)
            ),
        );
    }

    private function syncAndStoreSamedayPickupPoints($username, $password, $environment, $preferredPickupPoint)
    {
        $pickupPoints = $this->fetchSamedayPickupPoints($username, $password, $environment);
        if (empty($pickupPoints)) {
            throw new \RuntimeException($this->t('No pickup points were returned by SameDay.'));
        }

        $selectedId = $this->resolveSamedayPickupPointId($pickupPoints, (int) $preferredPickupPoint);
        if ($selectedId <= 0) {
            throw new \RuntimeException($this->t('Could not determine a valid SameDay pickup point.'));
        }

        \Configuration::updateValue(\Bookurier::CONFIG_SAMEDAY_PICKUP_POINTS_CACHE, json_encode($pickupPoints));

        return array(
            'selected_id' => $selectedId,
            'count' => count($pickupPoints),
        );
    }

    private function fetchSamedayPickupPoints($username, $password, $environment)
    {
        $client = new SamedayClient((string) $username, (string) $password, (string) $environment, null, $this->module->getLogger());
        $client->authenticate(true);

        $rawPickupPoints = $client->getPickupPoints(1, 500);
        $options = array();

        foreach ($rawPickupPoints as $pickupPoint) {
            $id = (int) ($pickupPoint['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $alias = trim((string) ($pickupPoint['alias'] ?? ''));
            $address = trim((string) ($pickupPoint['address'] ?? ''));
            $suffix = trim($alias . ' - ' . $address, ' -');

            $options[] = array(
                'id' => $id,
                'name' => '[' . $id . '] ' . ($suffix !== '' ? $suffix : $this->t('Pickup Point')),
                'default' => !empty($pickupPoint['defaultPickupPoint']),
            );
        }

        return $options;
    }

    private function resolveSamedayPickupPointId(array $options, $preferredPickupPoint)
    {
        if ((int) $preferredPickupPoint > 0) {
            foreach ($options as $option) {
                if ((int) ($option['id'] ?? 0) === (int) $preferredPickupPoint) {
                    return (int) $option['id'];
                }
            }
        }

        foreach ($options as $option) {
            if (!empty($option['default']) && !empty($option['id'])) {
                return (int) $option['id'];
            }
        }

        return !empty($options[0]['id']) ? (int) $options[0]['id'] : 0;
    }

    private function getSamedayPickupPointOptions()
    {
        $decoded = json_decode((string) \Configuration::get(\Bookurier::CONFIG_SAMEDAY_PICKUP_POINTS_CACHE), true);
        if (!is_array($decoded) || empty($decoded)) {
            return array(array('id' => 0, 'name' => $this->t('No pickup points loaded yet.')));
        }

        $options = array();
        foreach ($decoded as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $id = (int) ($entry['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $name = trim((string) ($entry['name'] ?? ''));
            $options[] = array('id' => $id, 'name' => $name !== '' ? $name : '[' . $id . ']');
        }

        if (empty($options)) {
            return array(array('id' => 0, 'name' => $this->t('No pickup points loaded yet.')));
        }

        return $options;
    }

    private function normalizeSamedayEnvironment($environment)
    {
        return strtolower((string) $environment) === 'prod' ? 'prod' : 'demo';
    }

    private function isValidBookurierService($service)
    {
        foreach ($this->getBookurierServiceOptions() as $option) {
            if ((int) $option['id'] === (int) $service) {
                return true;
            }
        }

        return false;
    }

    private function getBookurierServiceOptions()
    {
        return array(
            array('id' => 1, 'name' => 'Bucuresti 24h (1)'),
            array('id' => 3, 'name' => 'Metropolitan (3)'),
            array('id' => 5, 'name' => 'Ilfov Extins (5)'),
            array('id' => 7, 'name' => 'Bucuresti Today (7)'),
            array('id' => 8, 'name' => 'National Economic (8)'),
            array('id' => 9, 'name' => 'National 24 (9)'),
            array('id' => 11, 'name' => 'National Premium (11)'),
        );
    }

    private function renderLockerImportWarning()
    {
        if ((int) \Configuration::get(\Bookurier::CONFIG_SAMEDAY_ENABLED) !== 1) {
            return '';
        }

        if ($this->getSamedayLockerCount() > 0) {
            return '';
        }

        return $this->module->displayWarning(
            $this->t('No SameDay lockers are imported yet. Locker checkout selection will not be available until lockers are synced.')
        );
    }

    private function getSamedayLockerCount()
    {
        return $this->lockerRepository->countActive();
    }

    private function t($message)
    {
        return $this->module->l($message);
    }
}
