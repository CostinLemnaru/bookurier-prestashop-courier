<?php

namespace Bookurier\Admin\Config;

use Bookurier\Client\Sameday\SamedayClient;
use Bookurier\DTO\Sameday\LockerDto;

class SamedayLockerSyncService
{
    private $module;

    private $lockerRepository;

    public function __construct($module, ?SamedayLockerRepository $lockerRepository = null)
    {
        $this->module = $module;
        $this->lockerRepository = $lockerRepository ?: new SamedayLockerRepository();
    }

    public function sync($username, $password, $environment)
    {
        $lockers = $this->fetchFromApi($username, $password, $environment);
        if (empty($lockers)) {
            throw new \RuntimeException($this->module->l('No lockers were returned by SameDay.'));
        }

        return $this->lockerRepository->upsertMany($lockers);
    }

    private function fetchFromApi($username, $password, $environment)
    {
        $client = new SamedayClient((string) $username, (string) $password, (string) $environment, $this->module->getLogger());
        $client->authenticate(true);

        $page = 1;
        $perPage = 500;
        $maxPages = 200;
        $allLockers = array();

        do {
            $batch = $client->getLockers($page, $perPage);

            foreach ($batch as $locker) {
                if (!$locker instanceof LockerDto) {
                    continue;
                }

                $allLockers[] = $locker->toArray();
            }

            $page++;
        } while (!empty($batch) && count($batch) === $perPage && $page <= $maxPages);

        return $allLockers;
    }
}
