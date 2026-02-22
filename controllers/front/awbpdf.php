<?php

use Bookurier\Awb\AwbRepository;

class BookurierAwbpdfModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $idOrder = (int) Tools::getValue('id_order');
        $token = (string) Tools::getValue('token');
        if ($idOrder <= 0 || !$this->module->validateAwbDownloadToken($idOrder, $token)) {
            $this->sendErrorResponse(403, 'Invalid token.');
        }

        $awb = (new AwbRepository())->findByOrderId($idOrder);
        if (!is_array($awb) || trim((string) ($awb['awb_code'] ?? '')) === '') {
            $this->sendErrorResponse(404, 'AWB not found.');
        }

        $awbCode = trim((string) $awb['awb_code']);
        $courier = strtolower(trim((string) ($awb['courier'] ?? '')));
        if ($courier === '') {
            $this->sendErrorResponse(400, 'AWB courier is missing.');
        }

        try {
            if ($courier === 'sameday') {
                $pdfContent = $this->module->getSamedayClient()->downloadAwbPdf($awbCode);
            } elseif ($courier === 'bookurier') {
                $pdfContent = (string) $this->module->getBookurierClient()->printAwbs(array($awbCode), 'pdf', 'm', 0);
            } else {
                $this->sendErrorResponse(400, 'Unsupported courier: ' . $courier);
            }
        } catch (\Exception $exception) {
            $this->sendErrorResponse(502, 'Could not download AWB PDF: ' . $exception->getMessage());
        }

        if (trim((string) $pdfContent) === '') {
            $this->sendErrorResponse(502, 'Empty AWB PDF response.');
        }

        $safeAwbCode = preg_replace('/[^A-Za-z0-9._-]/', '_', $awbCode);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="AWB-' . $safeAwbCode . '.pdf"');
        header('Content-Length: ' . strlen($pdfContent));

        echo $pdfContent;
        exit;
    }

    private function sendErrorResponse($statusCode, $message)
    {
        http_response_code((int) $statusCode);
        header('Content-Type: text/plain; charset=UTF-8');
        echo (string) $message;
        exit;
    }
}
