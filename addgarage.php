<?php
// Api/register_garage.php

// Include database connection
include_once './db_connection/config.php';

// Set headers for CORS and content type
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Create a database connection
$databaseService = new DatabaseService();
$conn = $databaseService->getConnection();
$data = json_decode(file_get_contents('php://input'), true); // Decode JSON as an associative array

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the API key from the Authorization header
    $apiKey = null;
    $authHeader = apache_request_headers();
    if (isset($authHeader['Authorization'])) {
        // Extract the API key from the Bearer token
        $authParts = explode(" ", $authHeader['Authorization']);

        if (count($authParts) === 2 && $authParts[0] === "Bearer") {
            $apiKey = $authParts[1];
        }
    }

    // Validate the API key and get the user ID
    $userId = getUserIdByApiKey($apiKey, $conn);
    
    if ($userId) {
        // Check if the user is an admin
        if (isUserAdmin($userId, $conn)) {
            // Process the garage registration data
            $name = $data['name'] ?? null;
            $address = $data['address'] ?? null;
            $phone = $data['phone'] ?? null;
            $longitude = $data['longitude'] ?? null; // New field
            $latitude = $data['latitude'] ?? null;   // New field

            // Check for required fields
            if ($name && $address && $phone && $longitude && $latitude) {
                // Check if the garage name already exists for the user
                if (!isGarageNameUnique($name, $userId, $conn)) {
                    // Return an error response for duplicate name
                    http_response_code(400);
                    echo json_encode(['error' => 'Garage name already exists for this user.']);
                    exit();
                }

                // Save the garage data to the database
                $garageId = saveGarageData($name, $address, $phone, $longitude, $latitude, $userId, $conn);

                // Prepare the response with garage info
                $response = [
                    'message' => 'Garage successfully registered.',
                    'garage' => [
                        'id' => $garageId,
                        'name' => $name,
                        'address' => $address,
                        'phone' => $phone,
                        'longitude' => $longitude,
                        'latitude' => $latitude,
                        'user_id' => $userId // Optionally include user ID if needed
                    ]
                ];

                // Return a success response
                http_response_code(200);
                echo json_encode($response);
            } else {
                // Return an error response for missing fields
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields']);
            }
        } else {
            // Return an error response if the user is not an admin
            http_response_code(403);
            echo json_encode(['error' => 'Access denied. Only admin users can register garages.']);
        }
    } else {
        // Return an error response for invalid API key
        http_response_code(401);
        echo json_encode(['error' => 'Invalid API key']);
    }
} else {
    // Return a method not allowed response
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

// Function to get user ID by API key
function getUserIdByApiKey($apiKey, $conn) {
    if (!$apiKey) {
        return null; // Return null if no API key is provided
    }

    $query = "SELECT user_id FROM api_keys WHERE api_key = :apiKey LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':apiKey', $apiKey);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? $result['user_id'] : null; // Return the user ID or null if not found
}

// Function to check if the user is an admin
function isUserAdmin($userId, $conn) {
    $query = "SELECT user_type FROM users WHERE id = :userId LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':userId', $userId);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result && $result['user_type'] === 'admin'; // Return true if user is admin
}

// Function to check if the garage name is unique for the user
function isGarageNameUnique($name, $userId, $conn) {
    $query = "SELECT COUNT(*) as count FROM garages WHERE name = :name AND user_id = :user_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['count'] === '0'; // Return true if count is 0 (unique name)
}

// Function to save garage data
function saveGarageData($name, $address, $phone, $longitude, $latitude, $userId, $conn) {
    // Prepare the SQL statement to insert garage data
    $query = "INSERT INTO garages (name, address, phone, longitude, latitude, user_id) VALUES (:name, :address, :phone, :longitude, :latitude, :user_id)";
    $stmt = $conn->prepare($query);
    
    // Bind parameters
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':address', $address);
    $stmt->bindParam(':phone', $phone);
    $stmt->bindParam(':longitude', $longitude);
    $stmt->bindParam(':latitude', $latitude);
    $stmt->bindParam(':user_id', $userId);
    
    // Execute the statement
    if ($stmt->execute()) {
        // Return the last inserted ID
        return $conn->lastInsertId();
    } else {
        // Handle error if the insert fails
        return null; // Or throw an exception, or handle as per your application's needs
    }
}
?>