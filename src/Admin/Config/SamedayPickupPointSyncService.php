<?php

namespace Bookurier\Admin\Config;

use Bookurier\Client\Sameday\SamedayClient;

class SamedayPickupPointSyncService
{
    private $module;

    public function __construct($module)
    {
        $this->module = $module;
    }

    public function syncAndStore($username, $password, $environment, $preferredPickupPoint)
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

        $page = 1;
        $perPage = 500;
        $maxPages = 100;
        $rawPickupPoints = array();

        do {
            $batch = $client->getPickupPoints($page, $perPage);
            foreach ($batch as $row) {
                $rawPickupPoints[] = $row;
            }
            $page++;
        } while (!empty($batch) && count($batch) === $perPage && $page <= $maxPages);

        $options = array();
        foreach ($rawPickupPoints as $pickupPoint) {
            $id = (int) ($pickupPoint['id'] ?? 0);
            $isActive = !isset($pickupPoint['status']) || !empty($pickupPoint['status']);
            if ($id <= 0 || !$isActive) {
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

    private function t($message)
    {
        return $this->module->l($message);
    }
}
