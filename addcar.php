<?php
// Api/add_car.php

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
        // Get car data from POST body
        $data = json_decode(file_get_contents("php://input"), true);
        $make = isset($data['make']) ? $data['make'] : null;
        $model = isset($data['model']) ? $data['model'] : null;
        $year = isset($data['year']) ? $data['year'] : null;
        $problemDescription = isset($data['problem_description']) ? $data['problem_description'] : null;
        $imageData = isset($data['image']) ? $data['image'] : null; // Expecting base64 encoded image

        // Validate required fields
        if (!$make || !$model || !$year) {
            http_response_code(400);
            echo json_encode(['error' => 'Make, model, and year are required fields.']);
            exit();
        }

        // Prepare the image for storage
        $imageBlob = null;
        if ($imageData) {
            // Decode the base64 image
            $imageBlob = base64_decode($imageData);
        }

        // Insert car data into the database
        $query = "INSERT INTO cars (user_id, make, model, year, problem_description, image) VALUES (:user_id, :make, :model, :year, :problem_description, :image)";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':make', $make);
        $stmt->bindParam(':model', $model);
        $stmt->bindParam(':year', $year);
        $stmt->bindParam(':problem_description', $problemDescription);
        $stmt->bindParam(':image', $imageBlob, PDO::PARAM_LOB); // Bind the image as a BLOB

        if ($stmt->execute()) {
            http_response_code(201);
            echo json_encode(['message' => 'Car added successfully.']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to add car.']);
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
?>