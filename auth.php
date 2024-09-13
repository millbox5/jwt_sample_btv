<?php
// Api/register_garage.php

// Include database connection
include_once './db_connection/config.php';
require "./vendor/autoload.php";

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Secret key for JWT
$secret_key = "c9ac86e485bfc9f113ce1ee1cda6505113c8fb26bc05301c2e4a5c9ec277b404b664121329eec3c702c9279cf395885203fe6dedad7d54de1fc478d0086634aa4ee062e1b0194585f63b855635df43b49509a8c6bbef8fb8fd7520db9a8621be4095adeebd9a044e2753e96fe95f156a0329718eb9591410f7dfb68cb1d1b46b195ec2692617a22668efdb85b4118a7c5e3720fa76471d094f1ebec324dba1d5c3b1704e82e17ee0c3881ea77b561a266a2d17b092d5ff41a14f3fecb90c40273b12ba0b091c01f3db395ba5a8853c58b303070b22b5f80ef3a73f834b80de9f497ccae7938a8ec36be0c2fdc7faf445a951be64755d291f8aa58559b7a666ca";

// Create a database connection
$databaseService = new DatabaseService();
$conn = $databaseService->getConnection();

// Get the Authorization header
$authHeader = apache_request_headers();
if (isset($authHeader['Authorization'])) {
    list($authType, $authValue) = explode(" ", $authHeader['Authorization']);

    if ($authType === "Bearer") {
        $jwt = strval($authValue);
        token_authorization($jwt, $conn);
    } else {
        http_response_code(401);
        echo json_encode(array("message" => "Access denied. Invalid authorization type."));
    }
} else {
    http_response_code(401);
    echo json_encode(array("message" => "Access denied. No JWT token provided."));
}

// Function to authorize the token
function token_authorization($jwt, $conn)
{
    global $secret_key; // Use the global secret key

    if ($jwt) {
        try {
            // Decode the JWT
            $decoded = JWT::decode($jwt, new Key($secret_key, 'HS256'));

            // Get the user ID from the decoded token
            $user_id = $decoded->data->id;

            // Verify the user from the database
            $user = getUserById($user_id, $conn);

            if ($user) {
                // Fetch the API key for the user
                $api_key = getApiKeyByUserId($user_id, $conn);
                
                // Get the number of garages registered by the user
                $garageCount = getGarageCountByUserId($user_id, $conn);

                // Access is granted. Return the API key, garage count, and user type
                echo json_encode(array(
                    "message" => "Access granted.",
                    "api_key" => $api_key, // Include the API key in the response
                    "garage_registered" => $garageCount, // Include the number of garages
                    "user_type" => $user['user_type'] // Include the user type
                ));
            } else {
                http_response_code(401);
                echo json_encode(array("message" => "Access denied. User not found."));
            }
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(array("message" => "Access denied. Invalid JWT token.", "error" => $e->getMessage()));
        }
    } else {
        http_response_code(401);
        echo json_encode(array("message" => "Access denied. No JWT token provided."));
    }
}

// Function to get user by ID
function getUserById($user_id, $conn)
{
    $query = "SELECT id, phonenumber, user_type FROM users WHERE id = :id LIMIT 1"; // Include user_type
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $user_id);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to get API key by user ID
function getApiKeyByUserId($user_id, $conn)
{
    $query = "SELECT api_key FROM api_keys WHERE user_id = :user_id LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $api_key_row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $api_key_row ? $api_key_row['api_key'] : null; // Return the API key or null if not found
}

// Function to get the count of garages registered by the user
function getGarageCountByUserId($user_id, $conn)
{
    $query = "SELECT COUNT(*) as count FROM garages WHERE user_id = :user_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? $result['count'] : 0; // Return the count of garages or 0 if not found
}
?>