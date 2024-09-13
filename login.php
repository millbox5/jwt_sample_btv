<?php
include_once './db_connection/config.php';
require "./vendor/autoload.php";
use \Firebase\JWT\JWT;

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

$phonenumber = '';
$password = '';

$databaseService = new DatabaseService();
$conn = $databaseService->getConnection();

$data = json_decode(file_get_contents("php://input"));

$phonenumber = $data->phonenumber;
$password = $data->password;

$table_name_users = 'users';
$table_name_api_keys = 'api_keys';

// Prepare the SQL query to fetch user details
$query = "SELECT id, phonenumber, password FROM " . $table_name_users . " WHERE phonenumber = ? LIMIT 0,1";

$stmt = $conn->prepare($query);
$stmt->bindParam(1, $phonenumber);
$stmt->execute();
$num = $stmt->rowCount();

if ($num > 0) {
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $id = $row['id'];
    $phonenumber = $row['phonenumber'];
    $password2 = $row['password'];

    // Verify the password
    if (password_verify($password, $password2)) {
        // Fetch the API key from the api_keys table
        $query_api_key = "SELECT api_key FROM " . $table_name_api_keys . " WHERE user_id = ? LIMIT 1";
        $stmt_api_key = $conn->prepare($query_api_key);
        $stmt_api_key->bindParam(1, $id);
        $stmt_api_key->execute();
        $api_key_row = $stmt_api_key->fetch(PDO::FETCH_ASSOC);
        
        // Check if API key exists
        if ($api_key_row) {
            $api_key = $api_key_row['api_key'];
        } else {
            http_response_code(404);
            echo json_encode(array("message" => "API key not found for this user."));
            exit();
        }

        // JWT creation
        $secret_key = "c9ac86e485bfc9f113ce1ee1cda6505113c8fb26bc05301c2e4a5c9ec277b404b664121329eec3c702c9279cf395885203fe6dedad7d54de1fc478d0086634aa4ee062e1b0194585f63b855635df43b49509a8c6bbef8fb8fd7520db9a8621be4095adeebd9a044e2753e96fe95f156a0329718eb9591410f7dfb68cb1d1b46b195ec2692617a22668efdb85b4118a7c5e3720fa76471d094f1ebec324dba1d5c3b1704e82e17ee0c3881ea77b561a266a2d17b092d5ff41a14f3fecb90c40273b12ba0b091c01f3db395ba5a8853c58b303070b22b5f80ef3a73f834b80de9f497ccae7938a8ec36be0c2fdc7faf445a951be64755d291f8aa58559b7a666ca";
        $issuer_claim = "THE_ISSUER"; // this can be the servername
        $audience_claim = "THE_AUDIENCE";
        $issuedat_claim = time(); // issued at
        $notbefore_claim = $issuedat_claim + 10; // not before in seconds
        $expire_claim = $issuedat_claim + (60 * 60 * 24 * 365); // expire time in seconds

        $token = array(
            "iss" => $issuer_claim,
            "aud" => $audience_claim,
            "iat" => $issuedat_claim,
            "nbf" => $notbefore_claim,
            "exp" => $expire_claim,
            "data" => array(
                "id" => $id,
                "phonenumber" => $phonenumber
            )
        );

        http_response_code(200);

        // Encode the JWT
        $jwt = JWT::encode($token, $secret_key, 'HS256');
        echo json_encode(
            array(
                "message" => "Successful login.",
                // "api_key" => $api_key, // Return the fetched API key
                "token" => $jwt,
                "phonenumber" => $phonenumber,
                "expireAt" => $expire_claim
            )
        );
    } else {
        http_response_code(401);
        echo json_encode(array("message" => "Login failed. Invalid password."));
    }
} else {
    http_response_code(401);
    echo json_encode(array("message" => "Login failed. User not found."));
}
?>