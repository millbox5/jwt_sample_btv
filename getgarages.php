<?php
// Api/get_garages.php

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
        // Get latitude, longitude, and county from POST body
        $data = json_decode(file_get_contents("php://input"), true);
        $latitude = isset($data['latitude']) ? $data['latitude'] : null;
        $longitude = isset($data['longitude']) ? $data['longitude'] : null;
        $county = isset($data['county']) ? $data['county'] : null; // New county parameter

        // Check if both latitude and longitude are provided
        if ($latitude && $longitude) {
            // Fetch place details using Nominatim API
            $placeDetails = getPlaceDetails($latitude, $longitude);
            if ($placeDetails && isset($placeDetails['address']['county'])) {
                $county = $placeDetails['address']['county']; // Extract county from place details
            } else {
                // Return an error response if place details could not be fetched or county not found
                http_response_code(404);
                echo json_encode(['error' => 'Place details not found or county not available.']);
                exit();
            }
        } elseif (!$county) {
            // If no county is specified, return all garages for the authenticated user
            $garages = getAllGarages($conn, $userId);

            if ($garages) {
                // Return a success response with the garages data
                http_response_code(200);
                echo json_encode(['message' => 'All garages found.', 'garages' => $garages]);
            } else {
                // Return an error response if no garages found
                http_response_code(404);
                echo json_encode(['message' => 'No garages found for the authenticated user.']);
            }
            exit();
        }

        // Fetch garages by county name
        $garages = getGaragesByCounty($conn, $county);

        if ($garages) {
            // Return a success response with the garages data and place details
            http_response_code(200);
            echo json_encode([
                'message' => 'Garages found.',
                'garages' => $garages,
                'place_details' => $placeDetails // Include place details
            ]);
        } else {
            // Return an error response if no garages found
            http_response_code(404);
            echo json_encode(['message' => 'No garages found for the specified county.']);
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

// Function to fetch all garages for a specific user
function getAllGarages($conn, $userId) {
    $query = "SELECT id, name, address, phone, longitude, latitude FROM garages WHERE user_id = :user_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC); // Fetch all records as an associative array
}

// Function to fetch garages by county name
function getGaragesByCounty($conn, $county) {
    $query = "SELECT id, name, address, phone, longitude, latitude FROM garages WHERE LOWER(address) LIKE LOWER(:county)";
    $stmt = $conn->prepare($query);
    $countyParam = '%' . $county . '%'; // Prepare for partial matching
    $stmt->bindParam(':county', $countyParam);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC); // Fetch all records as an associative array
}

// Function to get place details from Nominatim API
function getPlaceDetails($latitude, $longitude) {
    $url = "https://nominatim.openstreetmap.org/reverse?lat={$latitude}&lon={$longitude}&format=json";

    // Set up the HTTP context with a User-Agent
    $options = [
        "http" => [
            "header" => "User-Agent: kcfx/1.0\r\n" // Replace YourAppName with your application name
        ]
    ];
    $context = stream_context_create($options);

    // Fetch the response with the context
    $response = file_get_contents($url, false, $context);
    if ($response === FALSE) {
        return null; // Return null if the API call fails
    }

    return json_decode($response, true); // Decode JSON response as an associative array
}
?>