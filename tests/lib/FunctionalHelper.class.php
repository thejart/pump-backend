<?php

class FunctionalHelper {
    private $pdo;

    public function __construct($database, $username, $password) {
        // No need to reinitialize the pdo if it's already setup
        if ($this->pdo) {
            return;
        }

        $this->pdo = new PDO("mysql:host=127.0.0.1;dbname=". $database, $username, $password);
    }

    public function insertIntoPumpEvents($type, $timestamp, $x = 1, $y = 2, $z = 3) {
        $query = $this->pdo->prepare("
            INSERT INTO pump_events
            (x_value, y_value, z_value, type, timestamp)
            VALUES (:x, :y, :z, :type, :timestamp)
        ");

        $query->execute([
            ':x' => $x,
            ':y' => $y,
            ':z' => $z,
            ':type' => $type,
            ':timestamp' => $timestamp
        ]);
    }

    public function getTotalNumberOfEvents() {
        $query = $this->pdo->prepare("
            SELECT COUNT(*) AS count
            FROM pump_events
        ");

        $query->execute();
        return (int)$query->fetchAll(PDO::FETCH_OBJ)[0]->count;
    }

    public function deleteAllEvents() {
        $query = $this->pdo->prepare("DELETE FROM pump_events");
        $query->execute();
    }

    public function unixTimestampToDbFormat($unixTimestamp) {
        return date("Y-m-d H:i:s", $unixTimestamp);
    }
}