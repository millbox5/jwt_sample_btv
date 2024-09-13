<?php
// Api/get_inspections.php

// Enable error reporting and display errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
        // Fetch user inspections
        $inspections = getUserInspections($conn, $userId);

        if ($inspections) {
            // Return a success response with the inspections data
            http_response_code(200);
            echo json_encode(['message' => 'Inspections found.', 'inspections' => $inspections]);
        } else {
            // Return a response indicating no inspections found
            http_response_code(200); // Use 200 OK for no content found
            echo json_encode(['message' => 'No inspections found for the user.']);
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

// Function to fetch user inspections
// Function to fetch user inspections
// Function to fetch user inspections
// Function to fetch user inspections
function getUserInspections($conn, $userId) {
    $query = "
        SELECT 
            DATE(ci.created_at) AS inspection_date,  -- Group by date only
            ci.id AS inspection_id,  -- Include inspection ID for individual inspections
            ci.notes,
            ir.result_status,
            ir.comments,
            iri.image
        FROM 
            car_inspections ci
        LEFT JOIN 
            inspection_results ir ON ci.id = ir.inspection_id  
        LEFT JOIN
            inspection_result_images iri ON ir.id = iri.inspection_result_id
        WHERE 
            ci.car_id IN (SELECT id FROM cars WHERE user_id = :user_id)
        ORDER BY 
            inspection_date DESC, ci.created_at DESC";  // Order by inspection date and time

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $userId);
    
    try {
        $stmt->execute();
        $inspections = $stmt->fetchAll(PDO::FETCH_ASSOC); // Fetch all records as an associative array
        
        // Debugging output
        error_log("User ID: " . $userId);
        error_log("Inspections: " . print_r($inspections, true));
        
        return $inspections; // Return the inspections
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return null;
    }
}
?>