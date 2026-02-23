<?php
/**
 * SMSService.php
 * A modular class to handle SMS notifications for the WBMS-BHS.
 */

class SMSService {
    private $apiKey;
    private $senderId;
    private $isMock;

    public function __construct($apiKey = null, $senderId = 'KIBENES', $isMock = true) {
        $this->apiKey = $apiKey;
        $this->senderId = $senderId;
        $this->isMock = $isMock;
    }

    /**
     * Sends an SMS message
     * @param string $phone The recipient phone number
     * @param string $message The message content
     * @return bool Success or failure
     */
    public function send($phone, $message) {
        if (empty($phone)) return false;

        // Clean phone number (e.g., ensure it starts with 09... or +63...)
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        if ($this->isMock) {
            $this->logMessage($phone, $message);
            return true;
        }

        // Integration point for external API (e.g., Twilio, Semaphore, etc.)
        // Example with cURL:
        /*
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.provider.com/send");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'apikey' => $this->apiKey,
            'number' => $phone,
            'message' => $message,
            'sendername' => $this->senderId
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        return true;
        */

        return false;
    }

    /**
     * Logs the message for local development/audit
     */
    private function logMessage($phone, $message) {
        $logPath = __DIR__ . '/../logs/sms_log.txt';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] TO: $phone | MSG: $message" . PHP_EOL;
        
        if (!is_dir(dirname($logPath))) {
            mkdir(dirname($logPath), 0777, true);
        }
        
        file_put_contents($logPath, $logEntry, FILE_APPEND);
    }

    /**
     * Standard Templates
     */
    public static function getAppointmentTemplate($name, $date, $type) {
        return "Hi $name, this is a reminder for your $type appointment scheduled for tomorrow, $date. See you at the Health Station!";
    }
}
