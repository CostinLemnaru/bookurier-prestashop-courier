<?php
/**
 * DTO for Bookurier AWB creation payload.
 */

namespace Bookurier\DTO\Bookurier;

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
            'pickup_point' => isset($data['pickup_point']) ? (string) $data['pickup_point'] : '',
            'unq' => isset($data['unq']) ? (string) $data['unq'] : '',
            'recv' => isset($data['recv']) ? (string) $data['recv'] : '',
            'phone' => isset($data['phone']) ? (string) $data['phone'] : '',
            'email' => isset($data['email']) ? (string) $data['email'] : '',
            'country' => isset($data['country']) ? (string) $data['country'] : 'Romania',
            'city' => isset($data['city']) ? (string) $data['city'] : '',
            'zip' => isset($data['zip']) ? (string) $data['zip'] : '',
            'district' => isset($data['district']) ? (string) $data['district'] : '',
            'street' => isset($data['street']) ? (string) $data['street'] : '',
            'no' => isset($data['no']) ? (string) $data['no'] : '',
            'bl' => isset($data['bl']) ? (string) $data['bl'] : '',
            'ent' => isset($data['ent']) ? (string) $data['ent'] : '',
            'floor' => isset($data['floor']) ? (string) $data['floor'] : '',
            'apt' => isset($data['apt']) ? (string) $data['apt'] : '',
            'interphone' => isset($data['interphone']) ? (string) $data['interphone'] : '',
            'service' => isset($data['service']) ? (string) $data['service'] : '9',
            'packs' => isset($data['packs']) ? (string) $data['packs'] : '1',
            'weight' => isset($data['weight']) ? (string) $data['weight'] : '1',
            'rbs_val' => isset($data['rbs_val']) ? (string) $data['rbs_val'] : '0',
            'insurance_val' => isset($data['insurance_val']) ? (string) $data['insurance_val'] : '0',
            'ret_doc' => isset($data['ret_doc']) ? (string) $data['ret_doc'] : '0',
            'weekend' => isset($data['weekend']) ? (string) $data['weekend'] : '0',
            'unpack' => isset($data['unpack']) ? (string) $data['unpack'] : '0',
            'exchange_pack' => isset($data['exchange_pack']) ? (string) $data['exchange_pack'] : '0',
            'confirmation' => isset($data['confirmation']) ? (string) $data['confirmation'] : '0',
            'notes' => isset($data['notes']) ? (string) $data['notes'] : '',
            'ref1' => isset($data['ref1']) ? (string) $data['ref1'] : '',
            'ref2' => isset($data['ref2']) ? (string) $data['ref2'] : '',
        );
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
    public function toApiArray()
    {
        return $this->data;
    }
}

