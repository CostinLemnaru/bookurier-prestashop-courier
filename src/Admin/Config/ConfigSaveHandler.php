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
        $autoAwbEnabled = (int) \Tools::getValue(
            \Bookurier::CONFIG_AUTO_AWB_ENABLED,
            \Configuration::get(\Bookurier::CONFIG_AUTO_AWB_ENABLED)
        );
        $autoAwbStatusIds = $this->resolveAutoAwbStatusIdsFromRequest();

        $samedayEnabled = (int) \Tools::getValue(\Bookurier::CONFIG_SAMEDAY_ENABLED, \Configuration::get(\Bookurier::CONFIG_SAMEDAY_ENABLED));
        $samedayUser = trim((string) \Tools::getValue(\Bookurier::CONFIG_SAMEDAY_API_USERNAME, \Configuration::get(\Bookurier::CONFIG_SAMEDAY_API_USERNAME)));
        $samedayPasswordInput = (string) \Tools::getValue(\Bookurier::CONFIG_SAMEDAY_API_PASSWORD, '');
        $samedayPassword = $samedayPasswordInput !== '' ? $samedayPasswordInput : (string) \Configuration::get(\Bookurier::CONFIG_SAMEDAY_API_PASSWORD);
        $samedayEnv = $this->normalizeSamedayEnvironment((string) \Tools::getValue(\Bookurier::CONFIG_SAMEDAY_ENV, \Configuration::get(\Bookurier::CONFIG_SAMEDAY_ENV)));
        $samedayPickupPoint = (int) \Tools::getValue(
            \Bookurier::CONFIG_SAMEDAY_PICKUP_POINT,
            (int) \Configuration::get(\Bookurier::CONFIG_SAMEDAY_PICKUP_POINT)
        );
        $samedayPackageType = (int) \Tools::getValue(
            \Bookurier::CONFIG_SAMEDAY_PACKAGE_TYPE,
            (int) \Configuration::get(\Bookurier::CONFIG_SAMEDAY_PACKAGE_TYPE)
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
        if ($autoAwbEnabled === 1 && empty($autoAwbStatusIds)) {
            $errors[] = $this->t('At least one order status must be selected when Auto generate AWB is enabled.');
        }
        if (!empty($autoAwbStatusIds)) {
            $invalidStatusIds = array_diff($autoAwbStatusIds, $this->getAvailableOrderStatusIds());
            if (!empty($invalidStatusIds)) {
                $errors[] = $this->t('Auto AWB allowed statuses contain invalid values.');
            }
        }
        if ($samedayEnabled === 1) {
            if ($samedayUser === '') {
                $errors[] = $this->t('SameDay API username is required when SameDay is enabled.');
            }
            if ($samedayPassword === '') {
                $errors[] = $this->t('SameDay API password is required when SameDay is enabled.');
            }
        }
        if (!$this->isValidSamedayPackageType($samedayPackageType)) {
            $errors[] = $this->t('SameDay package type is invalid.');
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
        \Configuration::updateValue(\Bookurier::CONFIG_AUTO_AWB_ENABLED, (string) $autoAwbEnabled);
        \Configuration::updateValue(\Bookurier::CONFIG_AUTO_AWB_ALLOWED_STATUSES, implode(',', $autoAwbStatusIds));
        \Configuration::updateValue(\Bookurier::CONFIG_SAMEDAY_ENABLED, (string) $samedayEnabled);
        \Configuration::updateValue(\Bookurier::CONFIG_SAMEDAY_API_USERNAME, $samedayUser);
        \Configuration::updateValue(\Bookurier::CONFIG_SAMEDAY_ENV, $samedayEnv);
        \Configuration::updateValue(\Bookurier::CONFIG_SAMEDAY_PICKUP_POINT, (string) $samedayPickupPoint);
        \Configuration::updateValue(\Bookurier::CONFIG_SAMEDAY_PACKAGE_TYPE, (string) $samedayPackageType);
        \Configuration::updateValue(\Bookurier::CONFIG_SAMEDAY_SERVICES_CACHE, '{}');

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

    private function isValidSamedayPackageType($packageType)
    {
        return in_array((int) $packageType, array(0, 1, 2), true);
    }

    private function resolveAutoAwbStatusIdsFromRequest()
    {
        $input = \Tools::getValue(\Bookurier::CONFIG_AUTO_AWB_ALLOWED_STATUSES, null);
        if ($input === null) {
            $input = \Tools::getValue(\Bookurier::CONFIG_AUTO_AWB_ALLOWED_STATUSES . '[]', array());
        }

        if (!is_array($input)) {
            $input = explode(',', (string) $input);
        }

        $statusIds = array();
        foreach ($input as $value) {
            $statusId = (int) $value;
            if ($statusId > 0) {
                $statusIds[] = $statusId;
            }
        }

        $statusIds = array_values(array_unique($statusIds));
        sort($statusIds);

        return $statusIds;
    }

    private function getAvailableOrderStatusIds()
    {
        $idLang = (int) (\Context::getContext()->language->id ?: \Configuration::get('PS_LANG_DEFAULT'));
        $states = \OrderState::getOrderStates($idLang);
        if (!is_array($states)) {
            return array();
        }

        $statusIds = array();
        foreach ($states as $state) {
            $idState = (int) ($state['id_order_state'] ?? 0);
            if ($idState > 0) {
                $statusIds[] = $idState;
            }
        }

        $statusIds = array_values(array_unique($statusIds));
        sort($statusIds);

        return $statusIds;
    }

    private function t($message)
    {
        return $this->module->l($message);
    }
}
