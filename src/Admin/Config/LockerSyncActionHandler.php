<?php

namespace Bookurier\Admin\Config;

class LockerSyncActionHandler
{
    private $module;

    private $lockerRepository;

    private $lockerSyncService;

    public function __construct(
        $module,
        SamedayLockerRepository $lockerRepository = null,
        SamedayLockerSyncService $lockerSyncService = null
    ) {
        $this->module = $module;
        $this->lockerRepository = $lockerRepository ?: new SamedayLockerRepository();
        $this->lockerSyncService = $lockerSyncService ?: new SamedayLockerSyncService($module, $this->lockerRepository);
    }

    public function handle()
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
        $environment = strtolower((string) \Configuration::get(\Bookurier::CONFIG_SAMEDAY_ENV)) === 'demo' ? 'demo' : 'prod';

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

    private function t($message)
    {
        return $this->module->l($message);
    }
}
