<?php
include_once './db_connection/config.php';

header("Access-Control-Allow-Origin: * ");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

$phonenumber = '';
$password = '';
$user_type = 'managed'; // Default user type
$conn = null;

$databaseService = new DatabaseService();
$conn = $databaseService->getConnection();

if (!$conn) {
    http_response_code(500);
    echo json_encode(array("message" => "Database connection failed."));
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->phonenumber) || !isset($data->password) || !isset($data->user_type)) {
    http_response_code(400);
    echo json_encode(array("message" => "Invalid input. Phone number, password, and user type are required."));
    exit();
}

$phonenumber = $data->phonenumber;
$password = $data->password;
$user_type = $data->user_type;

// Validate user type
if (!in_array($user_type, ['managed', 'admin'])) {
    http_response_code(400);
    echo json_encode(array("message" => "Invalid user type. Must be 'managed' or 'admin'."));
    exit();
}

$table_name_users = 'users';
$table_name_api_keys = 'api_keys';

// Check if the user already exists
$query_check = "SELECT * FROM " . $table_name_users . " WHERE phonenumber = :phonenumber";
$stmt_check = $conn->prepare($query_check);
$stmt_check->bindParam(':phonenumber', $phonenumber);
$stmt_check->execute();

if ($stmt_check->rowCount() > 0) {
    http_response_code(400);
    echo json_encode(array("message" => "User with this phone number already exists."));
    exit();
}

// Insert user into the users table
$query = "INSERT INTO " . $table_name_users . "
                SET phonenumber = :phonenumber,
                    password = :password,
                    user_type = :user_type";

$stmt = $conn->prepare($query);
$stmt->bindParam(':phonenumber', $phonenumber);
$password_hash = password_hash($password, PASSWORD_BCRYPT);
$stmt->bindParam(':password', $password_hash);
$stmt->bindParam(':user_type', $user_type); // Bind the user type

// Execute user registration
if ($stmt->execute()) {
    // Get the last inserted user ID
    $user_id = $conn->lastInsertId(); // Retrieve the ID of the newly created user

    // Generate a random API key
    $api_key = bin2hex(random_bytes(32)); // Generate a random API key

    // Insert the API key into the api_keys table
    $query_api_key = "INSERT INTO " . $table_name_api_keys . " (user_id, api_key) VALUES (:user_id, :api_key)";
    $stmt_api_key = $conn->prepare($query_api_key);
    $stmt_api_key->bindParam(':user_id', $user_id); // Use the user ID from the previous insert
    $stmt_api_key->bindParam(':api_key', $api_key);

    if ($stmt_api_key->execute()) {
        http_response_code(200);
        echo json_encode(array(
            "message" => "User was successfully registered.",
            // "api_key" => $api_key // Return the generated API key
        ));
    } else {
        http_response_code(400);
        echo json_encode(array("message" => "User registered, but unable to create API key.", "error" => $stmt_api_key->errorInfo()));
    }
} else {
    http_response_code(400);
    echo json_encode(array("message" => "Unable to register the user.", "error" => $stmt->errorInfo()));
}
?>