<?php
header("Content-Type: application/json");

// Load DB credentials from environment (Heroku or local .env)
$server   = getenv("DB_SERVER");   // ex: tcp:xxxx.database.windows.net,1433
$database = getenv("DB_NAME");     // ex: scada_db
$username = getenv("DB_USER");     // ex: dbadmin
$password = getenv("DB_PASS");     // ex: superSecret!

// Using ODBC connection
$connectionString = "odbc:Driver={ODBC Driver 18 for SQL Server};Server=$serverName;Database=$databaseName;Encrypt=yes;TrustServerCertificate=no;Connection Timeout=30;";
$pdo = new PDO($connectionString, $username, $password);
?>

// Read JSON body
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid JSON"]);
    exit;
}

// Required fields check
if (!isset($data["deviceid"]) || !isset($data["timestamp"])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "deviceid & timestamp required"]);
    exit;
}

try {
    // Connect to Azure SQL
    $conn = new PDO("sqlsrv:Server=$server;Database=$database", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Build dynamic INSERT statement based on incoming JSON keys
    $columns = array_keys($data);
    $placeholders = array_map(fn($c) => ":$c", $columns);

    $sql = "INSERT INTO massflowmeter_data (" . implode(",", $columns) . ")
            VALUES (" . implode(",", $placeholders) . ")";
    $stmt = $conn->prepare($sql);

    // Bind all values
    foreach ($data as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }

    $stmt->execute();
    echo json_encode(["status" => "success", "message" => "Data inserted"]);
}
catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
