<?php

class DatabaseService{
    private $db_host = "localhost";
    private $db_name = "kacafix_db";
    private $db_user = "root";
    private $db_password = "";
    private $connection;
    

    public function getConnection(){
        $this->connection = null;
        try{
            $this->connection = new PDO("mysql:host=" . $this->db_host . ";dbname=" . $this->db_name, $this->db_user, $this->db_password);
        }catch(PDOException $exception){
            echo "Connection failed: " . $exception->getMessage();
        }
        return $this->connection;
    }

    public function getUserById($id) {
        $connection = $this->getConnection();
        $query = "SELECT * FROM users WHERE id = :id";
        $stmt = $connection->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
        if (!$user['api_key']) {
            $apiKey = bin2hex(random_bytes(16)); // generate a random API key
            $hashedApiKey = password_hash($apiKey, PASSWORD_BCRYPT); // hash the API key
            $query = "UPDATE users SET api_key = :api_key WHERE id = :id";
            $stmt = $connection->prepare($query);
            $stmt->bindParam(":api_key", $hashedApiKey);
            $stmt->bindParam(":id", $id);
            $stmt->execute();
            $user['api_key'] = $apiKey; // store the API key in the user array
        }
    
        return $user;
    }
}
?>