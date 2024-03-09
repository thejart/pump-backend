<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../lib/WipeCheck.class.php';

if (!isset($argv[1])) {
    echo "Usage: /path/to/php /path/to/wipecheck-textbelt.php /path/to/.env\n";
    exit(0);
}
$envFullyQualifiedPath = $argv[1];

$wipeCheck = new WipeCheck($envFullyQualifiedPath);
if ($wipeCheck->shouldText()) {
    $message = $wipeCheck->getMessage();

    // textbolt.com will only allow one text message per day (if that!), so >1 number is moot
    foreach ($wipeCheck->getTextNumbers() as $textNumber) {
        error_log( "texting {$textNumber}\n$message\n");

        // i hacked up a class to make php's curl less shitty to interact with for now
        // Note: the key "textbelt" can be used for free usage
        $curlyPost = new CurlyPost("https://textbelt.com/text");
        error_log($curlyPost->request("phone={$textNumber}&message={$message}&key={$wipeCheck->getTextbeltToken()}") . "\n");
    }
}

class CurlyPost {
    private $handler;

    public function __construct($url)
    {
        $this->handler = curl_init();
        curl_setopt($this->handler, CURLOPT_URL, $url);
        curl_setopt($this->handler, CURLOPT_POST, true);
    }

    public function request($queryFields)
    {
        curl_setopt($this->handler, CURLOPT_POSTFIELDS, $queryFields);
        curl_setopt($this->handler, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($this->handler);
        curl_close($this->handler);
        return $response;
    }
}
