<?php

use Bookurier\Admin\Config\SamedayLockerRepository;
use Bookurier\Checkout\SamedayLockerSelectionRepository;

class BookurierLockerModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        header('Content-Type: application/json');

        if (!$this->module || (int) \Configuration::get(\Bookurier::CONFIG_SAMEDAY_ENABLED) !== 1) {
            $this->jsonError('SameDay is disabled.');
        }

        $idCart = (int) $this->context->cart->id;
        $lockerId = (int) \Tools::getValue('locker_id');
        if ($idCart <= 0 || $lockerId <= 0) {
            $this->jsonError('Invalid locker selection.');
        }

        $lockerRepository = new SamedayLockerRepository();
        if (!$lockerRepository->isActiveLockerId($lockerId)) {
            $this->jsonError('Selected locker is not valid.');
        }

        $selectionRepository = new SamedayLockerSelectionRepository();
        if (!$selectionRepository->saveForCart($idCart, $lockerId)) {
            $this->jsonError('Locker selection could not be saved.');
        }

        $this->jsonSuccess();
    }

    private function jsonSuccess()
    {
        die(json_encode(array('success' => true)));
    }

    private function jsonError($message)
    {
        http_response_code(400);
        die(json_encode(array(
            'success' => false,
            'message' => (string) $message,
        )));
    }
}
