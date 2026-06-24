<?php
require_once __DIR__ . '/BaseShit.class.php';

class Vacation extends BaseShit {
    // Don't text a new code more often than this (guards the public dashboard from spamming
    // your phone / running up Textbelt cost). A pending challenge also blocks re-issuance until it expires.
    const REQUEST_RATELIMIT_SECONDS = 120;

    private $action;
    private $op;
    private $endDate;
    private $code;

    public function __construct($envFile) {
        // Texting secrets are needed to send the one-time confirmation code.
        parent::__construct($envFile, true);
        $this->parseRequestParams();
    }

    private function parseRequestParams() {
        $this->action = $this->getRequestParam('action', 'read');
        $this->op = $this->getRequestParam('op');
        $this->endDate = $this->getRequestParam('endDate');
        $this->code = $this->getRequestParam('code');
    }

    public function handle() {
        switch ($this->action) {
            case 'request':
                return $this->handleRequest();
            case 'confirm':
                return $this->handleConfirm();
            case 'read':
            default:
                return $this->readResult(200);
        }
    }

    // Generates a code for a pending set/clear, stores it, and texts it to the configured number(s).
    private function handleRequest() {
        if ($this->op !== 'set' && $this->op !== 'clear') {
            return ['status' => 400, 'ok' => false, 'error' => 'Unknown action'];
        }

        $payload = null;
        if ($this->op === 'set') {
            if (is_null($this->endDate) || $this->endDate === '' || strtotime($this->endDate) === false) {
                return ['status' => 400, 'ok' => false, 'error' => 'Pick a valid date first'];
            }
            $payload = date("Y-m-d H:i:s", strtotime($this->endDate));
        }

        $secondsSinceLast = $this->secondsSinceLastAuthChallenge();
        if (!is_null($secondsSinceLast) && $secondsSinceLast < self::REQUEST_RATELIMIT_SECONDS) {
            return ['status' => 429, 'ok' => false, 'error' => 'A code was just sent — check your phone or wait a moment.'];
        }

        $code = $this->generateCode();
        if (!$this->createAuthChallenge($this->op, $payload, $code)) {
            return ['status' => 500, 'ok' => false, 'error' => 'Unable to start confirmation'];
        }

        $minutes = (int)(self::AUTH_CHALLENGE_TTL_SECONDS / 60);
        $this->sendText("Pump dashboard code: {$code} (expires in {$minutes} min)");

        return ['status' => 200, 'ok' => true, 'message' => 'A confirmation code was texted to you.'];
    }

    // Validates the texted code and, on success, commits the pending set/clear it was bound to.
    private function handleConfirm() {
        if (is_null($this->code) || $this->code === '') {
            return ['status' => 400, 'ok' => false, 'error' => 'Enter the code'];
        }

        $challenge = $this->verifyAuthChallenge($this->code);
        if (is_null($challenge)) {
            return ['status' => 401, 'ok' => false, 'error' => 'Invalid or expired code'];
        }

        if ($challenge->action === 'set') {
            $this->setVacationEndDate($challenge->payload);
        } else {
            $this->clearVacation();
        }

        return $this->readResult(200);
    }

    private function generateCode() {
        return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function readResult($status, $extra = []) {
        $endDate = $this->getActiveVacationEndDate();
        return array_merge([
            'status' => $status,
            'ok' => $status < 400,
            'active' => !is_null($endDate),
            'end_date' => $endDate,
        ], $extra);
    }
}
