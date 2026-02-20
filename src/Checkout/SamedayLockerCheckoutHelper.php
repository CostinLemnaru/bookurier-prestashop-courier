<?php

namespace Bookurier\Checkout;

use Bookurier\Admin\Config\SamedayLockerRepository;

class SamedayLockerCheckoutHelper
{
    public function isLockerCarrierSelected(array $params, \Context $context, $configuredCarrierReference)
    {
        $idReference = (int) $configuredCarrierReference;
        if ($idReference <= 0) {
            return false;
        }

        $carrierReference = $this->extractCarrierReferenceFromParams($params);
        if ($carrierReference > 0) {
            return $carrierReference === $idReference;
        }

        if (!isset($context->cart)) {
            return false;
        }

        $idCartCarrier = (int) $context->cart->id_carrier;
        if ($idCartCarrier <= 0) {
            return false;
        }

        $carrier = new \Carrier($idCartCarrier);
        if (!\Validate::isLoadedObject($carrier)) {
            return false;
        }

        return (int) $carrier->id_reference === $idReference;
    }

    public function resolvePreferredLockerByDeliveryAddress(\Context $context, SamedayLockerRepository $lockerRepository, array $lockers)
    {
        if (!isset($context->cart)) {
            return 0;
        }

        $idAddressDelivery = (int) $context->cart->id_address_delivery;
        if ($idAddressDelivery <= 0) {
            return 0;
        }

        $deliveryAddress = new \Address($idAddressDelivery);
        if (!\Validate::isLoadedObject($deliveryAddress)) {
            return 0;
        }

        return (int) $lockerRepository->findBestLockerIdForAddress($deliveryAddress, $lockers);
    }

    private function extractCarrierReferenceFromParams(array $params)
    {
        if (isset($params['carrier']) && is_object($params['carrier'])) {
            if (property_exists($params['carrier'], 'id_reference') && (int) $params['carrier']->id_reference > 0) {
                return (int) $params['carrier']->id_reference;
            }

            if (property_exists($params['carrier'], 'id') && (int) $params['carrier']->id > 0) {
                $carrier = new \Carrier((int) $params['carrier']->id);
                if (\Validate::isLoadedObject($carrier)) {
                    return (int) $carrier->id_reference;
                }
            }
        }

        if (isset($params['carrier']) && is_array($params['carrier'])) {
            if (isset($params['carrier']['id_reference']) && (int) $params['carrier']['id_reference'] > 0) {
                return (int) $params['carrier']['id_reference'];
            }

            $idCarrier = 0;
            if (isset($params['carrier']['id'])) {
                $idCarrier = (int) $params['carrier']['id'];
            } elseif (isset($params['carrier']['id_carrier'])) {
                $idCarrier = (int) $params['carrier']['id_carrier'];
            }

            if ($idCarrier > 0) {
                $carrier = new \Carrier($idCarrier);
                if (\Validate::isLoadedObject($carrier)) {
                    return (int) $carrier->id_reference;
                }
            }
        }

        if (isset($params['id_carrier']) && (int) $params['id_carrier'] > 0) {
            $carrier = new \Carrier((int) $params['id_carrier']);
            if (\Validate::isLoadedObject($carrier)) {
                return (int) $carrier->id_reference;
            }
        }

        return 0;
    }
}
