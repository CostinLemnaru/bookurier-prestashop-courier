<?php

namespace Bookurier\Admin\Config;

class ConfigFormManager
{
    private $module;

    private $lockerRepository;

    private $configSaveHandler;

    private $serviceOptionsProvider;

    private $lockerSyncActionHandler;

    public function __construct($module)
    {
        $this->module = $module;
        $this->lockerRepository = new SamedayLockerRepository();
        $this->serviceOptionsProvider = new BookurierServiceOptionsProvider();
        $this->configSaveHandler = new ConfigSaveHandler(
            $module,
            new SamedayPickupPointSyncService($module),
            $this->serviceOptionsProvider
        );
        $this->lockerSyncActionHandler = new LockerSyncActionHandler(
            $module,
            $this->lockerRepository
        );
    }

    public function handle()
    {
        $output = '';

        if (\Tools::isSubmit(\Bookurier::ACTION_SYNC_LOCKERS)) {
            $output .= $this->lockerSyncActionHandler->handle();
        } elseif (\Tools::isSubmit(\Bookurier::ACTION_SUBMIT_CONFIG)) {
            $output .= $this->configSaveHandler->handle();
        }

        $output .= $this->renderLockerImportWarning();

        return $output . $this->renderConfigForm();
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
        $lockerCount = $this->getSamedayLockerCount();

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
                        'options' => array('query' => $this->serviceOptionsProvider->getOptions(), 'id' => 'id', 'name' => 'name'),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->t('Auto generate AWB'),
                        'name' => \Bookurier::CONFIG_AUTO_AWB_ENABLED,
                        'is_bool' => true,
                        'values' => array(
                            array('id' => 'bookurier_auto_awb_on', 'value' => 1, 'label' => $this->t('Yes')),
                            array('id' => 'bookurier_auto_awb_off', 'value' => 0, 'label' => $this->t('No')),
                        ),
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->t('Auto AWB allowed statuses'),
                        'name' => \Bookurier::CONFIG_AUTO_AWB_ALLOWED_STATUSES . '[]',
                        'multiple' => true,
                        'size' => 8,
                        'options' => array(
                            'query' => $this->getOrderStatusOptions(),
                            'id' => 'id',
                            'name' => 'name',
                        ),
                        'desc' => $this->t('When auto generate is ON, AWB is generated only for selected order statuses.'),
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
                        'type' => 'select',
                        'label' => $this->t('SameDay Package Type'),
                        'name' => \Bookurier::CONFIG_SAMEDAY_PACKAGE_TYPE,
                        'options' => array(
                            'query' => $this->getSamedayPackageTypeOptions(),
                            'id' => 'id',
                            'name' => 'name',
                        ),
                        'desc' => $this->t('Default package type sent to SameDay when generating AWB.'),
                    ),
                    array(
                        'type' => 'html',
                        'name' => 'sameday_lockers_sync',
                        'html_content' => '<div class="bookurier-sameday-sync"><button type="submit" class="btn btn-default" name="' . \Bookurier::ACTION_SYNC_LOCKERS . '" value="1"><i class="process-icon-refresh"></i> ' . $this->t('Sync SameDay Lockers') . '</button><p style="margin-top:8px;"><strong>' . $this->t('Current imported active lockers:') . '</strong> ' . (int) $lockerCount . '</p></div>',
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
                (int) $this->getConfigValueOrDefault(\Bookurier::CONFIG_DEFAULT_SERVICE, 9)
            ),
            \Bookurier::CONFIG_AUTO_AWB_ENABLED => (int) \Tools::getValue(
                \Bookurier::CONFIG_AUTO_AWB_ENABLED,
                (int) $this->getConfigValueOrDefault(\Bookurier::CONFIG_AUTO_AWB_ENABLED, 1)
            ),
            \Bookurier::CONFIG_AUTO_AWB_ALLOWED_STATUSES . '[]' => $this->resolveAutoAwbStatusValues(),
            \Bookurier::CONFIG_SAMEDAY_ENABLED => (int) \Tools::getValue(
                \Bookurier::CONFIG_SAMEDAY_ENABLED,
                (int) $this->getConfigValueOrDefault(\Bookurier::CONFIG_SAMEDAY_ENABLED, 0)
            ),
            \Bookurier::CONFIG_SAMEDAY_ENV => (string) \Tools::getValue(
                \Bookurier::CONFIG_SAMEDAY_ENV,
                (string) $this->getConfigValueOrDefault(\Bookurier::CONFIG_SAMEDAY_ENV, 'demo')
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
            \Bookurier::CONFIG_SAMEDAY_PACKAGE_TYPE => (int) \Tools::getValue(
                \Bookurier::CONFIG_SAMEDAY_PACKAGE_TYPE,
                (int) $this->getConfigValueOrDefault(\Bookurier::CONFIG_SAMEDAY_PACKAGE_TYPE, 0)
            ),
        );
    }

    private function getConfigValueOrDefault($key, $defaultValue)
    {
        $value = \Configuration::get((string) $key);
        if ($value === false || $value === null || $value === '') {
            return $defaultValue;
        }

        return $value;
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

    private function getSamedayPackageTypeOptions()
    {
        return array(
            array('id' => 0, 'name' => $this->t('Parcel')),
            array('id' => 1, 'name' => $this->t('Envelope')),
            array('id' => 2, 'name' => $this->t('Large Package')),
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

    private function getOrderStatusOptions()
    {
        $idLang = (int) (\Context::getContext()->language->id ?: \Configuration::get('PS_LANG_DEFAULT'));
        $states = \OrderState::getOrderStates($idLang);
        if (!is_array($states)) {
            return array();
        }

        $options = array();
        foreach ($states as $state) {
            $idState = (int) ($state['id_order_state'] ?? 0);
            if ($idState <= 0) {
                continue;
            }

            $name = trim((string) ($state['name'] ?? ''));
            $options[] = array(
                'id' => $idState,
                'name' => '[' . $idState . '] ' . ($name !== '' ? $name : ('Status #' . $idState)),
            );
        }

        return $options;
    }

    private function resolveAutoAwbStatusValues()
    {
        $rawInput = \Tools::getValue(\Bookurier::CONFIG_AUTO_AWB_ALLOWED_STATUSES, null);
        if ($rawInput === null) {
            $rawInput = \Tools::getValue(\Bookurier::CONFIG_AUTO_AWB_ALLOWED_STATUSES . '[]', null);
        }

        if ($rawInput === null) {
            if (is_object($this->module) && method_exists($this->module, 'getAutoAwbAllowedStatusIds')) {
                return (array) $this->module->getAutoAwbAllowedStatusIds();
            }

            return $this->parseStatusIds((string) \Configuration::get(\Bookurier::CONFIG_AUTO_AWB_ALLOWED_STATUSES));
        }

        if (!is_array($rawInput)) {
            $rawInput = explode(',', (string) $rawInput);
        }

        $statusIds = array();
        foreach ($rawInput as $value) {
            $statusId = (int) $value;
            if ($statusId > 0) {
                $statusIds[] = $statusId;
            }
        }

        $statusIds = array_values(array_unique($statusIds));
        sort($statusIds);

        return $statusIds;
    }

    private function parseStatusIds($rawValue)
    {
        $rawValue = trim((string) $rawValue);
        if ($rawValue === '') {
            return array();
        }

        $values = explode(',', (string) $rawValue);
        $statusIds = array();
        foreach ($values as $value) {
            $statusId = (int) trim((string) $value);
            if ($statusId > 0) {
                $statusIds[] = $statusId;
            }
        }

        $statusIds = array_values(array_unique($statusIds));
        sort($statusIds);

        return $statusIds;
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
