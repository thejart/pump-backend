<?php
require_once __DIR__ . '/BaseShit.class.php';

class Vacation extends BaseShit {
    private $action;
    private $endDate;
    private $redirect = false;
    private $authenticated = false;

    public function __construct($envFile) {
        parent::__construct($envFile);
        $this->parseRequestParams();
    }

    private function parseRequestParams() {
        $this->action = $this->getRequestParam('action', 'read');
        $this->endDate = $this->getRequestParam('endDate');
        $this->redirect = (bool)$this->getRequestParam('redirect');

        if (!is_null($this->getRequestParam('authCode')) && $this->getRequestParam('authCode') == $this->shitAuth) {
            $this->authenticated = true;
        }
    }

    // Performs the requested action and returns a structured result for the entry script to render.
    public function handle() {
        // Reading vacation status is public; mutating it requires the device auth code.
        if (($this->action === 'set' || $this->action === 'clear') && !$this->authenticated) {
            return ['status' => 401, 'flash' => 'error', 'error' => 'Invalid auth code'];
        }

        switch ($this->action) {
            case 'set':
                if (is_null($this->endDate) || $this->endDate === '') {
                    return ['status' => 400, 'flash' => 'error', 'error' => 'Missing endDate'];
                }
                $success = $this->setVacationEndDate($this->endDate);
                return $success
                    ? $this->readResult(200, 'set')
                    : $this->readResult(400, 'error', 'Unable to set vacation end date');
            case 'clear':
                $this->clearVacation();
                return $this->readResult(200, 'cleared');
            case 'read':
            default:
                return $this->readResult(200, null);
        }
    }

    private function readResult($status, $flash, $error = null) {
        $endDate = $this->getActiveVacationEndDate();
        return [
            'status' => $status,
            'flash' => $flash,
            'error' => $error,
            'active' => !is_null($endDate),
            'end_date' => $endDate,
        ];
    }

    public function wantsRedirect() {
        return $this->redirect;
    }
}
