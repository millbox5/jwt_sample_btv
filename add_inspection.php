<?php
// Api/add_inspection.php

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
        // Get inspection data from POST body
        $data = json_decode(file_get_contents("php://input"), true);
        $carId = isset($data['car_id']) ? $data['car_id'] : null;
        $notes = isset($data['notes']) ? $data['notes'] : null;
        $imageData = isset($data['images']) ? $data['images'] : []; // Expecting an array of base64 encoded images

        // Validate required fields
        if (!$carId) {
            http_response_code(400);
            echo json_encode(['error' => 'Car ID is required.']);
            exit();
        }

        // Check if the car exists
        if (!carExists($conn, $carId, $userId)) {
            http_response_code(404);
            echo json_encode(['error' => 'Car not found. Please register your car first.']);
            exit();
        }

        // Insert inspection data into the database
        $inspectionId = insertInspectionData($conn, $carId, $notes);

        if ($inspectionId) {
            // Process images and get results
            $results = processImages($imageData);

            // Save results to the database
            foreach ($results as $result) {
                // Assuming result contains 'image', 'issues', and 'confidence'
                $image = $result['image']; // Base64 encoded image
                $issues = $result['issues']; // Array of issues
                $resultStatus = implode(', ', $issues); // Join issues as a string
                $comments = isset($result['comments']) ? $result['comments'] : ''; // Optional comments

                // Insert inspection result into the database
                $inspectionResultId = insertInspectionResult($conn, $inspectionId, $resultStatus, $comments);

                // Now insert the images into the inspection_result_images table
                if ($inspectionResultId) {
                    insertInspectionResultImage($conn, $inspectionResultId, $image, $comments);
                }
            }

            http_response_code(201);
            echo json_encode(['message' => 'Inspection added successfully.', 'results' => $results]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to add inspection.']);
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

// Function to check if the car exists
function carExists($conn, $carId, $userId) {
    $query = "SELECT COUNT(*) FROM cars WHERE id = :car_id AND user_id = :user_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':car_id', $carId);
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    return $stmt->fetchColumn() > 0; // Return true if the car exists
}

// Function to insert inspection data
function insertInspectionData($conn, $carId, $notes) {
    $query = "INSERT INTO car_inspections (car_id, notes) VALUES (:car_id, :notes)";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':car_id', $carId);
    $stmt->bindParam(':notes', $notes);

    if ($stmt->execute()) {
        return $conn->lastInsertId(); // Return the inserted inspection ID
    } else {
        return null; // Return null if insertion fails
    }
}

// Function to insert inspection results
function insertInspectionResult($conn, $inspectionId, $resultStatus, $comments) {
    $query = "INSERT INTO inspection_results (inspection_id, result_status, comments) VALUES (:inspection_id, :result_status, :comments)";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':inspection_id', $inspectionId);
    $stmt->bindParam(':result_status', $resultStatus);
    $stmt->bindParam(':comments', $comments);

    if ($stmt->execute()) {
        return $conn->lastInsertId(); // Return the inserted result ID
    } else {
        return null; // Return null if insertion fails
    }
}

// Function to insert inspection result images
function insertInspectionResultImage($conn, $inspectionResultId, $image, $comments) {
    $query = "INSERT INTO inspection_result_images (inspection_result_id, image, comments) VALUES (:inspection_result_id, :image, :comments)";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':inspection_result_id', $inspectionResultId);
    $stmt->bindParam(':image', $image, PDO::PARAM_LOB); // Bind the image as a BLOB
    $stmt->bindParam(':comments', $comments);

    return $stmt->execute(); // Return true if insertion is successful
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

// Function to process images (mock implementation for testing)
function processImages($imageData) {
    // Create a temporary file to store images
    $tempDir = sys_get_temp_dir();
    $imageFiles = [];
    
    foreach ($imageData as $index => $image) {
        // Decode the base64 image
        $imageBlob = base64_decode($image);
        $imagePath = $tempDir . "/image_{$index}.jpg";
        file_put_contents($imagePath, $imageBlob);
        $imageFiles[] = $imagePath; // Store the path for processing
    }

    // Mock results for testing purposes
    $mockResults = [];
    foreach ($imageFiles as $imageFile) {
        // Create a mock result for each image
        $mockResults[] = [
            'image' => base64_encode(file_get_contents($imageFile)), // Return the image as base64
            'issues' => ['Dent', 'Scratch'], // Mock issues
            'confidence' => [0.85, 0.75] // Mock confidence scores
        ];
    }

    // Clean up temporary files
    foreach ($imageFiles as $imageFile) {
        unlink($imageFile); // Delete the temporary image file
    }

    // Return the mock results
    return $mockResults;
}
?>