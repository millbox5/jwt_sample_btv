<?php
require 'vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

$secretKey = "68V0zWFrS72GbpPreidkQFLfj4v9m3Ti+DXc8OB0gcM=chkb387hb239869h4trhui"; // Change this to a strong secret key

function db_con() {
    $username = "root"; // Change as needed
    $password = ""; // Change as needed
    $host = "localhost"; // Change as needed
    $database = "kacafix_db"; // Change as needed

    $connection = new mysqli($host, $username, $password, $database);
    
    if ($connection->connect_error) {
        http_response_code(500);
        echo json_encode(array("message" => "Error connecting to the database."));
        exit;
    }
    
    return $connection;
}

function register_user($username, $password) {
    $connection = db_con();
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    
    $stmt = $connection->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
    $stmt->bind_param("ss", $username, $hashed_password);
    
    if ($stmt->execute()) {
        $stmt->close();
        $connection->close();
        return true;
    } else {
        $stmt->close();
        $connection->close();
        return false; // Registration failed (e.g., username already exists)
    }
}

function authenticate_user($username, $password) {
    $connection = db_con();
    $stmt = $connection->prepare("SELECT id, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $hashed_password);
        $stmt->fetch();
        
        if (password_verify($password, $hashed_password)) {
            $stmt->close();
            $connection->close();
            return $id; // Authentication successful
        }
    }
    
    $stmt->close();
    $connection->close();
    return false; // Authentication failed
}

function generate_kid() {
    return bin2hex(random_bytes(16)); // Generates a random 32-character hexadecimal string
}

function generate_jwt($user_id) {
    global $secretKey;
    $kid = generate_kid(); // Generate a unique kid for the token
    $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT', 'kid' => $kid]); // Include a 'kid'
    $payload = json_encode([
        "iat" => time(),
        "exp" => time() + (60 * 60), // Token expires in 1 hour
        
        "sub" => $user_id
    ]);

    // Base64Url encode the header and payload
    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    // Create the signature
    $signature = hash_hmac('sha256', $base64UrlHeader . '.' . $base64UrlPayload, $secretKey, true);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

    // Generate the JWT
    $jwt = $base64UrlHeader . '.' . $base64UrlPayload . '.' . $base64UrlSignature;

    // Debugging: Log the generated token and secret key
    error_log("Generated JWT: " . $jwt);
    error_log("Secret Key Used: " . $secretKey);

    return $jwt;
}

function verify_token() {
    global $secretKey;
    $headers = apache_request_headers();
    if (isset($headers['Authorization'])) {
        $token = str_replace('Bearer ', '', $headers['Authorization']);
        
        // Split the token into parts
        list($header, $payload, $signature) = explode('.', $token);
        
        // Decode the header and check for 'kid'
        $decoded_header = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $header)), true);
        if (empty($decoded_header['kid'])) {
            http_response_code(401);
            echo json_encode(array("message" => "Key ID (kid) is missing."));
            exit;
        }

        // Verify the token
        try {
            $decoded = JWT::decode($token, $secretKey, ['HS256']);
            return $decoded;
        } catch (ExpiredException $e) {
            http_response_code(401);
            echo json_encode(array("message" => "Token has expired."));
            exit;
        } catch (Exception $e) {
            // Debugging: Log the exception message
            error_log("Token verification error: " . $e->getMessage());
            http_response_code(401);
            echo json_encode(array("message" => "Invalid token."));
            exit;
        }
    }
    http_response_code(401);
    echo json_encode(array("message" => "Authorization header not found."));
    exit;
}

// Handle different HTTP methods
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    // Registration
    if (isset($data['action']) && $data['action'] === 'register') {
        if (isset($data['username']) && isset($data['password'])) {
            $username = $data['username'];
            $password = $data['password'];
            
            if (register_user($username, $password)) {
                http_response_code(201);
                echo json_encode(array("message" => "User registered successfully."));
            } else {
                http_response_code(409); // Conflict
                echo json_encode(array("message" => "Username already exists."));
            }
        } else {
            http_response_code(400); // Bad Request
            echo json_encode(array("message" => "Missing username or password."));
        }
    }
    
    // Login
    elseif (isset($data['action']) && $data['action'] === 'login') {
        if (isset($data['username']) && isset($data['password'])) {
            $username = $data['username'];
            $password = $data['password'];
            
            $user_id = authenticate_user($username, $password);
            if ($user_id) {
                $jwt = generate_jwt($user_id); // Store the token in the database
                echo json_encode(array("token" => $jwt));
            } else {
                http_response_code(401); // Unauthorized
                echo json_encode(array("message" => "Invalid credentials."));
            }
        } else {
            http_response_code(400); // Bad Request
            echo json_encode(array("message" => "Missing username or password."));
        }
    }
} elseif ($method === 'GET') {
    // Access user information
    if (isset($_GET['action']) && $_GET['action'] === 'user') {
        $user = verify_token(); // Verify the token
        echo json_encode(array("message" => "Access granted", "user_id" => $user->sub));
    }
} elseif ($method === 'OPTIONS') {
    // Handle preflight requests for CORS
    http_response_code(200);
} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(array("message" => "Method not allowed."));
}
?>