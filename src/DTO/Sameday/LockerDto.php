<?php
/**
 * DTO for SameDay locker entries.
 */

namespace Bookurier\DTO\Sameday;

class LockerDto
{
    /**
     * @var int
     */
    private $lockerId;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $county;

    /**
     * @var string
     */
    private $city;

    /**
     * @var string
     */
    private $address;

    /**
     * @var string
     */
    private $postalCode;

    /**
     * @var float
     */
    private $lat;

    /**
     * @var float
     */
    private $lng;

    /**
     * @var int
     */
    private $boxesCount;

    /**
     * @param int $lockerId
     * @param string $name
     * @param string $county
     * @param string $city
     * @param string $address
     * @param string $postalCode
     * @param float $lat
     * @param float $lng
     * @param int $boxesCount
     */
    public function __construct(
        $lockerId,
        $name,
        $county,
        $city,
        $address,
        $postalCode,
        $lat,
        $lng,
        $boxesCount
    ) {
        $this->lockerId = (int) $lockerId;
        $this->name = (string) $name;
        $this->county = (string) $county;
        $this->city = (string) $city;
        $this->address = (string) $address;
        $this->postalCode = (string) $postalCode;
        $this->lat = (float) $lat;
        $this->lng = (float) $lng;
        $this->boxesCount = (int) $boxesCount;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return self
     */
    public static function fromApiArray(array $data)
    {
        return new self(
            isset($data['lockerId']) ? (int) $data['lockerId'] : 0,
            isset($data['name']) ? (string) $data['name'] : '',
            isset($data['county']) ? (string) $data['county'] : '',
            isset($data['city']) ? (string) $data['city'] : '',
            isset($data['address']) ? (string) $data['address'] : '',
            isset($data['postalCode']) ? (string) $data['postalCode'] : '',
            isset($data['lat']) ? (float) $data['lat'] : 0.0,
            isset($data['long']) ? (float) $data['long'] : (isset($data['lng']) ? (float) $data['lng'] : 0.0),
            isset($data['availableBoxes']) && is_array($data['availableBoxes']) ? count($data['availableBoxes']) : 0
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return array(
            'lockerId' => $this->lockerId,
            'name' => $this->name,
            'county' => $this->county,
            'city' => $this->city,
            'address' => $this->address,
            'postalCode' => $this->postalCode,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'boxesCount' => $this->boxesCount,
        );
    }
}
