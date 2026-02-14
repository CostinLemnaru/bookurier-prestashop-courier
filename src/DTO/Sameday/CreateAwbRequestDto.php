<?php
/**
 * DTO for SameDay AWB creation payload.
 */

namespace Bookurier\DTO\Sameday;

class CreateAwbRequestDto
{
    /**
     * @var array<string, mixed>
     */
    private $data;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->data = array(
            'pickupPoint' => isset($data['pickupPoint']) ? (int) $data['pickupPoint'] : 0,
            'contactPerson' => isset($data['contactPerson']) ? $data['contactPerson'] : null,
            'packageType' => isset($data['packageType']) ? (int) $data['packageType'] : 2,
            'packageNumber' => isset($data['packageNumber']) ? (int) $data['packageNumber'] : 1,
            'packageWeight' => isset($data['packageWeight']) ? (float) $data['packageWeight'] : 1.0,
            'service' => isset($data['service']) ? (int) $data['service'] : 0,
            'awbPayment' => isset($data['awbPayment']) ? (int) $data['awbPayment'] : 1,
            'cashOnDelivery' => isset($data['cashOnDelivery']) ? (float) $data['cashOnDelivery'] : 0.0,
            'cashOnDeliveryReturns' => isset($data['cashOnDeliveryReturns']) ? (float) $data['cashOnDeliveryReturns'] : 0.0,
            'insuredValue' => isset($data['insuredValue']) ? (float) $data['insuredValue'] : 0.0,
            'thirdPartyPickup' => isset($data['thirdPartyPickup']) ? (int) $data['thirdPartyPickup'] : 0,
            'awbRecipient' => isset($data['awbRecipient']) && is_array($data['awbRecipient']) ? $data['awbRecipient'] : array(),
            'observation' => isset($data['observation']) ? (string) $data['observation'] : '',
            'clientInternalReference' => isset($data['clientInternalReference']) ? (string) $data['clientInternalReference'] : '',
            'parcels' => isset($data['parcels']) && is_array($data['parcels']) ? $data['parcels'] : array(array('weight' => 1.0)),
        );

        if (!empty($data['lockerLastMile'])) {
            $this->data['lockerLastMile'] = (int) $data['lockerLastMile'];
        }

        if (isset($data['serviceTaxes']) && is_array($data['serviceTaxes'])) {
            $this->data['serviceTaxes'] = $data['serviceTaxes'];
        }
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return self
     */
    public static function fromArray(array $data)
    {
        return new self($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function toFormArray()
    {
        return $this->data;
    }
}

