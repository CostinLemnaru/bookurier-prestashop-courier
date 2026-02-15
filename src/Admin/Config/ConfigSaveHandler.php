<?php

namespace Bookurier\Admin\Config;

class ConfigSaveHandler
{
    private $module;

    private $pickupPointSyncService;

    private $serviceOptionsProvider;

    public function __construct(
        $module,
        SamedayPickupPointSyncService $pickupPointSyncService = null,
        BookurierServiceOptionsProvider $serviceOptionsProvider = null
    ) {
        $this->module = $module;
        $this->pickupPointSyncService = $pickupPointSyncService ?: new SamedayPickupPointSyncService($module);
        $this->serviceOptionsProvider = $serviceOptionsProvider ?: new BookurierServiceOptionsProvider();
    }

    public function handle()
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
        if (!$this->serviceOptionsProvider->isValid($defaultService)) {
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
                $syncData = $this->pickupPointSyncService->syncAndStore($samedayUser, $samedayPassword, $samedayEnv, $samedayPickupPoint);
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

    private function normalizeSamedayEnvironment($environment)
    {
        return strtolower((string) $environment) === 'prod' ? 'prod' : 'demo';
    }

    private function t($message)
    {
        return $this->module->l($message);
    }
}
