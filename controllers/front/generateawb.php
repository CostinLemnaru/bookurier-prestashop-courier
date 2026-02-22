<?php

class BookurierGenerateawbModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $idOrder = (int) Tools::getValue('id_order');
        $token = (string) Tools::getValue('token');
        if ($idOrder <= 0 || !$this->module->validateAwbGenerateToken($idOrder, $token)) {
            $this->sendJson(403, false, 'Invalid token.');
        }

        try {
            $result = $this->module->generateAwbForOrderId($idOrder);
            $awbCode = '';
            if (is_array($result)) {
                $awbCode = trim((string) ($result['awb_code'] ?? ''));
            }

            $message = $awbCode !== '' ? 'AWB generated: ' . $awbCode : 'AWB generation completed.';
            $this->sendJson(200, true, $message, array('awb_code' => $awbCode));
        } catch (\Exception $exception) {
            $this->sendJson(500, false, 'Could not generate AWB: ' . $exception->getMessage());
        }
    }

    private function sendJson($statusCode, $success, $message, array $extra = array())
    {
        http_response_code((int) $statusCode);
        header('Content-Type: application/json; charset=UTF-8');

        $payload = array_merge(array(
            'success' => (bool) $success,
            'message' => (string) $message,
        ), $extra);

        echo json_encode($payload);
        exit;
    }
}
