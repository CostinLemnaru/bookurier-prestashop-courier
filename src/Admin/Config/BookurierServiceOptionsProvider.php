<?php

namespace Bookurier\Admin\Config;

class BookurierServiceOptionsProvider
{
    public function getOptions()
    {
        return array(
            array('id' => 1, 'name' => 'Bucuresti 24h (1)'),
            array('id' => 3, 'name' => 'Metropolitan (3)'),
            array('id' => 5, 'name' => 'Ilfov Extins (5)'),
            array('id' => 7, 'name' => 'Bucuresti Today (7)'),
            array('id' => 8, 'name' => 'National Economic (8)'),
            array('id' => 9, 'name' => 'National 24 (9)'),
            array('id' => 11, 'name' => 'National Premium (11)'),
        );
    }

    public function isValid($service)
    {
        foreach ($this->getOptions() as $option) {
            if ((int) $option['id'] === (int) $service) {
                return true;
            }
        }

        return false;
    }
}
